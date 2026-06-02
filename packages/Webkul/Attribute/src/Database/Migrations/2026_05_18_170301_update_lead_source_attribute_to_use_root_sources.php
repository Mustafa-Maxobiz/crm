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
        // Update lead_source_id attribute to use lead_root_sources lookup
        DB::table('attributes')
            ->where('code', 'lead_source_id')
            ->where('entity_type', 'leads')
            ->update([
                'lookup_type' => 'lead_root_sources',
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to lead_sources
        DB::table('attributes')
            ->where('code', 'lead_source_id')
            ->where('entity_type', 'leads')
            ->update([
                'lookup_type' => 'lead_sources',
            ]);
    }
};
