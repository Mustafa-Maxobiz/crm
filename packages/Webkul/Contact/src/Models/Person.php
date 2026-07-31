<?php

namespace Webkul\Contact\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Webkul\Activity\Models\ActivityProxy;
use Webkul\Activity\Traits\LogsActivity;
use Webkul\Attribute\Traits\CustomAttribute;
use Webkul\Contact\Contracts\Person as PersonContract;
use Webkul\Contact\Database\Factories\PersonFactory;
use Webkul\Lead\Models\LeadProxy;
use Webkul\Tag\Models\TagProxy;
use Webkul\User\Models\UserProxy;

class Person extends Model implements PersonContract
{
    use HasFactory, LogsActivity;
    use CustomAttribute {
        getAttribute as getCustomEavAttribute;
        attributesToArray as customAttributesToArray;
    }

    /**
     * Table name.
     *
     * @var string
     */
    protected $table = 'persons';

    /**
     * Eager loading.
     *
     * @var string
     */
    protected $with = 'organization';

    /**
     * The attributes that are castable.
     *
     * @var array
     */
    protected $casts = [
        'emails'          => 'array',
        'contact_numbers' => 'array',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'emails',
        'contact_numbers',
        'job_title',
        'address_line',
        'city',
        'state',
        'country',
        'postcode',
        'timezone',
        'user_id',
        'organization_id',
        'unique_id',
    ];

    /**
     * Prefer dedicated address columns over EAV for `address`.
     */
    public function getAttribute($key)
    {
        if ($key === 'address') {
            return $this->composeAddress();
        }

        return $this->getCustomEavAttribute($key);
    }

    /**
     * Ensure API/array dumps use column-backed address.
     */
    public function attributesToArray()
    {
        $attributes = $this->customAttributesToArray();
        $attributes['address'] = $this->composeAddress();

        return $attributes;
    }

    /**
     * Compose legacy address array shape used by forms and APIs.
     */
    public function composeAddress(): ?array
    {
        $address = [
            'address'  => $this->attributes['address_line'] ?? null,
            'city'     => $this->attributes['city'] ?? null,
            'state'    => $this->attributes['state'] ?? null,
            'country'  => $this->attributes['country'] ?? null,
            'postcode' => $this->attributes['postcode'] ?? null,
        ];

        $hasValue = collect($address)->contains(fn ($part) => filled($part));

        return $hasValue ? $address : null;
    }

    /**
     * Get the user that owns the lead.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass());
    }

    /**
     * Get the organization that owns the person.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(OrganizationProxy::modelClass());
    }

    /**
     * Get the activities.
     */
    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(ActivityProxy::modelClass(), 'person_activities');
    }

    /**
     * The tags that belong to the person.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(TagProxy::modelClass(), 'person_tags');
    }

    /**
     * Get the leads for the person.
     */
    public function leads(): HasMany
    {
        return $this->hasMany(LeadProxy::modelClass(), 'person_id');
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): PersonFactory
    {
        return PersonFactory::new();
    }
}
