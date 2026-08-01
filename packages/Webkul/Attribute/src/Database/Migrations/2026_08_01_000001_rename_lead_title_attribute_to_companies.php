<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Lead "Company Name" field was stored/submitted as `title`.
     * Rename the attribute code to `companies` so create/edit payloads match the UI.
     * Native `leads.title` column is kept and mapped in LeadRepository.
     */
    public function up(): void
    {
        DB::table('attributes')
            ->where('entity_type', 'leads')
            ->where('code', 'title')
            ->update([
                'code' => 'companies',
                'name' => 'Company Name',
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('attributes')
            ->where('entity_type', 'leads')
            ->where('code', 'companies')
            ->update([
                'code' => 'title',
                'name' => 'Title',
            ]);
    }
};
