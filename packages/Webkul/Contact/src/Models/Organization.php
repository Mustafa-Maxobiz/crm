<?php

namespace Webkul\Contact\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Webkul\Attribute\Traits\CustomAttribute;
use Webkul\Contact\Contracts\Organization as OrganizationContract;
use Webkul\User\Models\RoleProxy;
use Webkul\User\Models\UserProxy;

class Organization extends Model implements OrganizationContract
{
    use CustomAttribute;

    protected $casts = [
        'address' => 'array',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'address',
        'user_id',
    ];

    /**
     * Get persons.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function persons()
    {
        return $this->hasMany(PersonProxy::modelClass());
    }

    public function teams()
    {
        return $this->belongsToMany(TeamProxy::modelClass(), 'organization_team', 'organization_id', 'team_id');
    }

    /**
     * Get the user that owns the lead.
     */
    public function user()
    {
        return $this->belongsTo(UserProxy::modelClass());
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(RoleProxy::modelClass(), 'role_organization', 'organization_id', 'role_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(UserProxy::modelClass(), 'user_organization', 'organization_id', 'user_id');
    }
}
