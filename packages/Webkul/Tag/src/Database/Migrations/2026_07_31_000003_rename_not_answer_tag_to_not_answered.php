<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tags')
            ->whereRaw('LOWER(TRIM(name)) = ?', ['not answer'])
            ->update([
                'name'       => 'Not Answered',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('tags')
            ->whereRaw('LOWER(TRIM(name)) = ?', ['not answered'])
            ->update([
                'name'       => 'Not Answer',
                'updated_at' => now(),
            ]);
    }
};
