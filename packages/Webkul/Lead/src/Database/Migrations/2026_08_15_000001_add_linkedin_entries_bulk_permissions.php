<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roles = DB::table('roles')
            ->where('permission_type', '!=', 'all')
            ->get(['id', 'permissions']);

        foreach ($roles as $role) {
            $permissions = json_decode($role->permissions ?? '[]', true);

            if (! is_array($permissions)) {
                $permissions = [];
            }

            $toAdd = [];

            if (in_array('linkedin_entries.create', $permissions, true)) {
                $toAdd[] = 'linkedin_entries.bulk_create';
            }

            if (in_array('linkedin_entries.edit', $permissions, true)) {
                $toAdd[] = 'linkedin_entries.bulk_change_status';
            }

            if (empty($toAdd)) {
                continue;
            }

            DB::table('roles')
                ->where('id', $role->id)
                ->update([
                    'permissions' => json_encode(array_values(array_unique(array_merge($permissions, $toAdd)))),
                    'updated_at'  => now(),
                ]);
        }
    }

    public function down(): void
    {
        $roles = DB::table('roles')
            ->where('permission_type', '!=', 'all')
            ->get(['id', 'permissions']);

        foreach ($roles as $role) {
            $permissions = json_decode($role->permissions ?? '[]', true);

            if (! is_array($permissions)) {
                continue;
            }

            $filtered = array_values(array_filter(
                $permissions,
                fn ($permission) => ! in_array($permission, [
                    'linkedin_entries.bulk_create',
                    'linkedin_entries.bulk_change_status',
                ], true)
            ));

            DB::table('roles')
                ->where('id', $role->id)
                ->update([
                    'permissions' => json_encode($filtered),
                    'updated_at'  => now(),
                ]);
        }
    }
};
