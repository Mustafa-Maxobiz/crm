<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('linkedin_entry', function (Blueprint $table) {
            $table->dropForeign(['lge_usr_id']);
            $table->dropIndex(['lge_usr_id', 'status']);
            $table->renameColumn('lge_usr_id', 'user_id');
        });

        Schema::table('linkedin_entry', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('linkedin_entry', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['user_id', 'status']);
            $table->renameColumn('user_id', 'lge_usr_id');
        });

        Schema::table('linkedin_entry', function (Blueprint $table) {
            $table->foreign('lge_usr_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['lge_usr_id', 'status']);
        });
    }
};
