<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('lead_sources')->where('name', 'Meta Ads')->exists();

        if (! $exists) {
            $maxSort = DB::table('lead_sources')->whereNull('parent_id')->max('sort_order') ?? 0;

            DB::table('lead_sources')->insert([
                'parent_id'  => null,
                'name'       => 'Meta Ads',
                'sort_order' => $maxSort + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('lead_sources')->where('name', 'Meta Ads')->delete();
    }
};
