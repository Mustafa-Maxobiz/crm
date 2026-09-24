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

        $role = DB::table('roles')
            ->whereIn(DB::raw('LOWER(TRIM(name))'), $this->roleNames())
            ->first(['id', 'permissions']);

        if (! $role) {
            DB::table('roles')->insert([
                'name'            => 'Data Scraper',
                'description'     => 'Data extraction role for Data Scraper lead create and import workflows.',
                'permission_type' => 'custom',
                'permissions'     => json_encode($this->permissions()),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            return;
        }

        $permissions = json_decode($role->permissions ?? '[]', true);

        if (! is_array($permissions)) {
            $permissions = [];
        }

        DB::table('roles')
            ->where('id', $role->id)
            ->update([
                'permissions' => json_encode(array_values(array_unique(array_merge($permissions, $this->permissions())))),
                'updated_at'  => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $roles = DB::table('roles')
            ->whereIn(DB::raw('LOWER(TRIM(name))'), $this->roleNames())
            ->get(['id', 'permissions', 'description']);

        foreach ($roles as $role) {
            if ($role->description === 'Data extraction role for Data Scraper lead create and import workflows.') {
                DB::table('roles')->where('id', $role->id)->delete();

                continue;
            }

            $permissions = json_decode($role->permissions ?? '[]', true);

            if (! is_array($permissions)) {
                continue;
            }

            DB::table('roles')
                ->where('id', $role->id)
                ->update([
                    'permissions' => json_encode(array_values(array_diff($permissions, $this->permissions()))),
                    'updated_at'  => now(),
                ]);
        }
    }

    protected function roleNames(): array
    {
        return [
            'data scraper',
            'de data scraper',
            'de_data_scraper',
            'data-scraper',
            'de-data-scraper',
        ];
    }

    protected function permissions(): array
    {
        return [
            'de_data_scraper_leads',
            'de_data_scraper_leads.create',
            'de_data_scraper_leads.view',
            'de_data_scraper_leads.edit',
            'de_data_scraper_leads.delete',
            'de_data_scraper_leads.import',
            'de_data_scraper_leads.disqualified',
        ];
    }
};
