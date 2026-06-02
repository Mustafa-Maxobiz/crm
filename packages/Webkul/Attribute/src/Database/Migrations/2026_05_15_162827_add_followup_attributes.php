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

        // Check if next_followup_date attribute already exists
        $existingAttribute = DB::table('attributes')
            ->where('code', 'next_followup_date')
            ->where('entity_type', 'leads')
            ->first();

        // Insert next_followup_date attribute only if it doesn't exist
        if (!$existingAttribute) {
            DB::table('attributes')->insert([
                'code'            => 'next_followup_date',
                'name'            => 'Next Follow-up Date',
                'type'            => 'datetime',
                'entity_type'     => 'leads',
                'lookup_type'     => null,
                'validation'      => null,
                'sort_order'      => '8.5',
                'is_required'     => '0',
                'is_unique'       => '0',
                'quick_add'       => '1',
                'is_user_defined' => '0',
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Delete the attribute
        DB::table('attributes')
            ->where('code', 'next_followup_date')
            ->where('entity_type', 'leads')
            ->delete();
    }
};
