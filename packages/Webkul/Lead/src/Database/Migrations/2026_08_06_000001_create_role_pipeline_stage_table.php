<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_pipeline_stage', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('role_id');
            $table->unsignedInteger('lead_pipeline_stage_id');
            $table->boolean('is_shared')->default(false);
            $table->timestamps();

            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->foreign('lead_pipeline_stage_id')->references('id')->on('lead_pipeline_stages')->cascadeOnDelete();
            $table->unique(['role_id', 'lead_pipeline_stage_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_pipeline_stage');
    }
};
