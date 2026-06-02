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
        // Make person name, email, and contact_numbers fields optional
        DB::table('attributes')
            ->where('entity_type', 'persons')
            ->whereIn('code', ['name', 'emails', 'contact_numbers'])
            ->update(['is_required' => 0]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to required
        DB::table('attributes')
            ->where('entity_type', 'persons')
            ->whereIn('code', ['name', 'emails', 'contact_numbers'])
            ->update(['is_required' => 1]);
    }
};
