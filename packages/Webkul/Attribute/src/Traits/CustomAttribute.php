<?php

namespace Webkul\Attribute\Traits;

use Webkul\Attribute\Models\AttributeValueProxy;
use Webkul\Attribute\Repositories\AttributeRepository;

trait CustomAttribute
{
    /**
     * @var array
     */
    public static $attributeTypeFields = [
        'text'        => 'text_value',
        'textarea'    => 'text_value',
        'price'       => 'float_value',
        'boolean'     => 'boolean_value',
        'select'      => 'integer_value',
        'multiselect' => 'text_value',
        'checkbox'    => 'text_value',
        'email'       => 'json_value',
        'address'     => 'json_value',
        'phone'       => 'json_value',
        'lookup'      => 'integer_value',
        'datetime'    => 'datetime_value',
        'date'        => 'date_value',
        'file'        => 'text_value',
        'image'       => 'text_value',
    ];

    /**
     * Get the attribute values that owns the entity.
     */
    public function attribute_values()
    {
        return $this->morphMany(AttributeValueProxy::modelClass(), 'entity');
    }

    /**
     * Get an attribute from the model.
     *
     * @param  string  $key
     * @return mixed
     */
    public function getAttribute($key)
    {
        if ($this->relationLoaded($key)) {
            return $this->getRelationValue($key);
        }

        if (! method_exists(static::class, $key) && ! array_key_exists($key, $this->attributes)) {
            if (isset($this->id)) {
                $this->attributes[$key] = '';

                $attribute = app(AttributeRepository::class)->getAttributeByCode($key);

                $value = $this->getCustomAttributeValue($attribute);

                if ($this->customAttributeUsesJsonValue($attribute)) {
                    $this->attributes[$key] = $this->isJsonCastAttribute($key) && is_array($value)
                        ? json_encode($value)
                        : $value;

                    return $value;
                }

                $this->attributes[$key] = $value;

                return $this->getAttributeValue($key);
            }
        }

        $this->normalizeJsonCastAttribute($key);

        return parent::getAttribute($key);
    }

    /**
     * @return array
     */
    public function attributesToArray()
    {
        $this->normalizeJsonCastAttributes();

        $attributes = parent::attributesToArray();

        $hiddenAttributes = $this->getHidden();

        if (isset($this->id)) {
            $customAttributes = $this->getCustomAttributes();

            foreach ($customAttributes as $attribute) {
                if (in_array($attribute->code, $hiddenAttributes) && isset($this->attributes[$attribute->code])) {
                    continue;
                }

                if (array_key_exists($attribute->code, $attributes)
                    && ! $this->isEmptyCustomAttributeValue($attributes[$attribute->code])
                ) {
                    continue;
                }

                $attributes[$attribute->code] = $this->getCustomAttributeValue($attribute);
            }
        }

        return $attributes;
    }

    /**
     * Check in loaded family attributes.
     *
     * @return object
     */
    public function getCustomAttributes()
    {
        static $attributes;

        if ($attributes) {
            return $attributes;
        }

        return $attributes = app(AttributeRepository::class)->where('entity_type', $this->getTable())->get();
    }

    /**
     * Get an product attribute value.
     *
     * @return mixed
     */
    public function getCustomAttributeValue($attribute)
    {
        if (! $attribute) {
            return;
        }

        $attributeValue = $this->attribute_values->where('attribute_id', $attribute->id)->first();

        if (! $attributeValue) {
            return $this->customAttributeUsesJsonValue($attribute)
                ? $this->defaultJsonAttributeValue($attribute)
                : null;
        }

        $value = $attributeValue[self::$attributeTypeFields[$attribute->type]] ?? null;

        if ($this->customAttributeUsesJsonValue($attribute)) {
            return $this->decodeJsonAttributeValue($value, $this->defaultJsonAttributeValue($attribute));
        }

        return $value;
    }

    /**
     * Decode JSON attribute values without assuming their raw storage type.
     */
    protected function decodeJsonAttributeValue($value, $default = [])
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decodedValue = json_decode($value, true);

            return is_array($decodedValue) ? $decodedValue : $default;
        }

        return $default;
    }

    /**
     * Get the empty value expected by JSON-backed custom attribute types.
     */
    protected function defaultJsonAttributeValue($attribute)
    {
        return $attribute?->type === 'address' ? null : [];
    }

    /**
     * Determine if the custom attribute stores its value in json_value.
     */
    protected function customAttributeUsesJsonValue($attribute): bool
    {
        return $attribute && (self::$attributeTypeFields[$attribute->type] ?? null) === 'json_value';
    }

    /**
     * Convert raw array values back to JSON before Laravel's cast layer decodes them.
     */
    protected function normalizeJsonCastAttribute(string $key): void
    {
        if (! array_key_exists($key, $this->attributes)
            || ! is_array($this->attributes[$key])
            || ! $this->isJsonCastAttribute($key)
        ) {
            return;
        }

        $this->attributes[$key] = json_encode($this->attributes[$key]);
    }

    /**
     * Normalize all JSON-cast raw attributes before array serialization.
     */
    protected function normalizeJsonCastAttributes(): void
    {
        foreach (array_keys($this->attributes) as $key) {
            $this->normalizeJsonCastAttribute($key);
        }
    }

    /**
     * Determine if a model attribute is handled by Laravel's JSON cast layer.
     */
    protected function isJsonCastAttribute(string $key): bool
    {
        $cast = $this->getCasts()[$key] ?? null;

        if (! is_string($cast)) {
            return false;
        }

        $cast = strtolower($cast);
        $baseCast = explode(':', $cast, 2)[0];

        return in_array($baseCast, ['array', 'json', 'object', 'collection'], true)
            || in_array($cast, ['encrypted:array', 'encrypted:json', 'encrypted:object', 'encrypted:collection'], true);
    }

    /**
     * Determine if a model value is empty enough to fall back to EAV storage.
     */
    protected function isEmptyCustomAttributeValue($value): bool
    {
        if (is_array($value)) {
            return empty($value);
        }

        return $value === null || $value === '';
    }

    /**
     * Create a new instance of the given model.
     *
     * @param  array  $attributes
     * @return Collection
     */
    public function getLookUpAttributes($attributes)
    {
        $attributes = app(AttributeRepository::class)->scopeQuery(function ($query) use ($attributes) {
            return $query->distinct()
                ->where('type', 'lookup')
                ->where('entity_type', request('entity_type'))
                ->whereIn('code', array_keys($attributes, '', false));
        })->get();

        return $attributes;
    }

    /**
     * Create a new instance of the given model.
     *
     * @param  array  $attributes
     * @param  bool  $exists
     * @return static
     */
    public function newInstance($attributes = [], $exists = false)
    {
        // $attributes = $this->getLookUpAttributes($attributes);

        // Play with data here

        return parent::newInstance($attributes, $exists);
    }

    /**
     * Fill the model with an array of attributes.
     *
     * @return $this
     *
     * @throws \Illuminate\Database\Eloquent\MassAssignmentException
     */
    public function fill(array $attributes)
    {
        // Play with data here

        return parent::fill($attributes);
    }

    // Delete model's attribute values
    public static function boot()
    {
        parent::boot();

        static::deleting(function ($entity) {
            $entity->attribute_values()->delete();
        });
    }
}
