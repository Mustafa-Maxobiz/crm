<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_leads', function (Blueprint $table) {
            $table->id();
            $table->string('leadgen_id')->unique();
            $table->unsignedInteger('lead_id')->nullable();
            $table->foreign('lead_id')->references('id')->on('leads')->nullOnDelete();
            $table->string('full_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('campaign_name')->nullable();
            $table->string('form_name')->nullable();
            $table->string('status')->default('new');
            $table->boolean('is_duplicate')->default(false);
            $table->unsignedBigInteger('duplicate_of_id')->nullable();
            $table->foreign('duplicate_of_id')->references('id')->on('meta_leads')->nullOnDelete();
            $table->json('raw_payload')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index('phone');
            $table->index('email');
            $table->index('status');
            $table->index('received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_leads');
    }
};
