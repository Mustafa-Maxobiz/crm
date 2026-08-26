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
        Schema::table('leads', function (Blueprint $table) {
            $table->integer('lead_owner_id')->unsigned()->nullable()->after('user_id');

            $table->foreign('lead_owner_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        DB::table('leads')
            ->whereNull('lead_owner_id')
            ->update([
                'lead_owner_id' => DB::raw('user_id'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['lead_owner_id']);

            $table->dropColumn('lead_owner_id');
        });
    }
};
