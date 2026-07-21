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
            ->where('code', 'organization_id')
            ->where('entity_type', 'persons')
            ->where('name', 'Organization')
            ->update(['name' => 'Company']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('attributes')
            ->where('code', 'organization_id')
            ->where('entity_type', 'persons')
            ->where('name', 'Company')
            ->update(['name' => 'Organization']);
    }
};
