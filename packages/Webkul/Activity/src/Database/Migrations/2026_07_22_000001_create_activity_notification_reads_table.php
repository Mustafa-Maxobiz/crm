<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('activity_notification_reads')) {
            return;
        }

        Schema::create('activity_notification_reads', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('activity_id')->unsigned();
            $table->integer('user_id')->unsigned();
            $table->string('reminder_type', 20);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->foreign('activity_id')->references('id')->on('activities')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['activity_id', 'user_id', 'reminder_type'], 'activity_notification_reads_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_notification_reads');
    }
};
