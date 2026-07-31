<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smrtphone_call_logs', function (Blueprint $table) {
            $table->id();
            $table->string('external_call_id')->nullable()->unique();
            $table->string('event')->nullable()->index();
            $table->string('direction')->nullable()->index();
            $table->string('from_number')->nullable()->index();
            $table->string('to_number')->nullable()->index();
            $table->string('contact_phone')->nullable()->index();
            $table->string('contact_name')->nullable();
            $table->string('caller_id_name')->nullable();
            $table->string('user_name')->nullable()->index();
            $table->string('device')->nullable();
            $table->string('call_status')->nullable()->index();
            $table->string('call_outcome')->nullable()->index();
            $table->text('call_notes')->nullable();
            $table->string('recording_url')->nullable();
            $table->text('ai_summary')->nullable();
            $table->json('ai_transcript')->nullable();
            $table->json('ai_keywords')->nullable();
            $table->boolean('is_dialer')->default(false)->index();
            $table->unsignedInteger('person_id')->nullable();
            $table->unsignedInteger('lead_id')->nullable();
            $table->unsignedInteger('activity_id')->nullable();
            $table->timestamp('called_at')->nullable()->index();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->foreign('person_id')->references('id')->on('persons')->nullOnDelete();
            $table->foreign('lead_id')->references('id')->on('leads')->nullOnDelete();
            $table->foreign('activity_id')->references('id')->on('activities')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smrtphone_call_logs');
    }
};
