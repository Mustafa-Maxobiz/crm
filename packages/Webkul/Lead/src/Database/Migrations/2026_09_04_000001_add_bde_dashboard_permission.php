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

        DB::table('roles')
            ->where('permission_type', 'custom')
            ->whereIn(DB::raw('LOWER(TRIM(name))'), $this->bdeRoleNames())
            ->orderBy('id')
            ->get(['id', 'permissions'])
            ->each(function ($role) {
                $permissions = json_decode($role->permissions ?? '[]', true);

                if (! is_array($permissions)) {
                    $permissions = [];
                }

                $permissions[] = 'bde_dashboard';

                DB::table('roles')
                    ->where('id', $role->id)
                    ->update([
                        'permissions' => json_encode(array_values(array_unique($permissions))),
                        'updated_at'  => now(),
                    ]);
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        DB::table('roles')
            ->where('permission_type', 'custom')
            ->whereIn(DB::raw('LOWER(TRIM(name))'), $this->bdeRoleNames())
            ->orderBy('id')
            ->get(['id', 'permissions'])
            ->each(function ($role) {
                $permissions = json_decode($role->permissions ?? '[]', true);

                if (! is_array($permissions)) {
                    return;
                }

                DB::table('roles')
                    ->where('id', $role->id)
                    ->update([
                        'permissions' => json_encode(array_values(array_filter(
                            $permissions,
                            fn ($permission) => $permission !== 'bde_dashboard'
                        ))),
                        'updated_at'  => now(),
                    ]);
            });
    }

    protected function bdeRoleNames(): array
    {
        return [
            'bd',
            'bde',
            'bd executive',
            'bd exective',
            'bde executive',
            'bde exective',
            'business development executive',
        ];
    }
};
