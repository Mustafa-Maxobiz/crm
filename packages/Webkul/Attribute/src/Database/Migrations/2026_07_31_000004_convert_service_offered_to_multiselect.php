<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $attribute = DB::table('attributes')
            ->where('code', 'service_offered')
            ->where('entity_type', 'leads')
            ->first();

        if (! $attribute) {
            return;
        }

        DB::table('attributes')
            ->where('id', $attribute->id)
            ->update([
                'type'       => 'multiselect',
                'name'       => 'Services Offered',
                'updated_at' => now(),
            ]);

        $values = DB::table('attribute_values')
            ->where('attribute_id', $attribute->id)
            ->where('entity_type', 'leads')
            ->whereNotNull('integer_value')
            ->get(['id', 'integer_value', 'text_value']);

        foreach ($values as $value) {
            $textValue = filled($value->text_value)
                ? $value->text_value
                : (string) $value->integer_value;

            DB::table('attribute_values')
                ->where('id', $value->id)
                ->update([
                    'text_value'    => $textValue,
                    'integer_value' => null,
                ]);
        }

        $this->grantSdrCreatePermission();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $attribute = DB::table('attributes')
            ->where('code', 'service_offered')
            ->where('entity_type', 'leads')
            ->first();

        if (! $attribute) {
            return;
        }

        $values = DB::table('attribute_values')
            ->where('attribute_id', $attribute->id)
            ->where('entity_type', 'leads')
            ->whereNotNull('text_value')
            ->get(['id', 'text_value']);

        foreach ($values as $value) {
            $ids = array_values(array_filter(array_map('intval', explode(',', (string) $value->text_value))));
            $firstId = $ids[0] ?? null;

            DB::table('attribute_values')
                ->where('id', $value->id)
                ->update([
                    'integer_value' => $firstId,
                    'text_value'    => null,
                ]);
        }

        DB::table('attributes')
            ->where('id', $attribute->id)
            ->update([
                'type'       => 'select',
                'name'       => 'Service Offered',
                'updated_at' => now(),
            ]);
    }

    /**
     * Ensure SDR custom roles can create service offered options.
     */
    protected function grantSdrCreatePermission(): void
    {
        $permissionsToAdd = [
            'settings.lead',
            'settings.lead.services_offered',
            'settings.lead.services_offered.create',
        ];

        $roles = DB::table('roles')
            ->whereRaw('LOWER(name) = ?', ['sdr'])
            ->get(['id', 'permission_type', 'permissions']);

        foreach ($roles as $role) {
            if ($role->permission_type === 'all') {
                continue;
            }

            $permissions = json_decode($role->permissions ?? '[]', true);

            if (! is_array($permissions)) {
                $permissions = [];
            }

            $merged = array_values(array_unique(array_merge($permissions, $permissionsToAdd)));

            DB::table('roles')
                ->where('id', $role->id)
                ->update([
                    'permissions' => json_encode($merged),
                    'updated_at'  => now(),
                ]);
        }
    }
};
