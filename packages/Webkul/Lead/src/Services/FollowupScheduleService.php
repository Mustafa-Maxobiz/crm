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

    public const DEFAULT_MAX_DAYS = 30;

    public function __construct(
        protected StageRepository $stageRepository
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

    public function settings(): array
    {
        return [
            'steps'    => $this->steps(),
            'max_days' => $this->configInt('max_days', self::DEFAULT_MAX_DAYS),
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

        $settings = $this->settings();
        $from = ($from ?: Carbon::now())->copy();
        $completedCount = $completedCount ?? (int) ($lead?->followup_count ?? 0);
        $createdAt = $lead?->created_at
            ? Carbon::parse($lead->created_at)
            : $from->copy();

        if ($createdAt->diffInDays($from, false) >= $settings['max_days']) {
            return null;
        }

        $step = $this->stepForCompletedCount($completedCount, $settings['steps']);

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

        $settings = $this->settings();
        $completedCount = $completedCount ?? (int) ($lead?->followup_count ?? 0);
        $createdAt = $lead?->created_at ? Carbon::parse($lead->created_at) : Carbon::now();

        if ($createdAt->diffInDays(Carbon::now(), false) >= $settings['max_days']) {
            return 'Schedule ended — lead will be marked dead.';
        }

        $resolved = $this->resolveStepPosition($completedCount, $settings['steps']);

        if (! $resolved) {
            return 'Schedule ended — all follow-up steps completed.';
        }

        return sprintf(
            'Auto: step %d (%d of %d) — every %d %s',
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
        $maxDays = $this->settings()['max_days'];
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

    protected function stepForCompletedCount(int $completedCount, array $steps): ?array
    {
        $resolved = $this->resolveStepPosition($completedCount, $steps);

        return $resolved['step'] ?? null;
    }

    protected function resolveStepPosition(int $completedCount, array $steps): ?array
    {
        if (empty($steps)) {
            $steps = self::DEFAULT_STEPS;
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
