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

        $sourceRole = DB::table('roles')
            ->whereIn(DB::raw('LOWER(TRIM(name))'), [
                'bd executive',
                'bd exective',
                'bde',
                'bde executive',
                'bde exective',
                'business development executive',
            ])
            ->orderByRaw("CASE WHEN LOWER(TRIM(name)) = 'bd executive' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->first();

        if (! $sourceRole) {
            return;
        }

        $demoRole = DB::table('roles')
            ->whereRaw('LOWER(TRIM(name)) = ?', ['bde demo'])
            ->first();

        if (! $demoRole) {
            $demoRoleId = DB::table('roles')->insertGetId([
                'name'            => 'BDE Demo',
                'description'     => 'BDE demo role with direct Negotiation/Won/Lost stage access while retaining the standard BDE Meeting handoff workflow.',
                'permission_type' => $sourceRole->permission_type,
                'permissions'     => $sourceRole->permissions,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        } else {
            $demoRoleId = (int) $demoRole->id;

            DB::table('roles')
                ->where('id', $demoRoleId)
                ->update([
                    'permission_type' => $sourceRole->permission_type,
                    'permissions'     => $sourceRole->permissions,
                    'updated_at'      => now(),
                ]);
        }

        $this->copyPivotRows('role_source', $sourceRole->id, $demoRoleId, ['lead_source_id']);
        $this->copyPivotRows('role_organization', $sourceRole->id, $demoRoleId, ['organization_id']);
        $this->copyPivotRows('role_pipeline_stage', $sourceRole->id, $demoRoleId, ['lead_pipeline_stage_id', 'is_shared']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $roleId = DB::table('roles')
            ->whereRaw('LOWER(TRIM(name)) = ?', ['bde demo'])
            ->value('id');

        if (! $roleId) {
            return;
        }

        foreach (['role_source', 'role_organization', 'role_pipeline_stage', 'user_roles'] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->where('role_id', $roleId)->delete();
            }
        }

        DB::table('roles')->where('id', $roleId)->delete();
    }

    protected function copyPivotRows(string $table, int $fromRoleId, int $toRoleId, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $rows = DB::table($table)
            ->where('role_id', $fromRoleId)
            ->get();

        foreach ($rows as $row) {
            $attributes = ['role_id' => $toRoleId];

            foreach ($columns as $column) {
                $attributes[$column] = $row->{$column};
            }

            $values = [];

            if (Schema::hasColumn($table, 'created_at')) {
                $values['created_at'] = now();
            }

            if (Schema::hasColumn($table, 'updated_at')) {
                $values['updated_at'] = now();
            }

            DB::table($table)->updateOrInsert($attributes, $values);
        }
    }
};
