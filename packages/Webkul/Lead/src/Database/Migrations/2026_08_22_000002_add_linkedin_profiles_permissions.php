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

        $adminRoleIds = DB::table('roles')
            ->where('permission_type', 'all')
            ->pluck('id');

        foreach ($adminRoleIds as $roleId) {
            $this->grantPermissions((int) $roleId, [
                'settings.other_settings.linkedin_profiles',
                'settings.other_settings.linkedin_profiles.create',
                'settings.other_settings.linkedin_profiles.edit',
                'settings.other_settings.linkedin_profiles.delete',
            ]);
        }
    }

    public function down(): void
    {
        // Permissions are managed through the ACL config; no destructive rollback.
    }

    /**
     * @param  array<int, string>  $permissions
     */
    protected function grantPermissions(int $roleId, array $permissions): void
    {
        $role = DB::table('roles')->where('id', $roleId)->first();

        if (! $role) {
            return;
        }

        $existing = json_decode((string) ($role->permissions ?? '[]'), true) ?: [];

        $merged = array_values(array_unique(array_merge($existing, $permissions)));

        DB::table('roles')
            ->where('id', $roleId)
            ->update(['permissions' => json_encode($merged)]);
    }
};
