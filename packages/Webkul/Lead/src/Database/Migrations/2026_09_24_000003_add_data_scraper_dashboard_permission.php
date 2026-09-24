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
            ->whereIn(DB::raw('LOWER(TRIM(name))'), [
                'data scraper',
                'de data scraper',
                'de_data_scraper',
                'data-scraper',
                'de-data-scraper',
            ])
            ->get(['id', 'permissions']);

        foreach ($roles as $role) {
            $permissions = json_decode($role->permissions ?? '[]', true);

            if (! is_array($permissions)) {
                $permissions = [];
            }

            $permissions[] = 'data_scraper_dashboard';

            DB::table('roles')
                ->where('id', $role->id)
                ->update([
                    'permissions' => json_encode(array_values(array_unique($permissions))),
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
            ->whereIn(DB::raw('LOWER(TRIM(name))'), [
                'data scraper',
                'de data scraper',
                'de_data_scraper',
                'data-scraper',
                'de-data-scraper',
            ])
            ->get(['id', 'permissions']);

        foreach ($roles as $role) {
            $permissions = json_decode($role->permissions ?? '[]', true);

            if (! is_array($permissions)) {
                continue;
            }

            DB::table('roles')
                ->where('id', $role->id)
                ->update([
                    'permissions' => json_encode(array_values(array_diff($permissions, ['data_scraper_dashboard']))),
                    'updated_at'  => now(),
                ]);
        }
    }
};
