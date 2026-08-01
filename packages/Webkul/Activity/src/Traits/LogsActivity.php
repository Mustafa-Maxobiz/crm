<?php

namespace Webkul\Activity\Traits;

use Illuminate\Support\Facades\Schema;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Attribute\Contracts\AttributeValue;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Attribute\Repositories\AttributeValueRepository;

trait LogsActivity
{
    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::created(function ($model) {
            if (! method_exists($model->entity ?? $model, 'activities')) {
                return;
            }

            if (! $model instanceof AttributeValue) {
                $activity = app(ActivityRepository::class)->create([
                    'type'    => 'system',
                    'title'   => trans('admin::app.activities.created'),
                    'is_done' => 1,
                    'user_id' => auth()->check()
                        ? auth()->id()
                        : null,
                ]);

                $model->activities()->attach($activity->id);

                return;
            }

            static::logActivity($model);
        });

        static::updated(function ($model) {
            if (! method_exists($model->entity ?? $model, 'activities')) {
                return;
            }

            static::logActivity($model);
        });

        static::deleting(function ($model) {
            if (! method_exists($model->entity ?? $model, 'activities')) {
                return;
            }

            $model->activities()->delete();
        });
    }

    /**
     * Create activity entries for dirty attributes.
     */
    protected static function logActivity($model)
    {
        if ($model instanceof AttributeValue) {
            static::logAttributeValueActivity($model);

            return;
        }

        $updatedAttributes = static::getUpdatedAttributes($model);

        foreach ($updatedAttributes as $attributeCode => $attributeData) {
            $attribute = static::resolveEntityAttribute($model, $attributeCode);
            $attributeLabel = $attribute?->name ?: $attributeCode;

            static::storeSystemActivity(
                $model,
                $attributeLabel,
                $attributeData['old'],
                $attributeData['new'],
                $attribute
            );
        }
    }

    /**
     * Log EAV-only attribute value changes (skip column-backed attributes to avoid duplicates).
     */
    protected static function logAttributeValueActivity(AttributeValue $model): void
    {
        $attribute = $model->attribute;

        if (! $attribute) {
            return;
        }

        if (static::entityHasColumn($model->entity_type, $attribute->code)) {
            return;
        }

        if (! $model->entity || ! method_exists($model->entity, 'activities')) {
            return;
        }

        $updatedAttributes = static::getUpdatedAttributes($model);

        foreach ($updatedAttributes as $attributeData) {
            static::storeSystemActivity(
                $model->entity,
                $attribute->name ?: $attribute->code,
                $attributeData['old'],
                $attributeData['new'],
                $attribute
            );
        }
    }

    /**
     * Persist a system changelog activity on an entity that has an activities relation.
     */
    public static function storeSystemActivity(
        $entity,
        string $attributeLabel,
        mixed $oldValue,
        mixed $newValue,
        mixed $attribute = null
    ): void {
        if (! $entity || ! method_exists($entity, 'activities')) {
            return;
        }

        if (static::valuesAreEqual($oldValue, $newValue)) {
            return;
        }

        $activity = app(ActivityRepository::class)->create([
            'type'       => 'system',
            'title'      => trans('admin::app.activities.updated', ['attribute' => $attributeLabel]),
            'is_done'    => 1,
            'additional' => json_encode([
                'attribute' => $attributeLabel,
                'new'       => [
                    'value' => $newValue,
                    'label' => static::getAttributeLabel($newValue, $attribute) ?? static::stringifyLogValue($newValue),
                ],
                'old'       => [
                    'value' => $oldValue,
                    'label' => static::getAttributeLabel($oldValue, $attribute) ?? static::stringifyLogValue($oldValue),
                ],
            ]),
            'user_id'    => auth()->id(),
        ]);

        $entity->activities()->attach($activity->id);
    }

    /**
     * Resolve attribute definition for an entity column/code.
     */
    protected static function resolveEntityAttribute($model, string $code)
    {
        $entityType = method_exists($model, 'getTable') ? $model->getTable() : null;

        if (! $entityType) {
            return null;
        }

        // Lead Company Name is stored in `title`/`organization_id` but attribute code is `organization_id`.
        if ($entityType === 'leads' && in_array($code, ['title', 'companies'], true)) {
            $code = 'organization_id';
        }

        return app(AttributeRepository::class)->findOneWhere([
            'entity_type' => $entityType,
            'code'        => $code,
        ]);
    }

    /**
     * Whether the attribute is stored as a real column on the entity table.
     */
    protected static function entityHasColumn(?string $entityType, string $code): bool
    {
        if (! $entityType || ! Schema::hasTable($entityType)) {
            return false;
        }

        // `organization_id` is a native lead column; `companies` was the old text attribute code.
        if ($entityType === 'leads' && in_array($code, ['organization_id', 'companies'], true)) {
            return Schema::hasColumn($entityType, $code === 'companies' ? 'title' : 'organization_id')
                || Schema::hasColumn($entityType, 'organization_id');
        }

        return Schema::hasColumn($entityType, $code);
    }

    /**
     * Get attribute label.
     */
    protected static function getAttributeLabel($value, $attribute)
    {
        if (! $attribute) {
            return static::stringifyLogValue($value);
        }

        return app(AttributeValueRepository::class)->getAttributeLabel($value, $attribute);
    }

    /**
     * Fallback display value for changelog entries.
     */
    protected static function stringifyLogValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        return (string) $value;
    }

    protected static function valuesAreEqual(mixed $oldValue, mixed $newValue): bool
    {
        return static::decodeValueIfJson($oldValue) == static::decodeValueIfJson($newValue);
    }

    /**
     * Create activity.
     */
    protected static function getUpdatedAttributes($model)
    {
        $updatedAttributes = [];

        $dirty = $model->getChanges();

        if ($dirty === []) {
            $dirty = $model->getDirty();
        }

        foreach ($dirty as $key => $value) {
            if (in_array($key, [
                'id',
                'attribute_id',
                'entity_id',
                'entity_type',
                'created_at',
                'updated_at',
                'deleted_at',
                'unique_id',
            ], true)) {
                continue;
            }

            $newValue = static::decodeValueIfJson($value);

            $oldValue = static::decodeValueIfJson($model->getOriginal($key));

            if ($newValue != $oldValue) {
                $updatedAttributes[$key] = [
                    'new' => $newValue,
                    'old' => $oldValue,
                ];
            }
        }

        return $updatedAttributes;
    }

    /**
     * Convert value if json.
     */
    protected static function decodeValueIfJson($value)
    {
        if (
            ! is_array($value)
            && is_string($value)
            && $value !== ''
            && json_decode($value, true) !== null
            && json_last_error() === JSON_ERROR_NONE
        ) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                $value = $decoded;
            }
        }

        if (! is_array($value)) {
            return $value;
        }

        static::ksortRecursive($value);

        return $value;
    }

    /**
     * Sort array recursively.
     */
    protected static function ksortRecursive(&$array)
    {
        if (! is_array($array)) {
            return;
        }

        ksort($array);

        foreach ($array as &$value) {
            if (! is_array($value)) {
                continue;
            }

            static::ksortRecursive($value);
        }
    }
}
