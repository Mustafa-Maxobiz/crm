<?php

namespace Webkul\User\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Webkul\Lead\Models\SourceProxy;
use Webkul\Contact\Models\OrganizationProxy;
use Webkul\User\Contracts\User as UserContract;

class User extends Authenticatable implements UserContract
{
    use HasApiTokens, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'image',
        'password',
        'api_token',
        'role_id',
        'status',
        'view_permission',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'api_token',
        'remember_token',
    ];

    /**
     * Get image url for the product image.
     */
    public function image_url()
    {
        if (! $this->image) {
            return;
        }

        return Storage::url($this->image);
    }

    /**
     * Get image url for the product image.
     */
    public function getImageUrlAttribute()
    {
        return $this->image_url();
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $array = parent::toArray();

        $array['image_url'] = $this->image_url;

        return $array;
    }

    /**
     * Primary/compat role FK (users.role_id). Prefer roles() + ActiveRoleService for multi-role.
     */
    public function role()
    {
        return $this->belongsTo(RoleProxy::modelClass());
    }

    /**
     * All roles assigned to the user.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(RoleProxy::modelClass(), 'user_roles', 'user_id', 'role_id')
            ->withTimestamps();
    }

    /**
     * Whether the user is assigned the given role name (case-insensitive).
     */
    public function hasRole(string $roleName): bool
    {
        $roleName = strtolower(trim($roleName));

        $this->loadMissing('roles');

        if ($this->roles->isNotEmpty()) {
            return $this->roles->contains(
                fn ($role) => strtolower(trim((string) $role->name)) === $roleName
            );
        }

        return strtolower(trim((string) $this->role?->name)) === $roleName;
    }

    /**
     * @param  array<int, string>  $roleNames
     */
    public function hasAnyRole(array $roleNames): bool
    {
        foreach ($roleNames as $roleName) {
            if ($this->hasRole((string) $roleName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The groups that belong to the user.
     */
    public function groups()
    {
        return $this->belongsToMany(GroupProxy::modelClass(), 'user_groups');
    }

    public function sources(): BelongsToMany
    {
        return $this->belongsToMany(SourceProxy::modelClass(), 'user_source', 'user_id', 'lead_source_id');
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(OrganizationProxy::modelClass(), 'user_organization', 'user_id', 'organization_id')
            ->select(
                'organizations.id',
                'organizations.name',
                'organizations.address',
                'organizations.created_at',
                'organizations.updated_at',
                'organizations.user_id',
                'user_organization.id as pivot_id',
                'user_organization.user_id as pivot_user_id',
                'user_organization.organization_id as pivot_organization_id',
                'user_organization.created_at as pivot_created_at',
                'user_organization.updated_at as pivot_updated_at',
            );
    }

    /**
     * Checks if user has permission to perform certain action for the active role.
     *
     * @param  string  $permission
     * @return bool
     */
    public function hasPermission($permission)
    {
        $role = app(\Webkul\User\Services\ActiveRoleService::class)->getActiveRole($this)
            ?? $this->role;

        if (! $role) {
            return false;
        }

        if ($role->permission_type == 'custom' && ! $role->permissions) {
            return false;
        }

        if ($role->permission_type === 'all') {
            return true;
        }

        return in_array($permission, $role->permissions ?? [], true);
    }
}
