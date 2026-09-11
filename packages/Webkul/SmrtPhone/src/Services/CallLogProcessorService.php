<?php

namespace Webkul\SmrtPhone\Services;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\SmrtPhone\Models\SmrtPhoneCallLog;
use Webkul\SmrtPhone\Repositories\SmrtPhoneCallLogRepository;

class CallLogProcessorService
{
    public function __construct(
        protected SmrtPhoneCallLogRepository $callLogRepository,
        protected PhoneMatcherService $phoneMatcher,
        protected ActivityRepository $activityRepository,
        protected SmrtPhoneConfigService $configService,
    ) {}

    /**
     * Persist or update a smrtPhone call webhook payload.
     */
    public function process(array $payload): ?SmrtPhoneCallLog
    {
        $event = (string) ($payload['event'] ?? '');

        if (! in_array($event, SmrtPhoneCallLog::CALL_EVENTS, true)) {
            Log::info('SmrtPhone webhook ignored non-call event.', ['event' => $event]);

            return null;
        }

        if (! $this->isOutboundSmrtDialerLifecycleEvent($event)) {
            Log::info('SmrtPhone webhook ignored non-outbound smrtDialer lifecycle event.', ['event' => $event]);

            return null;
        }

        $direction = $this->resolveDirection(
            $event,
            (string) ($this->firstFilledPayloadValue($payload, ['from', 'from_number', 'fromNumber']) ?? ''),
            (string) ($this->firstFilledPayloadValue($payload, ['to', 'to_number', 'toNumber']) ?? ''),
        );

        if (! $this->configService->shouldAcceptCallPayload($payload, $direction)) {
            Log::info('SmrtPhone webhook ignored: business number not allowed.', [
                'event' => $event,
                'from'  => $payload['from'] ?? $payload['from_number'] ?? $payload['fromNumber'] ?? null,
                'to'    => $payload['to'] ?? $payload['to_number'] ?? $payload['toNumber'] ?? null,
            ]);

            return null;
        }

        $externalCallId = $this->resolveExternalCallId($payload);

        if (! $externalCallId) {
            Log::warning('SmrtPhone webhook missing call id.', ['event' => $event, 'payload' => $payload]);

            return null;
        }

        $existing = $this->callLogRepository->findByExternalCallId($externalCallId);

        $attributes = $this->mapPayload($payload, $event, $externalCallId);

        if ($existing) {
            if (! $this->customerPhoneMatches($existing, $attributes)) {
                Log::warning('SmrtPhone webhook call id matched but customer phone differed.', [
                    'external_call_id' => $externalCallId,
                    'existing_phone'   => $existing->contact_phone ?: $existing->to_number,
                    'incoming_phone'   => $attributes['contact_phone'] ?? $attributes['to_number'] ?? null,
                ]);

                return null;
            }

            $merged = $this->mergeAttributes($existing->getAttributes(), $attributes);
            $this->callLogRepository->update($merged, $existing->id);
            $callLog = $this->callLogRepository->find($existing->id);
        } else {
            $callLog = $this->callLogRepository->create($attributes);
        }

        if ($this->isCompletedEvent($event)) {
            $this->linkPersonAndLead($callLog);
            $this->syncActivity($callLog->fresh(['person', 'lead', 'activity']));
        }

        return $callLog->fresh(['person', 'lead', 'activity']);
    }

    protected function resolveExternalCallId(array $payload): ?string
    {
        foreach (['callId', 'dialerCallId', 'smrtPhoneCallId', 'id'] as $key) {
            $value = Arr::get($payload, $key);

            if (filled($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    protected function mapPayload(array $payload, string $event, string $externalCallId): array
    {
        $from = (string) ($this->firstFilledPayloadValue($payload, ['from', 'from_number', 'fromNumber']) ?? '');
        $to = (string) ($this->firstFilledPayloadValue($payload, ['to', 'to_number', 'toNumber']) ?? '');
        $isDialer = str_contains(strtolower($event), 'smrtdialer');
        $direction = $this->resolveDirection($event, $from, $to);
        $contactPhone = $this->resolveContactPhone($direction, $from, $to);
        $calledAt = $this->parseCalledAt($payload);

        $attributes = [
            'external_call_id' => $externalCallId,
            'event'            => $event,
            'direction'        => $direction,
            'from_number'      => $from ?: null,
            'to_number'        => $to ?: null,
            'contact_phone'    => $contactPhone ?: null,
            'contact_name'     => $payload['contactName'] ?? null,
            'caller_id_name'   => $payload['callerIdName'] ?? null,
            'user_name'        => $payload['userName'] ?? null,
            'device'           => $payload['device'] ?? null,
            'call_status'      => $payload['callStatus'] ?? null,
            'call_outcome'     => $payload['callOutcome'] ?? null,
            'call_notes'       => $this->nullableString($this->firstFilledPayloadValue($payload, [
                'callNotes',
                'call_notes',
                'notes',
                'note',
            ])),
            'recording_url'    => $this->nullableString($this->firstFilledPayloadValue($payload, [
                'recordingUrl',
                'recording_url',
                'recording',
                'recordingLink',
            ])),
            'is_dialer'        => $isDialer,
            'called_at'        => $this->formatDateTimeForStorage($calledAt),
            'raw_payload'      => $payload,
        ];

        if ($this->isInitiatedEvent($event)) {
            $attributes['started_at'] = $this->formatDateTimeForStorage($calledAt);
        }

        if ($this->isCompletedEvent($event)) {
            $attributes['completed_at'] = $this->formatDateTimeForStorage($calledAt);
        }

        if ($this->configService->saveAiSummary()) {
            $attributes['ai_summary'] = $this->nullableString($this->firstFilledPayloadValue($payload, [
                'ai_summary',
                'aiSummary',
                'summary',
                'callSummary',
            ]));
            $attributes['ai_transcript'] = $this->firstFilledPayloadValue($payload, [
                'ai_transcript',
                'aiTranscript',
                'transcript',
            ]);
            $attributes['ai_keywords'] = $this->firstFilledPayloadValue($payload, [
                'ai_keywords',
                'aiKeywords',
                'ai_keyword',
            ]);
        }

        return array_filter($attributes, fn ($value) => $value !== null && $value !== '');
    }

    protected function mergeAttributes(array $existing, array $incoming): array
    {
        $keepKeys = [
            'person_id',
            'lead_id',
            'activity_id',
            'created_at',
            'updated_at',
            'id',
        ];

        foreach ($incoming as $key => $value) {
            if (in_array($key, $keepKeys, true)) {
                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            $existing[$key] = $value;
        }

        $existing['duration_seconds'] = $this->calculateDurationSeconds(
            $existing['started_at'] ?? null,
            $existing['completed_at'] ?? null,
        );

        if (! empty($incoming['raw_payload']) && is_array($incoming['raw_payload'])) {
            $existing['raw_payload'] = array_merge(
                is_array($existing['raw_payload'] ?? null) ? $existing['raw_payload'] : [],
                $incoming['raw_payload']
            );
        }

        return Arr::only($existing, (new SmrtPhoneCallLog)->getFillable());
    }

    protected function customerPhoneMatches(SmrtPhoneCallLog $existing, array $incoming): bool
    {
        $existingPhone = $this->normalizePhoneNumber($existing->contact_phone ?: $existing->to_number);
        $incomingPhone = $this->normalizePhoneNumber($incoming['contact_phone'] ?? $incoming['to_number'] ?? null);

        return $this->phoneNumbersMatch($existingPhone, $incomingPhone);
    }

    protected function resolveDirection(string $event, string $from, string $to): string
    {
        if ($this->isOutboundSmrtDialerLifecycleEvent($event)) {
            return 'outbound';
        }

        return 'unknown';
    }

    protected function resolveContactPhone(string $direction, string $from, string $to): ?string
    {
        if ($direction === 'outbound') {
            return $to ?: $from ?: null;
        }

        return $to ?: $from ?: null;
    }

    protected function isOutboundSmrtDialerLifecycleEvent(string $event): bool
    {
        return in_array($event, [
            'callInitiatedSmrtDialer',
            'callCompletedSmrtDialer',
        ], true);
    }

    protected function isInitiatedEvent(string $event): bool
    {
        return $event === 'callInitiatedSmrtDialer';
    }

    protected function isCompletedEvent(string $event): bool
    {
        return $event === 'callCompletedSmrtDialer';
    }

    /**
     * CRM receive time is used as fallback when smrtPhone does not provide a reliable event time.
     */
    protected function parseCalledAt(array $payload): Carbon
    {
        return $this->parsePayloadDateWithFallback($payload)['datetime'];
    }

    /**
     * @return array{datetime: Carbon, used_receive_time: bool, has_reliable_time: bool}
     */
    protected function parsePayloadDateWithFallback(array $payload): array
    {
        $raw = $payload['date']
            ?? $payload['timestamp']
            ?? $payload['createdAt']['date']
            ?? null;
        $timezone = config('app.timezone') ?: 'UTC';

        try {
            if ($this->hasReliablePayloadTime($raw)) {
                $datetime = is_numeric($raw)
                    ? Carbon::createFromTimestamp(strlen((string) $raw) >= 13 ? (int) floor(((int) $raw) / 1000) : (int) $raw)->timezone($timezone)
                    : Carbon::parse($raw, $timezone);

                return [
                    'datetime'          => $datetime,
                    'used_receive_time' => false,
                    'has_reliable_time' => true,
                ];
            }
        } catch (\Throwable) {
            //
        }

        return [
            'datetime'          => now($timezone),
            'used_receive_time' => true,
            'has_reliable_time' => false,
        ];
    }

    protected function hasReliablePayloadTime(mixed $raw): bool
    {
        if ($raw === null || $raw === '' || $raw === 'null') {
            return false;
        }

        $raw = trim((string) $raw);

        if (preg_match('/^\d{8}$/', $raw)) {
            return false;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return false;
        }

        if (is_numeric($raw)) {
            return strlen($raw) >= 10;
        }

        return preg_match('/\d{1,2}:\d{2}/', $raw) === 1
            || str_contains($raw, 'T');
    }

    protected function formatDateTimeForStorage(Carbon $dateTime): string
    {
        return $dateTime
            ->copy()
            ->timezone(config('app.timezone') ?: 'UTC')
            ->format('Y-m-d H:i:s');
    }

    protected function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === 'null') {
            return null;
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: null;
        }

        return trim((string) $value);
    }

    /**
     * @param  array<int, string>  $keys
     */
    protected function firstFilledPayloadValue(array $payload, array $keys): mixed
    {
        foreach ($keys as $key) {
            $value = Arr::get($payload, $key);

            if ($value === null || $value === '' || $value === 'null') {
                continue;
            }

            return $value;
        }

        return null;
    }

    protected function calculateDurationSeconds(mixed $startedAt, mixed $completedAt): ?int
    {
        if (! $startedAt || ! $completedAt) {
            return null;
        }

        try {
            $timezone = config('app.timezone') ?: 'UTC';
            $startedAt = Carbon::parse($startedAt, $timezone);
            $completedAt = Carbon::parse($completedAt, $timezone);
        } catch (\Throwable) {
            return null;
        }

        if ($completedAt->lessThan($startedAt)) {
            return null;
        }

        return $startedAt->diffInSeconds($completedAt);
    }

    protected function normalizePhoneNumber(?string $number): string
    {
        return preg_replace('/\D+/', '', (string) $number) ?: '';
    }

    protected function phoneNumbersMatch(string $firstNumber, string $secondNumber): bool
    {
        if ($firstNumber === '' || $secondNumber === '') {
            return false;
        }

        if ($firstNumber === $secondNumber) {
            return true;
        }

        if (strlen($firstNumber) >= 10 && strlen($secondNumber) >= 10) {
            return substr($firstNumber, -10) === substr($secondNumber, -10);
        }

        return false;
    }

    protected function linkPersonAndLead(SmrtPhoneCallLog $callLog): void
    {
        if (! $this->configService->autoMatchLead()) {
            return;
        }

        if ($callLog->person_id && $callLog->lead_id) {
            return;
        }

        $person = $callLog->person_id
            ? $callLog->person
            : $this->phoneMatcher->findPersonByPhone($callLog->contact_phone);

        if (! $person) {
            return;
        }

        $lead = $callLog->lead_id
            ? $callLog->lead
            : $this->phoneMatcher->findLeadForPerson($person);

        $this->callLogRepository->update(array_filter([
            'person_id' => $person->id,
            'lead_id'   => $lead?->id,
        ]), $callLog->id);
    }

    protected function syncActivity(SmrtPhoneCallLog $callLog): void
    {
        if (! $this->configService->createActivities()) {
            return;
        }

        if (! $this->configService->autoMatchLead()) {
            return;
        }

        if (
            $this->configService->createActivityOnlyWhenMatched()
            && ! $callLog->lead_id
            && ! $callLog->person_id
        ) {
            return;
        }

        $title = trim(($callLog->direction === 'inbound' ? 'Inbound' : 'Outbound').' call via smrtPhone');
        $commentParts = array_filter([
            $callLog->call_notes ? 'Call Notes: '.$callLog->call_notes : null,
            $callLog->call_status ? 'Status: '.$callLog->call_status : null,
            $callLog->recording_url ? 'Recording: '.$callLog->recording_url : null,
            $this->configService->saveAiSummary() && $callLog->ai_summary ? 'AI Summary: '.$callLog->ai_summary : null,
        ]);

        $data = [
            'title'         => $title,
            'type'          => 'call',
            'comment'       => implode("\n", $commentParts) ?: null,
            'schedule_from' => $callLog->started_at ?: $callLog->called_at ?: now(),
            'schedule_to'   => $callLog->completed_at ?: $callLog->called_at ?: now(),
            'is_done'       => 1,
            'call_status'   => $this->mapCallStatus($callLog),
            'additional'    => json_encode([
                'source'           => 'smrtphone',
                'external_call_id' => $callLog->external_call_id,
                'from'             => $callLog->from_number,
                'to'               => $callLog->to_number,
                'recording_url'    => $callLog->recording_url,
            ]),
        ];

        if ($callLog->activity_id) {
            Event::dispatch('activity.update.before', $callLog->activity_id);
            $activity = $this->activityRepository->update($data, $callLog->activity_id);
            Event::dispatch('activity.update.after', $activity);
        } else {
            Event::dispatch('activity.create.before');
            $activity = $this->activityRepository->create($data);
            Event::dispatch('activity.create.after', $activity);

            if ($callLog->lead_id) {
                $activity->leads()->syncWithoutDetaching([$callLog->lead_id]);
            }

            if ($callLog->person_id) {
                $activity->persons()->syncWithoutDetaching([$callLog->person_id]);
            }

            $this->callLogRepository->update([
                'activity_id' => $activity->id,
            ], $callLog->id);
        }
    }

    protected function mapCallStatus(SmrtPhoneCallLog $callLog): string
    {
        $status = strtolower((string) ($callLog->call_status ?: $callLog->call_outcome));

        return match (true) {
            str_contains($status, 'miss'),
            str_contains($status, 'no answer'),
            str_contains($status, 'no_answer'),
            str_contains($status, 'busy'),
            str_contains($status, 'fail'),
            str_contains($status, 'voice') => 'not_answered',
            default => 'done',
        };
    }
}
