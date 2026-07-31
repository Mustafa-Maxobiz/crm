<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\Tag\StaticTags;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $userId = DB::table('users')->orderBy('id')->value('id') ?: 1;
        $now = now();

        $renameMap = [
            'cold call'  => 'Cold Lead',
            'cold calls' => 'Cold Lead',
            'warm leads' => 'Warm Lead',
            'warm lead'  => 'Warm Lead',
            'not answer' => 'Not Answered',
            'not answered' => 'Not Answered',
            'do not call'=> 'Do Not Call',
            'incorrect info' => 'Incorrect Info',
        ];

        foreach (DB::table('tags')->get(['id', 'name']) as $tag) {
            $normalized = strtolower(trim((string) $tag->name));

            if (! isset($renameMap[$normalized])) {
                continue;
            }

            $targetName = $renameMap[$normalized];

            if ($tag->name === $targetName) {
                continue;
            }

            $existingTargetId = DB::table('tags')
                ->where('name', $targetName)
                ->where('id', '!=', $tag->id)
                ->value('id');

            if ($existingTargetId) {
                $this->reassignTagReferences((int) $tag->id, (int) $existingTargetId);
                DB::table('tags')->where('id', $tag->id)->delete();

                continue;
            }

            DB::table('tags')
                ->where('id', $tag->id)
                ->update([
                    'name'       => $targetName,
                    'updated_at' => $now,
                ]);
        }

        foreach (StaticTags::definitions() as $definition) {
            $exists = DB::table('tags')
                ->whereRaw('LOWER(TRIM(name)) = ?', [strtolower($definition['name'])])
                ->exists();

            if ($exists) {
                DB::table('tags')
                    ->whereRaw('LOWER(TRIM(name)) = ?', [strtolower($definition['name'])])
                    ->update([
                        'name'       => $definition['name'],
                        'color'      => $definition['color'],
                        'updated_at' => $now,
                    ]);

                continue;
            }

            DB::table('tags')->insert([
                'name'       => $definition['name'],
                'color'      => $definition['color'],
                'user_id'    => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $allowedNames = array_map('strtolower', StaticTags::names());

        $obsoleteIds = DB::table('tags')
            ->get(['id', 'name'])
            ->filter(fn ($tag) => ! in_array(strtolower(trim((string) $tag->name)), $allowedNames, true))
            ->pluck('id')
            ->all();

        if ($obsoleteIds) {
            DB::table('tags')->whereIn('id', $obsoleteIds)->delete();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally left blank: restoring deleted tags is not reversible.
    }

    protected function reassignTagReferences(int $fromTagId, int $toTagId): void
    {
        $pivots = [
            'lead_tags'      => 'lead_id',
            'person_tags'    => 'person_id',
            'product_tags'   => 'product_id',
            'warehouse_tags' => 'warehouse_id',
            'email_tags'     => 'email_id',
        ];

        foreach ($pivots as $table => $entityColumn) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $rows = DB::table($table)->where('tag_id', $fromTagId)->get();

            foreach ($rows as $row) {
                $alreadyAssigned = DB::table($table)
                    ->where('tag_id', $toTagId)
                    ->where($entityColumn, $row->{$entityColumn})
                    ->exists();

                if (! $alreadyAssigned) {
                    DB::table($table)
                        ->where('tag_id', $fromTagId)
                        ->where($entityColumn, $row->{$entityColumn})
                        ->update(['tag_id' => $toTagId]);
                } else {
                    DB::table($table)
                        ->where('tag_id', $fromTagId)
                        ->where($entityColumn, $row->{$entityColumn})
                        ->delete();
                }
            }
        }
    }
};
