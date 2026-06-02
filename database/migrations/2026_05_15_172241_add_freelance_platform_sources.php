<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = Carbon::now();

        // Check if sources already exist before inserting
        $existingSources = DB::table('lead_sources')
            ->whereIn('name', ['Upwork', 'Fiverr', 'Freelancer'])
            ->pluck('name')
            ->toArray();

        $sourcesToInsert = [];

        if (!in_array('Upwork', $existingSources)) {
            $sourcesToInsert[] = [
                'name'       => 'Upwork',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!in_array('Fiverr', $existingSources)) {
            $sourcesToInsert[] = [
                'name'       => 'Fiverr',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!in_array('Freelancer', $existingSources)) {
            $sourcesToInsert[] = [
                'name'       => 'Freelancer',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($sourcesToInsert)) {
            DB::table('lead_sources')->insert($sourcesToInsert);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('lead_sources')
            ->whereIn('name', ['Upwork', 'Fiverr', 'Freelancer'])
            ->delete();
    }
};
