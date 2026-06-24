<?php

namespace Webkul\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Webkul\Lead\Models\SourceProxy;
use Webkul\Contact\Models\OrganizationProxy;
use Webkul\User\Contracts\Role as RoleContract;

class Role extends Model implements RoleContract
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'description',
        'permission_type',
        'permissions',
    ];

    protected $casts = [
        'permissions' => 'array',
    ];

    /**
     * Get the users.
     */
    public function users()
    {
        return $this->hasMany(UserProxy::modelClass());
    }

    public function sources(): BelongsToMany
    {
        return $this->belongsToMany(SourceProxy::modelClass(), 'role_source', 'role_id', 'lead_source_id');
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(OrganizationProxy::modelClass(), 'role_organization', 'role_id', 'organization_id');
    }
}
