<?php

namespace Webkul\User\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Webkul\User\Contracts\User as UserContract;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

class ActiveRoleService
{
    public const SESSION_KEY = 'active_role_id';

    protected static ?bool $userRolesTableExists = null;

    /**
     * Roles assigned to the user via user_roles (falls back to users.role_id).
     *
     * @return Collection<int, Role>
     */
    public function assignedRoles(?UserContract $user = null): Collection
    {
        $user = $this->resolveUser($user);

        if (! $user) {
            return collect();
        }

        if ($user instanceof User) {
            if ($user->relationLoaded('roles')) {
                if ($user->roles->isNotEmpty()) {
                    return $user->roles->values();
                }
            } else {
                try {
                    $user->load('roles');
                } catch (\Throwable) {
                    $user->setRelation('roles', collect());
                }

                if ($user->roles->isNotEmpty()) {
                    return $user->roles->values();
                }
            }

            if ($user->relationLoaded('role') && $user->role) {
                return collect([$user->role]);
            }
        }

        if (! empty($user->role_id)) {
            $role = Role::query()->find((int) $user->role_id);

            return $role ? collect([$role]) : collect();
        }

        return collect();
    }

    public function getActiveRoleId(?UserContract $user = null): ?int
    {
        $user = $this->resolveUser($user);

        if (! $user) {
            return null;
        }

        $sessionRoleId = (int) session(self::SESSION_KEY, 0);

        if ($sessionRoleId > 0 && $this->userHasRole($user, $sessionRoleId)) {
            return $sessionRoleId;
        }

        $assigned = $this->assignedRoles($user);

        if ($assigned->count() === 1) {
            $roleId = (int) $assigned->first()->id;
            $this->storeActiveRoleId($roleId);

            return $roleId;
        }

        if (! empty($user->role_id) && $this->userHasRole($user, (int) $user->role_id)) {
            return (int) $user->role_id;
        }

        return $assigned->isNotEmpty() ? (int) $assigned->first()->id : null;
    }

    public function getActiveRole(?UserContract $user = null): ?Role
    {
        $user = $this->resolveUser($user);
        $roleId = $this->getActiveRoleId($user);

        if (! $roleId) {
            return null;
        }

        if ($user instanceof User) {
            $user->loadMissing('roles');

            $fromAssigned = $user->roles->firstWhere('id', $roleId);

            if ($fromAssigned) {
                $this->bindActiveRoleRelation($user, $fromAssigned);

                return $fromAssigned;
            }

            if (
                $user->relationLoaded('role')
                && $user->role
                && (int) $user->role->id === (int) $roleId
            ) {
                return $user->role;
            }
        }

        $role = Role::query()
            ->with(['sources', 'organizations', 'pipelineStages'])
            ->find($roleId);

        if ($role && $user instanceof User) {
            $this->bindActiveRoleRelation($user, $role);
        }

        return $role;
    }

    /**
     * Apply the active role onto the user model for the current request.
     */
    public function applyActiveRole(?UserContract $user = null): ?Role
    {
        return $this->getActiveRole($user);
    }

    public function userHasRole(?UserContract $user, int $roleId): bool
    {
        $user = $this->resolveUser($user);

        if (! $user || $roleId <= 0) {
            return false;
        }

        if ($user instanceof User) {
            if ($user->relationLoaded('roles') && $user->roles->isNotEmpty()) {
                return $user->roles->contains(fn ($role) => (int) $role->id === $roleId);
            }

            if ($user->relationLoaded('role') && $user->role) {
                return (int) $user->role->id === $roleId;
            }
        }

        if ($this->userRolesTableExists()) {
            try {
                return DB::table('user_roles')
                    ->where('user_id', $user->id)
                    ->where('role_id', $roleId)
                    ->exists();
            } catch (\Throwable) {
                // Fall through for mocked DB test contexts.
            }
        }

        return (int) ($user->role_id ?? 0) === $roleId;
    }

    public function requiresRoleSelection(?UserContract $user = null): bool
    {
        $user = $this->resolveUser($user);

        if (! $user) {
            return false;
        }

        if ($this->assignedRoles($user)->count() <= 1) {
            return false;
        }

        $sessionRoleId = (int) session(self::SESSION_KEY, 0);

        return ! ($sessionRoleId > 0 && $this->userHasRole($user, $sessionRoleId));
    }

    /**
     * Activate a role after validating assignment. Regenerates the session.
     *
     * @throws ValidationException
     */
    public function activateRole(int $roleId, ?UserContract $user = null, bool $regenerateSession = true): Role
    {
        $user = $this->resolveUser($user);

        if (! $user) {
            throw ValidationException::withMessages([
                'role_id' => ['You must be authenticated to select a role.'],
            ]);
        }

        if (! $this->userHasRole($user, $roleId)) {
            throw ValidationException::withMessages([
                'role_id' => ['You are not assigned that role.'],
            ]);
        }

        $role = Role::query()->findOrFail($roleId);

        if ($regenerateSession) {
            request()->session()->regenerate();
        }

        $this->storeActiveRoleId((int) $role->id);

        if ($user instanceof User) {
            $this->bindActiveRoleRelation($user, $role);
        }

        return $role;
    }

    public function clearActiveRole(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public function storeActiveRoleId(int $roleId): void
    {
        session([self::SESSION_KEY => $roleId]);
    }

    /**
     * Dashboard route for the active role.
     */
    public function dashboardRouteForRole(?Role $role): string
    {
        if (! $role) {
            return 'admin.dashboard.index';
        }

        if ($role->permission_type === 'all') {
            return 'admin.dashboard.index';
        }

        $name = strtolower(trim((string) $role->name));

        return match (true) {
            $name === 'sdr' => 'admin.dashboard.sdr',
            $name === 'lge' => 'admin.dashboard.lge',
            in_array($name, ['lead', 'lead clouser', 'lead closer', 'lead closure'], true) => 'admin.dashboard.lead_clouser',
            default => 'admin.dashboard.index',
        };
    }

    protected function bindActiveRoleRelation(User $user, Role $role): void
    {
        $user->setRelation('role', $role);
    }

    protected function resolveUser(?UserContract $user = null): ?UserContract
    {
        return $user ?? auth()->guard('user')->user();
    }

    protected function userRolesTableExists(): bool
    {
        if (static::$userRolesTableExists === null) {
            static::$userRolesTableExists = Schema::hasTable('user_roles');
        }

        return static::$userRolesTableExists;
    }
}
