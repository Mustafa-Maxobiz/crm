<?php

namespace Webkul\Lead\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Webkul\Lead\Contracts\Lead;
use Webkul\Lead\Repositories\StageRepository;

class FollowupScheduleService
{
    public const UNITS = ['minutes', 'hours', 'days', 'weeks', 'months'];

    public const DEFAULT_STEPS = [
        ['value' => 4, 'unit' => 'hours', 'frequency' => 2],
        ['value' => 24, 'unit' => 'hours', 'frequency' => 7],
        ['value' => 7, 'unit' => 'days', 'frequency' => 4],
    ];

    public function __construct(
        protected StageRepository $stageRepository,
        protected FollowupRuleService $followupRuleService,
    ) {}

    public function isEnabled(): bool
    {
        $value = core()->getConfigData('general.settings.followup_schedule.enabled');

        if ($value === null || $value === '') {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Normalize and return follow-up steps.
     *
     * Each step:
     * - value: interval amount
     * - unit: minutes|hours|days|weeks|months
     * - frequency: how many times this step repeats
     */
    public function steps(): array
    {
        $raw = core()->getConfigData('general.settings.followup_schedule.intervals');

        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);

            if (is_array($decoded) && ! empty($decoded)) {
                $steps = $this->normalizeSteps($decoded);

                if (! empty($steps)) {
                    return $steps;
                }
            }
        }

        return $this->legacySteps();
    }

    public function settings(?Lead $lead = null): array
    {
        $rule = null;

        if ($lead?->followup_rule_group_id) {
            $rule = $this->followupRuleService->findActiveRule(
                (int) $lead->followup_rule_group_id
            );
        }

        if (! $rule) {
            $rule = $lead
                ? $this->followupRuleService->resolve($lead)
                : $this->followupRuleService->defaultRule();
        }

        if ($rule && ! empty($rule['steps'])) {
            return [
                'rule_id'             => $rule['id'],
                'rule_name'           => $rule['name'],
                'steps'               => $rule['steps'],
                'max_days'            => $rule['max_days'],
                'exhaustion_behavior' => $rule['exhaustion_behavior'],
            ];
        }

        $steps = $this->steps();

        return [
            'rule_id'             => null,
            'rule_name'           => 'Legacy Global Schedule',
            'steps'               => $steps,
            'max_days'            => $this->calculateMaxDays($steps),
            'exhaustion_behavior' => 'stop',
        ];
    }

    public function scheduleSummary(): string
    {
        $settings = $this->settings();

        $steps = collect($settings['steps'])
            ->map(function (array $step, int $index) {
                return sprintf(
                    '%d) every %d %s × %d',
                    $index + 1,
                    $step['value'],
                    $step['unit'],
                    $step['frequency']
                );
            })
            ->implode(' → ');

        return sprintf(
            '%s. Ends after day %d or when all steps finish.',
            $steps,
            $settings['max_days']
        );
    }

    public function calculateNext(?Lead $lead = null, ?Carbon $from = null, ?int $completedCount = null): ?Carbon
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $settings = $this->settings($lead);
        $from = ($from ?: Carbon::now())->copy();
        $completedCount = $completedCount ?? (int) ($lead?->followup_count ?? 0);
        $createdAt = $lead?->created_at
            ? Carbon::parse($lead->created_at)
            : $from->copy();

        if ($createdAt->diffInDays($from, false) >= $settings['max_days']) {
            return null;
        }

        $step = $this->stepForCompletedCount(
            $completedCount,
            $settings['steps'],
            $settings['exhaustion_behavior'] ?? 'stop'
        );

        if (! $step) {
            return null;
        }

        return $this->addInterval($from, $step['value'], $step['unit']);
    }

    public function describeNextPhase(?Lead $lead = null, ?int $completedCount = null): string
    {
        if (! $this->isEnabled()) {
            return 'Auto follow-up is disabled.';
        }

        $settings = $this->settings($lead);
        $completedCount = $completedCount ?? (int) ($lead?->followup_count ?? 0);
        $createdAt = $lead?->created_at ? Carbon::parse($lead->created_at) : Carbon::now();

        if ($createdAt->diffInDays(Carbon::now(), false) >= $settings['max_days']) {
            return 'Schedule ended — lead will be marked dead.';
        }

        $resolved = $this->resolveStepPosition(
            $completedCount,
            $settings['steps'],
            $settings['exhaustion_behavior'] ?? 'stop'
        );

        if (! $resolved) {
            return 'Schedule ended — all follow-up steps completed.';
        }

        return sprintf(
            'Auto%s: step %d (%d of %d) — every %d %s',
            ! empty($settings['rule_name']) ? ' ['.$settings['rule_name'].']' : '',
            $resolved['index'] + 1,
            $resolved['occurrence'],
            $resolved['step']['frequency'],
            $resolved['step']['value'],
            $resolved['step']['unit']
        );
    }

    public function applyNextFollowup(Lead $lead, ?Carbon $manualNext = null, bool $allowAuto = true): Lead
    {
        if ($manualNext) {
            $this->persistNextFollowup($lead, $manualNext);

            return $lead->refresh();
        }

        if (! $allowAuto || ! $this->isEnabled()) {
            $this->persistNextFollowup($lead, null);

            return $lead->refresh();
        }

        $next = $this->calculateNext($lead, Carbon::now(), (int) ($lead->followup_count ?? 0));

        if ($next) {
            $settings = $this->settings($lead);

            $lead->newQuery()
                ->whereKey($lead->getKey())
                ->update([
                    'followup_rule_group_id' => $settings['rule_id'] ?? null,
                ]);

            $this->persistNextFollowup($lead, $next);

            return $lead->refresh();
        }

        $this->markLeadDead($lead);

        return $lead->refresh();
    }

    public function persistNextFollowup(Lead $lead, ?Carbon $nextFollowupDate): void
    {
        $lead->newQuery()
            ->whereKey($lead->getKey())
            ->update([
                'next_followup_date' => $nextFollowupDate,
            ]);

        $this->syncAttributeValue($lead, $nextFollowupDate);
    }

    public function markLeadDead(Lead $lead, ?string $reason = null): void
    {
        $maxDays = $this->settings($lead)['max_days'];
        $reason = $reason ?: "Follow-up schedule ended after {$maxDays} days without conversion.";

        $lostStage = $this->stageRepository
            ->findWhere([
                'lead_pipeline_id' => $lead->lead_pipeline_id,
                'code'             => 'lost',
            ])
            ->first();

        $payload = [
            'next_followup_date' => null,
            'closed_at'          => Carbon::now(),
            'lost_reason'        => $lead->lost_reason ?: $reason,
        ];

        if ($lostStage) {
            $payload['lead_pipeline_stage_id'] = $lostStage->id;
        }

        $lead->newQuery()
            ->whereKey($lead->getKey())
            ->update($payload);

        $this->syncAttributeValue($lead, null);

        $lead->refresh();
    }

    public function normalizeSteps(array $steps): array
    {
        return collect($steps)
            ->map(function ($step) {
                if (is_numeric($step)) {
                    return [
                        'value'     => max(1, (int) $step),
                        'unit'      => 'hours',
                        'frequency' => 1,
                    ];
                }

                if (! is_array($step)) {
                    return null;
                }

                $unit = strtolower((string) ($step['unit'] ?? 'hours'));

                if (! in_array($unit, self::UNITS, true)) {
                    $unit = 'hours';
                }

                $value = max(1, (int) ($step['value'] ?? 1));
                $frequency = max(1, (int) ($step['frequency'] ?? 1));

                return [
                    'value'     => $value,
                    'unit'      => $unit,
                    'frequency' => $frequency,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function calculateMaxDays(array $steps): int
    {
        $minutes = collect($this->normalizeSteps($steps))->sum(function (array $step) {
            $factor = match ($step['unit']) {
                'minutes' => 1,
                'hours'   => 60,
                'days'    => 1440,
                'weeks'   => 10080,
                'months'  => 43200,
                default   => 60,
            };

            return $step['value'] * $step['frequency'] * $factor;
        });

        return max(1, (int) ceil($minutes / 1440));
    }

    protected function stepForCompletedCount(int $completedCount, array $steps, string $behavior = 'stop'): ?array
    {
        $resolved = $this->resolveStepPosition($completedCount, $steps, $behavior);

        return $resolved['step'] ?? null;
    }

    protected function resolveStepPosition(int $completedCount, array $steps, string $behavior = 'stop'): ?array
    {
        if (empty($steps)) {
            $steps = self::DEFAULT_STEPS;
        }

        $totalOccurrences = collect($steps)->sum(
            fn ($step) => max(1, (int) ($step['frequency'] ?? 1))
        );

        if ($totalOccurrences <= 0) {
            return null;
        }

        if ($completedCount >= $totalOccurrences) {
            if ($behavior === 'restart') {
                $completedCount %= $totalOccurrences;
            } elseif ($behavior === 'repeat_last') {
                $index = array_key_last($steps);
                $step = $steps[$index];

                return [
                    'index'      => $index,
                    'occurrence' => max(1, (int) $step['frequency']),
                    'step'       => $step,
                ];
            } else {
                return null;
            }
        }

        $cursor = 0;

        foreach ($steps as $index => $step) {
            $frequency = max(1, (int) $step['frequency']);

            if ($completedCount < $cursor + $frequency) {
                return [
                    'index'      => $index,
                    'occurrence' => ($completedCount - $cursor) + 1,
                    'step'       => $step,
                ];
            }

            $cursor += $frequency;
        }

        return null;
    }

    protected function addInterval(Carbon $from, int $value, string $unit): Carbon
    {
        $date = $from->copy();

        return match ($unit) {
            'minutes' => $date->addMinutes($value),
            'hours'   => $date->addHours($value),
            'days'    => $date->addDays($value),
            'weeks'   => $date->addWeeks($value),
            'months'  => $date->addMonthsNoOverflow($value),
            default   => $date->addHours($value),
        };
    }

    protected function legacySteps(): array
    {
        $rawIntervals = core()->getConfigData('general.settings.followup_schedule.intervals');

        if (is_string($rawIntervals) && $rawIntervals !== '') {
            $decoded = json_decode($rawIntervals, true);

            if (is_array($decoded) && ! empty($decoded) && is_numeric($decoded[0] ?? null)) {
                return $this->normalizeSteps($decoded);
            }
        }

        $first = $this->configInt('first_followup_hours', 0)
            ?: $this->configInt('first_interval_hours', 4);

        $frequencyHours = $this->configInt('frequency_hours', 0)
            ?: $this->configInt('daily_interval_hours', 24);

        $second = $this->configInt('second_interval_hours', $first);

        return [
            ['value' => max(1, $first), 'unit' => 'hours', 'frequency' => 1],
            ['value' => max(1, $second), 'unit' => 'hours', 'frequency' => 1],
            ['value' => max(1, $frequencyHours), 'unit' => 'hours', 'frequency' => 1],
        ];
    }

    protected function syncAttributeValue(Lead $lead, ?Carbon $nextFollowupDate): void
    {
        $followupAttribute = DB::table('attributes')
            ->where('entity_type', 'leads')
            ->where('code', 'next_followup_date')
            ->first();

        if (! $followupAttribute) {
            return;
        }

        DB::table('attribute_values')->updateOrInsert(
            [
                'entity_type'  => 'leads',
                'entity_id'    => $lead->getKey(),
                'attribute_id' => $followupAttribute->id,
            ],
            [
                'datetime_value' => $nextFollowupDate,
                'unique_id'      => $lead->getKey().'|'.$followupAttribute->id,
            ]
        );
    }

    protected function configInt(string $name, int $default): int
    {
        $value = core()->getConfigData('general.settings.followup_schedule.'.$name);

        if ($value === null || $value === '' || ! is_numeric($value)) {
            return $default;
        }

        return max(0, (int) $value);
    }
}
