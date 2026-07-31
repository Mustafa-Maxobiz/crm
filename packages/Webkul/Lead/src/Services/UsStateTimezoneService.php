<?php

namespace Webkul\Lead\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;

class UsStateTimezoneService
{
    /**
     * All US states with IANA timezones.
     *
     * @return array<int, array{state: string, abbr: string, timezone: string, popular: bool}>
     */
    public function allStates(): array
    {
        return [
            ['state' => 'Alabama', 'abbr' => 'AL', 'timezone' => 'America/Chicago', 'popular' => false],
            ['state' => 'Alaska', 'abbr' => 'AK', 'timezone' => 'America/Anchorage', 'popular' => false],
            ['state' => 'Arizona', 'abbr' => 'AZ', 'timezone' => 'America/Phoenix', 'popular' => true],
            ['state' => 'Arkansas', 'abbr' => 'AR', 'timezone' => 'America/Chicago', 'popular' => false],
            ['state' => 'California', 'abbr' => 'CA', 'timezone' => 'America/Los_Angeles', 'popular' => true],
            ['state' => 'Colorado', 'abbr' => 'CO', 'timezone' => 'America/Denver', 'popular' => true],
            ['state' => 'Connecticut', 'abbr' => 'CT', 'timezone' => 'America/New_York', 'popular' => false],
            ['state' => 'Delaware', 'abbr' => 'DE', 'timezone' => 'America/New_York', 'popular' => false],
            ['state' => 'Florida', 'abbr' => 'FL', 'timezone' => 'America/New_York', 'popular' => true],
            ['state' => 'Georgia', 'abbr' => 'GA', 'timezone' => 'America/New_York', 'popular' => true],
            ['state' => 'Hawaii', 'abbr' => 'HI', 'timezone' => 'Pacific/Honolulu', 'popular' => false],
            ['state' => 'Idaho', 'abbr' => 'ID', 'timezone' => 'America/Boise', 'popular' => false],
            ['state' => 'Illinois', 'abbr' => 'IL', 'timezone' => 'America/Chicago', 'popular' => true],
            ['state' => 'Indiana', 'abbr' => 'IN', 'timezone' => 'America/Indiana/Indianapolis', 'popular' => false],
            ['state' => 'Iowa', 'abbr' => 'IA', 'timezone' => 'America/Chicago', 'popular' => false],
            ['state' => 'Kansas', 'abbr' => 'KS', 'timezone' => 'America/Chicago', 'popular' => false],
            ['state' => 'Kentucky', 'abbr' => 'KY', 'timezone' => 'America/New_York', 'popular' => false],
            ['state' => 'Louisiana', 'abbr' => 'LA', 'timezone' => 'America/Chicago', 'popular' => false],
            ['state' => 'Maine', 'abbr' => 'ME', 'timezone' => 'America/New_York', 'popular' => false],
            ['state' => 'Maryland', 'abbr' => 'MD', 'timezone' => 'America/New_York', 'popular' => false],
            ['state' => 'Massachusetts', 'abbr' => 'MA', 'timezone' => 'America/New_York', 'popular' => true],
            ['state' => 'Michigan', 'abbr' => 'MI', 'timezone' => 'America/Detroit', 'popular' => true],
            ['state' => 'Minnesota', 'abbr' => 'MN', 'timezone' => 'America/Chicago', 'popular' => false],
            ['state' => 'Mississippi', 'abbr' => 'MS', 'timezone' => 'America/Chicago', 'popular' => false],
            ['state' => 'Missouri', 'abbr' => 'MO', 'timezone' => 'America/Chicago', 'popular' => false],
            ['state' => 'Montana', 'abbr' => 'MT', 'timezone' => 'America/Denver', 'popular' => false],
            ['state' => 'Nebraska', 'abbr' => 'NE', 'timezone' => 'America/Chicago', 'popular' => false],
            ['state' => 'Nevada', 'abbr' => 'NV', 'timezone' => 'America/Los_Angeles', 'popular' => true],
            ['state' => 'New Hampshire', 'abbr' => 'NH', 'timezone' => 'America/New_York', 'popular' => false],
            ['state' => 'New Jersey', 'abbr' => 'NJ', 'timezone' => 'America/New_York', 'popular' => true],
            ['state' => 'New Mexico', 'abbr' => 'NM', 'timezone' => 'America/Denver', 'popular' => false],
            ['state' => 'New York', 'abbr' => 'NY', 'timezone' => 'America/New_York', 'popular' => true],
            ['state' => 'North Carolina', 'abbr' => 'NC', 'timezone' => 'America/New_York', 'popular' => true],
            ['state' => 'North Dakota', 'abbr' => 'ND', 'timezone' => 'America/Chicago', 'popular' => false],
            ['state' => 'Ohio', 'abbr' => 'OH', 'timezone' => 'America/New_York', 'popular' => true],
            ['state' => 'Oklahoma', 'abbr' => 'OK', 'timezone' => 'America/Chicago', 'popular' => false],
            ['state' => 'Oregon', 'abbr' => 'OR', 'timezone' => 'America/Los_Angeles', 'popular' => true],
            ['state' => 'Pennsylvania', 'abbr' => 'PA', 'timezone' => 'America/New_York', 'popular' => true],
            ['state' => 'Rhode Island', 'abbr' => 'RI', 'timezone' => 'America/New_York', 'popular' => false],
            ['state' => 'South Carolina', 'abbr' => 'SC', 'timezone' => 'America/New_York', 'popular' => false],
            ['state' => 'South Dakota', 'abbr' => 'SD', 'timezone' => 'America/Chicago', 'popular' => false],
            ['state' => 'Tennessee', 'abbr' => 'TN', 'timezone' => 'America/Chicago', 'popular' => true],
            ['state' => 'Texas', 'abbr' => 'TX', 'timezone' => 'America/Chicago', 'popular' => true],
            ['state' => 'Utah', 'abbr' => 'UT', 'timezone' => 'America/Denver', 'popular' => true],
            ['state' => 'Vermont', 'abbr' => 'VT', 'timezone' => 'America/New_York', 'popular' => false],
            ['state' => 'Virginia', 'abbr' => 'VA', 'timezone' => 'America/New_York', 'popular' => true],
            ['state' => 'Washington', 'abbr' => 'WA', 'timezone' => 'America/Los_Angeles', 'popular' => true],
            ['state' => 'West Virginia', 'abbr' => 'WV', 'timezone' => 'America/New_York', 'popular' => false],
            ['state' => 'Wisconsin', 'abbr' => 'WI', 'timezone' => 'America/Chicago', 'popular' => false],
            ['state' => 'Wyoming', 'abbr' => 'WY', 'timezone' => 'America/Denver', 'popular' => false],
        ];
    }

    /**
     * Resolve IANA timezone from a US state code or full name.
     */
    public function timezoneForState(?string $state): ?string
    {
        $state = trim((string) $state);

        if ($state === '') {
            return null;
        }

        $normalized = strtoupper($state);
        $normalizedName = strtolower($state);

        foreach ($this->allStates() as $entry) {
            if (
                strtoupper($entry['abbr']) === $normalized
                || strtolower($entry['state']) === $normalizedName
            ) {
                return $entry['timezone'];
            }
        }

        return null;
    }

    /**
     * Resolve US timezone from a person address attribute (JSON).
     */
    public function timezoneFromAddress(mixed $address): ?string
    {
        if (is_string($address)) {
            $decoded = json_decode($address, true);
            $address = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        if (! is_array($address)) {
            return null;
        }

        return $this->timezoneForState($address['state'] ?? null);
    }

    /**
     * Resolve US timezone from a person model / object.
     *
     * Prefers dedicated `persons.timezone` / `persons.state` columns,
     * then falls back to legacy nested address payloads.
     */
    public function timezoneFromPerson(mixed $person): ?string
    {
        if (! $person) {
            return null;
        }

        $storedTimezone = is_object($person)
            ? ($person->timezone ?? null)
            : ($person['timezone'] ?? null);

        if (filled($storedTimezone)) {
            return (string) $storedTimezone;
        }

        $state = is_object($person)
            ? ($person->state ?? null)
            : ($person['state'] ?? null);

        if (filled($state)) {
            return $this->timezoneForState((string) $state);
        }

        $address = is_object($person)
            ? ($person->address ?? null)
            : ($person['address'] ?? null);

        return $this->timezoneFromAddress($address);
    }

    /**
     * App/.env timezone (Pakistan or whatever APP_TIMEZONE is).
     */
    public function appTimezone(): string
    {
        return (string) config('app.timezone', 'UTC');
    }

    /**
     * Format a datetime in app timezone and optional US state timezone.
     *
     * @return array{local: ?string, us: ?string, label: ?string, timezone_local: string, timezone_us: ?string}
     */
    public function formatDual(
        CarbonInterface|string|null $datetime,
        ?string $usTimezone,
        string $format = 'M d, Y h:i A'
    ): array {
        $appTimezone = $this->appTimezone();

        if ($datetime === null || $datetime === '') {
            return [
                'local'           => null,
                'us'              => null,
                'label'           => null,
                'timezone_local'  => $appTimezone,
                'timezone_us'     => $usTimezone,
            ];
        }

        $carbon = $datetime instanceof CarbonInterface
            ? $datetime->copy()
            : Carbon::parse($datetime);

        $local = $carbon->copy()->timezone($appTimezone)->format($format.' T');
        $us = null;

        if ($usTimezone) {
            try {
                $us = $carbon->copy()->timezone($usTimezone)->format($format.' T');
            } catch (\Throwable) {
                $us = null;
                $usTimezone = null;
            }
        }

        return [
            'local'          => $local,
            'us'             => $us,
            'label'          => $us ? "{$local} · {$us}" : $local,
            'timezone_local' => $appTimezone,
            'timezone_us'    => $usTimezone,
        ];
    }

    /**
     * Compact time-only dual label (for SDR dashboard badges).
     *
     * @return array{local: ?string, us: ?string, label: ?string, timezone_local: string, timezone_us: ?string}
     */
    public function formatDualTime(
        CarbonInterface|string|null $datetime,
        ?string $usTimezone
    ): array {
        return $this->formatDual($datetime, $usTimezone, 'h:i A');
    }

    /**
     * Sort metadata for SDR "today" lists using prospect US wall-clock time
     * (not APP_TIMEZONE / PKR). Prioritizes 11am–4pm US local time.
     *
     * `us_sort_at` is minutes from midnight in the US timezone so ordering
     * matches the displayed US time badges.
     *
     * @return array{in_priority_window: bool, priority_rank: int, us_sort_at: int}
     */
    public function priorityWindowSortMeta(
        CarbonInterface|string|null $datetime,
        ?string $usTimezone,
        int $windowStartHour = 11,
        int $windowEndHour = 16
    ): array {
        $fallback = [
            'in_priority_window' => false,
            'priority_rank'      => 1,
            'us_sort_at'         => PHP_INT_MAX,
        ];

        if ($datetime === null || $datetime === '') {
            return $fallback;
        }

        $carbon = $datetime instanceof CarbonInterface
            ? $datetime->copy()
            : Carbon::parse($datetime);

        if (! $usTimezone) {
            // No prospect state: keep after US-prioritized rows; order by app/PKR clock.
            $local = $carbon->copy()->timezone($this->appTimezone());

            return [
                'in_priority_window' => false,
                'priority_rank'      => 1,
                'us_sort_at'         => ((int) $local->format('H') * 60) + (int) $local->format('i'),
            ];
        }

        try {
            $usTime = $carbon->copy()->timezone($usTimezone);
        } catch (\Throwable) {
            return $fallback;
        }

        $minutes = ((int) $usTime->format('H') * 60) + (int) $usTime->format('i');
        $windowStart = $windowStartHour * 60;
        $windowEnd = $windowEndHour * 60;
        $inWindow = $minutes >= $windowStart && $minutes <= $windowEnd;

        return [
            'in_priority_window' => $inWindow,
            'priority_rank'      => $inWindow ? 0 : 1,
            'us_sort_at'         => $minutes,
        ];
    }
}
