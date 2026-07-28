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
        $attributeId = DB::table('attributes')
            ->where('code', 'pricing_type')
            ->where('entity_type', 'leads')
            ->value('id');

        if (! $attributeId) {
            return;
        }

        $exists = DB::table('attribute_options')
            ->where('attribute_id', $attributeId)
            ->whereRaw('LOWER(name) = ?', ['optional'])
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('attribute_options')->insert([
            'attribute_id' => $attributeId,
            'name'         => 'Optional',
            'sort_order'   => 3,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $attributeId = DB::table('attributes')
            ->where('code', 'pricing_type')
            ->where('entity_type', 'leads')
            ->value('id');

        if (! $attributeId) {
            return;
        }

        DB::table('attribute_options')
            ->where('attribute_id', $attributeId)
            ->whereRaw('LOWER(name) = ?', ['optional'])
            ->delete();
    }
};
