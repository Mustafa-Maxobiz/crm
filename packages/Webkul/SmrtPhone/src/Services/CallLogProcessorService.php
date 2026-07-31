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

        $externalCallId = $this->resolveExternalCallId($payload);

        if (! $externalCallId) {
            Log::warning('SmrtPhone webhook missing call id.', ['event' => $event, 'payload' => $payload]);

            return null;
        }

        $existing = $this->callLogRepository->findByExternalCallId($externalCallId);

        $attributes = $this->mapPayload($payload, $event, $externalCallId);

        if ($existing) {
            $merged = $this->mergeAttributes($existing->toArray(), $attributes);
            $this->callLogRepository->update($merged, $existing->id);
            $callLog = $this->callLogRepository->find($existing->id);
        } else {
            $callLog = $this->callLogRepository->create($attributes);
        }

        $this->linkPersonAndLead($callLog);
        $this->syncActivity($callLog->fresh(['person', 'lead', 'activity']));

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
        $from = (string) ($payload['from'] ?? '');
        $to = (string) ($payload['to'] ?? '');
        $isDialer = str_contains(strtolower($event), 'smrtdialer');
        $direction = $this->resolveDirection($event, $from, $to);
        $contactPhone = $this->resolveContactPhone($direction, $from, $to);

        return array_filter([
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
            'call_notes'       => $this->nullableString($payload['callNotes'] ?? null),
            'recording_url'    => $payload['recordingUrl'] ?? null,
            'ai_summary'       => $payload['ai_summary'] ?? ($payload['summary'] ?? null),
            'ai_transcript'    => $payload['ai_transcript'] ?? ($payload['transcript'] ?? null),
            'ai_keywords'      => $payload['ai_keywords'] ?? ($payload['ai_keyword'] ?? null),
            'is_dialer'        => $isDialer,
            'called_at'        => $this->parseCalledAt($payload),
            'raw_payload'      => $payload,
        ], fn ($value) => $value !== null && $value !== '');
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

        if (! empty($incoming['raw_payload']) && is_array($incoming['raw_payload'])) {
            $existing['raw_payload'] = array_merge(
                is_array($existing['raw_payload'] ?? null) ? $existing['raw_payload'] : [],
                $incoming['raw_payload']
            );
        }

        return Arr::only($existing, (new SmrtPhoneCallLog)->getFillable());
    }

    protected function resolveDirection(string $event, string $from, string $to): string
    {
        if (str_contains(strtolower($event), 'incoming')) {
            return 'inbound';
        }

        if (str_contains(strtolower($event), 'initiated') || str_contains(strtolower($event), 'outgoing')) {
            return 'outbound';
        }

        return 'unknown';
    }

    protected function resolveContactPhone(string $direction, string $from, string $to): ?string
    {
        if ($direction === 'inbound') {
            return $from ?: $to ?: null;
        }

        if ($direction === 'outbound') {
            return $to ?: $from ?: null;
        }

        return $from ?: $to ?: null;
    }

    protected function parseCalledAt(array $payload): ?Carbon
    {
        $raw = $payload['date']
            ?? $payload['timestamp']
            ?? $payload['createdAt']['date']
            ?? null;

        if (! $raw) {
            return now();
        }

        try {
            if (is_numeric($raw) && strlen((string) $raw) === 8) {
                return Carbon::createFromFormat('Ymd', (string) $raw)->startOfDay();
            }

            return Carbon::parse($raw);
        } catch (\Throwable) {
            return now();
        }
    }

    protected function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === 'null') {
            return null;
        }

        return (string) $value;
    }

    protected function linkPersonAndLead(SmrtPhoneCallLog $callLog): void
    {
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
        if (! config('smrtphone.create_activities', true)) {
            return;
        }

        if (! $callLog->lead_id && ! $callLog->person_id) {
            return;
        }

        $title = trim(($callLog->direction === 'inbound' ? 'Inbound' : 'Outbound').' call via smrtPhone');
        $commentParts = array_filter([
            $callLog->call_notes,
            $callLog->call_status ? 'Status: '.$callLog->call_status : null,
            $callLog->call_outcome ? 'Outcome: '.$callLog->call_outcome : null,
            $callLog->recording_url ? 'Recording: '.$callLog->recording_url : null,
            $callLog->ai_summary ? 'AI Summary: '.$callLog->ai_summary : null,
        ]);

        $data = [
            'title'         => $title,
            'type'          => 'call',
            'comment'       => implode("\n", $commentParts) ?: null,
            'schedule_from' => $callLog->called_at ?: now(),
            'schedule_to'   => $callLog->called_at ?: now(),
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
