<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        // Check if attributes already exist
        $sourceSubTypeExists = DB::table('attributes')
            ->where('code', 'source_sub_type')
            ->where('entity_type', 'leads')
            ->exists();

        $sourceLinkExists = DB::table('attributes')
            ->where('code', 'source_link')
            ->where('entity_type', 'leads')
            ->exists();

        // Add Source Sub-Type attribute if it doesn't exist
        if (!$sourceSubTypeExists) {
            DB::table('attributes')->insert([
                'code'          => 'source_sub_type',
                'name'          => 'Source Sub-Type',
                'type'          => 'select',
                'entity_type'   => 'leads',
                'lookup_type'   => NULL,
                'validation'    => NULL,
                'sort_order'    => 11,
                'is_required'   => 0,
                'is_unique'     => 0,
                'quick_add'     => 1,
                'is_user_defined' => 0,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        }

        // Add Source Link attribute if it doesn't exist
        if (!$sourceLinkExists) {
            DB::table('attributes')->insert([
                'code'          => 'source_link',
                'name'          => 'Source Link',
                'type'          => 'text',
                'entity_type'   => 'leads',
                'lookup_type'   => NULL,
                'validation'    => 'url',
                'sort_order'    => 12,
                'is_required'   => 0,
                'is_unique'     => 0,
                'quick_add'     => 1,
                'is_user_defined' => 0,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        }

        // Get the source_sub_type attribute ID
        $sourceSubTypeAttribute = DB::table('attributes')
            ->where('code', 'source_sub_type')
            ->where('entity_type', 'leads')
            ->first();

        if ($sourceSubTypeAttribute) {
            // Check if options already exist
            $optionsExist = DB::table('attribute_options')
                ->where('attribute_id', $sourceSubTypeAttribute->id)
                ->exists();

            if (!$optionsExist) {
                // Add options for source sub-type
                DB::table('attribute_options')->insert([
                    [
                        'attribute_id' => $sourceSubTypeAttribute->id,
                        'name'         => 'Invitation',
                        'sort_order'   => 1,
                    ],
                    [
                        'attribute_id' => $sourceSubTypeAttribute->id,
                        'name'         => 'Bid',
                        'sort_order'   => 2,
                    ],
                    [
                        'attribute_id' => $sourceSubTypeAttribute->id,
                        'name'         => 'Direct Client',
                        'sort_order'   => 3,
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
        // Get attribute IDs
        $attributes = DB::table('attributes')
            ->whereIn('code', ['source_sub_type', 'source_link'])
            ->where('entity_type', 'leads')
            ->pluck('id');

        // Delete attribute options
        DB::table('attribute_options')
            ->whereIn('attribute_id', $attributes)
            ->delete();

        // Delete attribute values
        DB::table('attribute_values')
            ->whereIn('attribute_id', $attributes)
            ->delete();

        // Delete attributes
        DB::table('attributes')
            ->whereIn('code', ['source_sub_type', 'source_link'])
            ->where('entity_type', 'leads')
            ->delete();
    }
};
