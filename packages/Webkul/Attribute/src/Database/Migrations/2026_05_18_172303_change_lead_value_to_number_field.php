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
        // Change lead_value attribute from price to text type with numeric validation
        DB::table('attributes')
            ->where('code', 'lead_value')
            ->where('entity_type', 'leads')
            ->update([
                'type' => 'text',
                'validation' => 'numeric',
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to price type
        DB::table('attributes')
            ->where('code', 'lead_value')
            ->where('entity_type', 'leads')
            ->update([
                'type' => 'price',
                'validation' => 'decimal',
            ]);
    }
};
