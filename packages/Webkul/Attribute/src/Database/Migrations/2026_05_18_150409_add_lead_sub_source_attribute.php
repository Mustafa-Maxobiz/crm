<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = Carbon::now();
        
        // Check if attribute already exists
        $exists = DB::table('attributes')->where('code', 'lead_sub_source_id')->exists();
        
        if (!$exists) {
            DB::table('attributes')->insert([
                'code'          => 'lead_sub_source_id',
                'name'          => 'Sub Source',
                'type'          => 'select',
                'entity_type'   => 'leads',
                'lookup_type'   => 'lead_sources',
                'validation'    => null,
                'sort_order'    => 7,
                'is_required'   => 0,
                'is_unique'     => 0,
                'quick_add'     => 1,
                'is_user_defined' => 0,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('attributes')->where('code', 'lead_sub_source_id')->delete();
    }
};
