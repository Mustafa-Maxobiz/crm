<?php

namespace Webkul\SmrtPhone\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SmrtPhoneConfigService
{
    protected const CACHE_KEY = 'smrtphone.settings';

    public function webhookSecret(): string
    {
        $setting = trim((string) $this->setting('webhook_secret'));

        if ($setting !== '') {
            return $setting;
        }

        return trim((string) config('smrtphone.webhook_secret', ''));
    }

    public function createActivities(): bool
    {
        return $this->booleanSetting('create_activities', (bool) config('smrtphone.create_activities', true));
    }

    public function saveAiSummary(): bool
    {
        return $this->booleanSetting('save_ai_summary', true);
    }

    public function autoMatchLead(): bool
    {
        return $this->booleanSetting('auto_match_lead', true);
    }

    public function createActivityOnlyWhenMatched(): bool
    {
        return $this->booleanSetting('create_activity_only_when_matched', true);
    }

    /**
     * @return array<int, string>
     */
    public function allowedNumbers(): array
    {
        $value = $this->setting('allowed_numbers');

        if ($value === null || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn ($number) => trim((string) $number), $decoded),
            fn ($number) => $number !== '',
        ));
    }

    /**
     * @return array<int, string>
     */
    public function normalizedAllowedNumbers(): array
    {
        return array_values(array_unique(array_filter(
            array_map(fn ($number) => $this->normalizePhoneNumber($number), $this->allowedNumbers()),
            fn ($number) => $number !== '',
        )));
    }

    public function isAllowedBusinessNumber(?string $number): bool
    {
        $allowedNumbers = $this->normalizedAllowedNumbers();

        if (empty($allowedNumbers)) {
            return true;
        }

        $number = $this->normalizePhoneNumber($number);

        if ($number === '') {
            return false;
        }

        foreach ($allowedNumbers as $allowedNumber) {
            if ($this->phoneNumbersMatch($allowedNumber, $number)) {
                return true;
            }
        }

        return false;
    }

    public function shouldAcceptCallPayload(array $payload, string $direction): bool
    {
        $allowedNumbers = $this->normalizedAllowedNumbers();

        if (empty($allowedNumbers)) {
            return true;
        }

        $from = $this->firstFilledPayloadValue($payload, ['from', 'from_number', 'fromNumber']);
        $to = $this->firstFilledPayloadValue($payload, ['to', 'to_number', 'toNumber']);

        if ($direction === 'outbound') {
            return $this->isAllowedBusinessNumber($from);
        }

        if ($direction === 'inbound') {
            return $this->isAllowedBusinessNumber($to);
        }

        return $this->isAllowedBusinessNumber($from)
            || $this->isAllowedBusinessNumber($to);
    }

    public function webhookUrl(bool $withSecret = false): string
    {
        $url = rtrim((string) config('app.url'), '/').'/'.ltrim(route('admin.smrtphone.webhook', [], false), '/');

        if (! $withSecret || $this->webhookSecret() === '') {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.'secret='.urlencode($this->webhookSecret());
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return [
            'webhook_secret'                    => $this->webhookSecret(),
            'create_activities'                 => $this->createActivities(),
            'save_ai_summary'                   => $this->saveAiSummary(),
            'auto_match_lead'                   => $this->autoMatchLead(),
            'create_activity_only_when_matched' => $this->createActivityOnlyWhenMatched(),
            'allowed_numbers'                   => $this->allowedNumbers(),
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function save(array $settings): void
    {
        if (! Schema::hasTable('smrtphone_settings')) {
            return;
        }

        $now = now();

        foreach ($settings as $key => $value) {
            DB::table('smrtphone_settings')->updateOrInsert(
                ['key' => $key],
                [
                    'value'      => $this->serializeValue($value),
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }

        Cache::forget(self::CACHE_KEY);
    }

    protected function setting(string $key): ?string
    {
        return $this->settings()[$key] ?? null;
    }

    /**
     * @return array<string, string|null>
     */
    protected function settings(): array
    {
        if (! Schema::hasTable('smrtphone_settings')) {
            return [];
        }

        return Cache::rememberForever(self::CACHE_KEY, function () {
            return DB::table('smrtphone_settings')
                ->pluck('value', 'key')
                ->map(fn ($value) => $value === null ? null : (string) $value)
                ->all();
        });
    }

    protected function booleanSetting(string $key, bool $default): bool
    {
        $value = $this->setting($key);

        if ($value === null || $value === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    protected function serializeValue(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($this->cleanPhoneNumbers($value), JSON_UNESCAPED_SLASHES) ?: '[]';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return trim((string) $value);
    }

    /**
     * @param  array<int, mixed>  $numbers
     * @return array<int, string>
     */
    protected function cleanPhoneNumbers(array $numbers): array
    {
        $cleaned = [];

        foreach ($numbers as $number) {
            $number = trim((string) $number);
            $normalized = $this->normalizePhoneNumber($number);

            if ($normalized === '' || isset($cleaned[$normalized])) {
                continue;
            }

            $cleaned[$normalized] = $normalized;
        }

        return array_values($cleaned);
    }

    protected function normalizePhoneNumber(?string $number): string
    {
        return preg_replace('/\D+/', '', (string) $number) ?: '';
    }

    protected function phoneNumbersMatch(string $allowedNumber, string $number): bool
    {
        if ($allowedNumber === '' || $number === '') {
            return false;
        }

        if ($allowedNumber === $number) {
            return true;
        }

        if (strlen($allowedNumber) >= 10 && strlen($number) >= 10) {
            return substr($allowedNumber, -10) === substr($number, -10);
        }

        return false;
    }

    /**
     * @param  array<int, string>  $keys
     */
    protected function firstFilledPayloadValue(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = data_get($payload, $key);

            if ($value === null || $value === '' || $value === 'null') {
                continue;
            }

            return (string) $value;
        }

        return null;
    }
}
