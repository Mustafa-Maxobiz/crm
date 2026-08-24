<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_user', function (Blueprint $table) {
            $table->unsignedInteger('service_id');
            $table->unsignedInteger('user_id');
            $table->timestamps();

            $table->foreign('service_id')
                ->references('id')
                ->on('services')
                ->cascadeOnDelete();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->unique(['service_id', 'user_id']);
            $table->index('service_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_user');
    }
};
