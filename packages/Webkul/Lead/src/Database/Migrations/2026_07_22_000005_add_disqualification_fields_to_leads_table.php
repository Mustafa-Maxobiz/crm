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
            if (! Schema::hasColumn('leads', 'lead_disqualification_reason')) {
                $table->string('lead_disqualification_reason')->nullable()->after('followup_notes');
            }

            if (! Schema::hasColumn('leads', 'lead_disqualified_at')) {
                $table->timestamp('lead_disqualified_at')->nullable()->after('lead_disqualification_reason');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (Schema::hasColumn('leads', 'lead_disqualified_at')) {
                $table->dropColumn('lead_disqualified_at');
            }

            if (Schema::hasColumn('leads', 'lead_disqualification_reason')) {
                $table->dropColumn('lead_disqualification_reason');
            }
        });
    }
};
