<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Allowed business types.
     */
    private array $allowed = [
        'New Business',
        'Existing Business',
    ];

    public function up(): void
    {
        $now = now();

        foreach ($this->allowed as $name) {
            $exists = DB::table('lead_types')
                ->whereRaw('LOWER(name) = ?', [strtolower($name)])
                ->exists();

            if (! $exists) {
                DB::table('lead_types')->insert([
                    'name'       => $name,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('lead_types')
                    ->whereRaw('LOWER(name) = ?', [strtolower($name)])
                    ->update([
                        'name'       => $name,
                        'updated_at' => $now,
                    ]);
            }
        }

        $existingBusinessId = DB::table('lead_types')
            ->whereRaw('LOWER(name) = ?', ['existing business'])
            ->value('id');

        $allowedIds = DB::table('lead_types')
            ->whereIn(DB::raw('LOWER(name)'), ['new business', 'existing business'])
            ->pluck('id')
            ->all();

        if ($existingBusinessId) {
            DB::table('leads')
                ->whereNotNull('lead_type_id')
                ->whereNotIn('lead_type_id', $allowedIds)
                ->update([
                    'lead_type_id' => $existingBusinessId,
                    'updated_at'   => $now,
                ]);
        }

        DB::table('lead_types')
            ->whereNotIn(DB::raw('LOWER(name)'), ['new business', 'existing business'])
            ->delete();

        DB::table('attributes')
            ->where('code', 'lead_type_id')
            ->where('entity_type', 'leads')
            ->update([
                'name'       => 'Business Type',
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        $exists = DB::table('lead_types')
            ->whereRaw('LOWER(name) = ?', ['old business'])
            ->exists();

        if (! $exists) {
            DB::table('lead_types')->insert([
                'name'       => 'Old Business',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('attributes')
            ->where('code', 'lead_type_id')
            ->where('entity_type', 'leads')
            ->update([
                'name'       => 'Type',
                'updated_at' => now(),
            ]);
    }
};
