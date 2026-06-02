<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = Carbon::now();

        // Check if pricing_type attribute already exists
        $existingAttribute = DB::table('attributes')
            ->where('code', 'pricing_type')
            ->where('entity_type', 'leads')
            ->first();

        // Insert pricing_type attribute only if it doesn't exist
        if (!$existingAttribute) {
            DB::table('attributes')->insert([
                'code'            => 'pricing_type',
                'name'            => 'Pricing Type',
                'type'            => 'select',
                'entity_type'     => 'leads',
                'lookup_type'     => null,
                'validation'      => null,
                'sort_order'      => '3.5',
                'is_required'     => '1',
                'is_unique'       => '0',
                'quick_add'       => '1',
                'is_user_defined' => '0',
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
        }

        // Get the attribute ID
        $attributeId = DB::table('attributes')
            ->where('code', 'pricing_type')
            ->where('entity_type', 'leads')
            ->value('id');

        // Insert attribute options only if they don't exist
        if ($attributeId) {
            $existingOptions = DB::table('attribute_options')
                ->where('attribute_id', $attributeId)
                ->count();

            if ($existingOptions == 0) {
                DB::table('attribute_options')->insert([
                    [
                        'attribute_id' => $attributeId,
                        'name'         => 'Fixed Price',
                        'sort_order'   => 1,
                    ],
                    [
                        'attribute_id' => $attributeId,
                        'name'         => 'Hourly Rate',
                        'sort_order'   => 2,
                    ],
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Get the attribute ID
        $attributeId = DB::table('attributes')
            ->where('code', 'pricing_type')
            ->where('entity_type', 'leads')
            ->value('id');

        // Delete attribute options
        if ($attributeId) {
            DB::table('attribute_options')
                ->where('attribute_id', $attributeId)
                ->delete();
        }

        // Delete the attribute
        DB::table('attributes')
            ->where('code', 'pricing_type')
            ->where('entity_type', 'leads')
            ->delete();
    }
};
