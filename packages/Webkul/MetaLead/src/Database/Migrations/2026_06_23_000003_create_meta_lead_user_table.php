<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_lead_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('meta_lead_id');
            $table->unsignedInteger('user_id');
            $table->timestamps();

            $table->foreign('meta_lead_id')->references('id')->on('meta_leads')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['meta_lead_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_lead_user');
    }
};
