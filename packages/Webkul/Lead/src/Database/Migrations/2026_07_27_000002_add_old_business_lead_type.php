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
        $exists = DB::table('lead_types')
            ->whereRaw('LOWER(name) = ?', ['old business'])
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('lead_types')->insert([
            'name'       => 'Old Business',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('lead_types')
            ->whereRaw('LOWER(name) = ?', ['old business'])
            ->delete();
    }
};
