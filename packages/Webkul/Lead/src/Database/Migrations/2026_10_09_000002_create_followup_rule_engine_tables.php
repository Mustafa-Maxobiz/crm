<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('followup_rule_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('max_days')->default(30);
            $table->string('exhaustion_behavior', 20)->default('repeat_last');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'priority']);
        });

        Schema::create('followup_rule_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('followup_rule_group_id')->constrained('followup_rule_groups')->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->unsignedInteger('delay_value');
            $table->string('delay_unit', 20);
            $table->unsignedInteger('frequency')->default(1);
            $table->timestamps();

            $table->unique(['followup_rule_group_id', 'sequence'], 'followup_rule_schedule_sequence_unique');
        });

        foreach ([
            'followup_rule_roles'   => ['role_id', 'roles'],
            'followup_rule_users'   => ['user_id', 'users'],
            'followup_rule_sources' => ['lead_source_id', 'lead_sources'],
            'followup_rule_tags'    => ['tag_id', 'tags'],
        ] as $tableName => [$column, $foreignTable]) {
            Schema::create($tableName, function (Blueprint $table) use ($column, $foreignTable) {
                $table->foreignId('followup_rule_group_id')->constrained('followup_rule_groups')->cascadeOnDelete();
                $table->unsignedInteger($column);
                $table->primary(['followup_rule_group_id', $column]);
                $table->foreign($column)->references('id')->on($foreignTable)->cascadeOnDelete();
            });
        }

        Schema::table('leads', function (Blueprint $table) {
            $table->unsignedBigInteger('followup_rule_group_id')->nullable()->after('followup_notes');
            $table->index('followup_rule_group_id');
        });

        $this->seedDefaultRuleFromCurrentConfig();
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['followup_rule_group_id']);
            $table->dropColumn('followup_rule_group_id');
        });

        Schema::dropIfExists('followup_rule_tags');
        Schema::dropIfExists('followup_rule_sources');
        Schema::dropIfExists('followup_rule_users');
        Schema::dropIfExists('followup_rule_roles');
        Schema::dropIfExists('followup_rule_schedules');
        Schema::dropIfExists('followup_rule_groups');
    }

    protected function seedDefaultRuleFromCurrentConfig(): void
    {
        $getConfig = fn (string $key) => DB::table('core_config')
            ->where('code', 'general.settings.followup_schedule.'.$key)
            ->value('value');

        $maxDays = max(1, (int) ($getConfig('max_days') ?: 30));
        $raw = $getConfig('intervals');
        $steps = is_string($raw) ? json_decode($raw, true) : null;

        if (! is_array($steps) || empty($steps)) {
            $steps = [
                ['value' => 4, 'unit' => 'hours', 'frequency' => 2],
                ['value' => 24, 'unit' => 'hours', 'frequency' => 7],
                ['value' => 7, 'unit' => 'days', 'frequency' => 4],
            ];
        }

        $groupId = DB::table('followup_rule_groups')->insertGetId([
            'name'                => 'Default Follow-up Rule',
            'priority'            => 0,
            'is_active'           => true,
            'is_default'          => true,
            'max_days'            => $maxDays,
            'exhaustion_behavior' => 'stop',
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        foreach (array_values($steps) as $index => $step) {
            $value = is_array($step) ? max(1, (int) ($step['value'] ?? 1)) : max(1, (int) $step);
            $unit = is_array($step) ? strtolower((string) ($step['unit'] ?? 'hours')) : 'hours';
            $frequency = is_array($step) ? max(1, (int) ($step['frequency'] ?? 1)) : 1;

            if (! in_array($unit, ['minutes', 'hours', 'days', 'weeks', 'months'], true)) {
                $unit = 'hours';
            }

            DB::table('followup_rule_schedules')->insert([
                'followup_rule_group_id' => $groupId,
                'sequence'               => $index + 1,
                'delay_value'            => $value,
                'delay_unit'             => $unit,
                'frequency'              => $frequency,
                'created_at'             => now(),
                'updated_at'             => now(),
            ]);
        }
    }
};
