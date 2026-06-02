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
        Schema::create('lead_source_parents', function (Blueprint $table) {
            $table->unsignedInteger('source_id');
            $table->unsignedInteger('parent_source_id');
            
            $table->foreign('source_id')
                  ->references('id')
                  ->on('lead_sources')
                  ->onDelete('cascade');
                  
            $table->foreign('parent_source_id')
                  ->references('id')
                  ->on('lead_sources')
                  ->onDelete('cascade');
                  
            $table->primary(['source_id', 'parent_source_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_source_parents');
    }
};
