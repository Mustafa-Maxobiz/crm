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
        DB::table('core_config')
            ->where('code', 'general.settings.menu.contacts.organizations')
            ->where('value', 'Organizations')
            ->update(['value' => 'Companies']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('core_config')
            ->where('code', 'general.settings.menu.contacts.organizations')
            ->where('value', 'Companies')
            ->update(['value' => 'Organizations']);
    }
};
