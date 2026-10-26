<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('followup_rule_groups')) {
            return;
        }

        if (Schema::hasColumn('followup_rule_groups', 'priority')) {
            Schema::table('followup_rule_groups', function (Blueprint $table) {
                $table->dropIndex('followup_rule_groups_is_active_priority_index');
                $table->dropColumn('priority');
            });
        }

        if (Schema::hasColumn('followup_rule_groups', 'max_days')) {
            DB::table('followup_rule_groups')
                ->orderBy('id')
                ->each(function ($group) {
                    $steps = DB::table('followup_rule_schedules')
                        ->where('followup_rule_group_id', $group->id)
                        ->get();

                    DB::table('followup_rule_groups')
                        ->where('id', $group->id)
                        ->update([
                            'max_days'   => $this->calculateMaxDays($steps),
                            'updated_at' => now(),
                        ]);
                });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('followup_rule_groups') || Schema::hasColumn('followup_rule_groups', 'priority')) {
            return;
        }

        Schema::table('followup_rule_groups', function (Blueprint $table) {
            $table->integer('priority')->default(0)->after('name');
            $table->index(['is_active', 'priority']);
        });
    }

    protected function calculateMaxDays($steps): int
    {
        $minutes = 0;

        foreach ($steps as $step) {
            $factor = match ((string) $step->delay_unit) {
                'minutes' => 1,
                'hours'   => 60,
                'days'    => 1440,
                'weeks'   => 10080,
                'months'  => 43200,
                default   => 60,
            };

            $minutes += max(1, (int) $step->delay_value)
                * max(1, (int) $step->frequency)
                * $factor;
        }

        return max(1, (int) ceil($minutes / 1440));
    }
};
