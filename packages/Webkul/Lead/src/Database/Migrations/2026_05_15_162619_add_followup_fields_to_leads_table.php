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
        Schema::table('leads', function (Blueprint $table) {
            $table->datetime('next_followup_date')->nullable()->after('expected_close_date');
            $table->integer('followup_count')->default(0)->after('next_followup_date');
            $table->datetime('last_followup_date')->nullable()->after('followup_count');
            $table->text('followup_notes')->nullable()->after('last_followup_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['next_followup_date', 'followup_count', 'last_followup_date', 'followup_notes']);
        });
    }
};
