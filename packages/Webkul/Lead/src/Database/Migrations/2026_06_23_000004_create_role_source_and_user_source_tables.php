<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_source', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('role_id');
            $table->unsignedInteger('lead_source_id');
            $table->timestamps();

            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->foreign('lead_source_id')->references('id')->on('lead_sources')->cascadeOnDelete();
            $table->unique(['role_id', 'lead_source_id']);
        });

        Schema::create('user_source', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('lead_source_id');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('lead_source_id')->references('id')->on('lead_sources')->cascadeOnDelete();
            $table->unique(['user_id', 'lead_source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_source');
        Schema::dropIfExists('role_source');
    }
};
