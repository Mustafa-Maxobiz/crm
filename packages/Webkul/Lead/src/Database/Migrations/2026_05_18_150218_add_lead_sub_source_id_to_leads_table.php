<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->unsignedInteger('lead_sub_source_id')->nullable()->after('lead_source_id');
            
            $table->foreign('lead_sub_source_id')
                  ->references('id')
                  ->on('lead_sources')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['lead_sub_source_id']);
            $table->dropColumn('lead_sub_source_id');
        });
    }
};
