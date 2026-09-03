<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $roles = DB::table('roles')
            ->where('permission_type', 'custom')
            ->whereIn(DB::raw('LOWER(TRIM(name))'), [
                'bd',
                'bde',
                'bd executive',
                'bde executive',
                'business development executive',
            ])
            ->get(['id', 'permissions']);

        foreach ($roles as $role) {
            $permissions = json_decode($role->permissions ?? '[]', true);

            if (! is_array($permissions)) {
                $permissions = [];
            }

            $mappedPermissions = collect($permissions)
                ->reject(fn ($permission) => $permission === 'leads' || str_starts_with((string) $permission, 'leads.'))
                ->values()
                ->all();

            foreach ($this->permissionMap() as $oldPermission => $newPermission) {
                if (in_array($oldPermission, $permissions, true)) {
                    $mappedPermissions[] = $newPermission;
                }
            }

            DB::table('roles')
                ->where('id', $role->id)
                ->update([
                    'permissions' => json_encode(array_values(array_unique($mappedPermissions))),
                    'updated_at'  => now(),
                ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $roles = DB::table('roles')
            ->where('permission_type', 'custom')
            ->whereIn(DB::raw('LOWER(TRIM(name))'), [
                'bd',
                'bde',
                'bd executive',
                'bde executive',
                'business development executive',
            ])
            ->get(['id', 'permissions']);

        foreach ($roles as $role) {
            $permissions = json_decode($role->permissions ?? '[]', true);

            if (! is_array($permissions)) {
                continue;
            }

            $mappedPermissions = collect($permissions)
                ->reject(fn ($permission) => $permission === 'bde_leads' || str_starts_with((string) $permission, 'bde_leads.'))
                ->values()
                ->all();

            foreach (array_flip($this->permissionMap()) as $oldPermission => $newPermission) {
                if (in_array($oldPermission, $permissions, true)) {
                    $mappedPermissions[] = $newPermission;
                }
            }

            DB::table('roles')
                ->where('id', $role->id)
                ->update([
                    'permissions' => json_encode(array_values(array_unique($mappedPermissions))),
                    'updated_at'  => now(),
                ]);
        }
    }

    protected function permissionMap(): array
    {
        return [
            'leads'              => 'bde_leads',
            'leads.create'       => 'bde_leads.create',
            'leads.view'         => 'bde_leads.view',
            'leads.edit'         => 'bde_leads.edit',
            'leads.delete'       => 'bde_leads.delete',
            'leads.import'       => 'bde_leads.import',
            'leads.disqualified' => 'bde_leads.disqualified',
        ];
    }
};
