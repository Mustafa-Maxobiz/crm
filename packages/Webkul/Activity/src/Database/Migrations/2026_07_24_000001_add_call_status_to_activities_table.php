<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->string('call_status')->default('scheduled')->after('is_done');
        });

        DB::table('activities')
            ->where('is_done', 1)
            ->update(['call_status' => 'done']);

        DB::table('activities')
            ->where('type', 'call')
            ->where('is_done', 0)
            ->where('schedule_from', '<=', now())
            ->update(['call_status' => 'not_answered']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn('call_status');
        });
    }
};
