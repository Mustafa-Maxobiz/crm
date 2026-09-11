<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('smrtphone_call_logs', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable()->after('called_at')->index();
            $table->timestamp('completed_at')->nullable()->after('started_at')->index();
            $table->unsignedInteger('duration_seconds')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('smrtphone_call_logs', function (Blueprint $table) {
            $table->dropColumn([
                'started_at',
                'completed_at',
                'duration_seconds',
            ]);
        });
    }
};
