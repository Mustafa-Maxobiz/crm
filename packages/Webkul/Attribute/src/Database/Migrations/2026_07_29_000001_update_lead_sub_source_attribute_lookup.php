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
        DB::table('attributes')
            ->where('code', 'lead_sub_source_id')
            ->where('entity_type', 'leads')
            ->update([
                'lookup_type' => 'lead_sub_sources',
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('attributes')
            ->where('code', 'lead_sub_source_id')
            ->where('entity_type', 'leads')
            ->update([
                'lookup_type' => 'lead_sources',
            ]);
    }
};
