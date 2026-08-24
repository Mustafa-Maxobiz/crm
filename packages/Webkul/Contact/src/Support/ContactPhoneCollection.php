<?php

namespace Webkul\Contact\Support;

class ContactPhoneCollection
{
    /**
     * Parse a CSV/API phone cell into the person contact_numbers shape.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function fromImportValue(mixed $raw): array
    {
        $items = [];
        $seen = [];

        foreach (self::tokens($raw) as $token) {
            $compare = self::compareKey($token);

            if ($compare === null || isset($seen[$compare])) {
                continue;
            }

            $seen[$compare] = true;
            $items[] = [
                'value' => $token,
                'label' => 'work',
            ];
        }

        return $items;
    }

    /**
     * Accept existing contact_numbers, phones[], or a single phone field.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, array{value: string, label: string}>
     */
    public static function fromRequestPayload(array $payload): array
    {
        $personNumbers = $payload['person']['contact_numbers'] ?? null;

        if (is_array($personNumbers) && self::hasStoredValues($personNumbers)) {
            return self::sanitizeStored($personNumbers);
        }

        if (array_key_exists('phones', $payload) && is_array($payload['phones'])) {
            return self::fromImportValue($payload['phones']);
        }

        if (filled($payload['phone'] ?? null)) {
            return self::fromImportValue($payload['phone']);
        }

        return is_array($personNumbers) ? self::sanitizeStored($personNumbers) : [];
    }

    /**
     * @return array<int, string>
     */
    public static function tokens(mixed $raw): array
    {
        if (is_array($raw)) {
            $tokens = [];

            foreach ($raw as $item) {
                if (is_array($item)) {
                    $value = trim((string) ($item['value'] ?? ''));
                } else {
                    $value = trim((string) $item);
                }

                if ($value !== '') {
                    $tokens[] = $value;
                }
            }

            return $tokens;
        }

        $value = trim((string) $raw);

        if ($value === '') {
            return [];
        }

        return collect(explode(',', $value))
            ->map(fn ($token) => trim((string) $token))
            ->filter(fn ($token) => $token !== '')
            ->values()
            ->all();
    }

    /**
     * Tokens that cannot be stored as phones (no digits).
     *
     * @return array<int, string>
     */
    public static function invalidTokens(mixed $raw): array
    {
        $invalid = [];

        foreach (self::tokens($raw) as $token) {
            if (self::compareKey($token) === null) {
                $invalid[] = $token;
            }
        }

        return $invalid;
    }

    /**
     * Digit-only key used for duplicate comparison and lookup.
     */
    public static function compareKey(?string $phone): ?string
    {
        if (! filled($phone)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $phone);

        if ($digits === null || $digits === '') {
            return null;
        }

        return $digits;
    }

    /**
     * @return array<int, string>
     */
    public static function values(mixed $contactNumbers): array
    {
        if (is_string($contactNumbers)) {
            $decoded = json_decode($contactNumbers, true);
            $contactNumbers = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($contactNumbers)) {
            return [];
        }

        return collect($contactNumbers)
            ->map(function ($item) {
                if (is_array($item)) {
                    return trim((string) ($item['value'] ?? ''));
                }

                return trim((string) $item);
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function compareKeys(mixed $raw): array
    {
        $keys = [];

        foreach (self::tokens($raw) as $token) {
            $key = self::compareKey($token);

            if ($key !== null) {
                $keys[] = $key;
            }
        }

        return array_values(array_unique($keys));
    }

    public static function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }

    /**
     * @param  array<int, mixed>  $contactNumbers
     * @return array<int, array{value: string, label: string}>
     */
    public static function sanitizeStored(array $contactNumbers): array
    {
        $items = [];
        $seen = [];

        foreach ($contactNumbers as $item) {
            $value = is_array($item)
                ? trim((string) ($item['value'] ?? ''))
                : trim((string) $item);

            if ($value === '') {
                continue;
            }

            $compare = self::compareKey($value);

            if ($compare !== null) {
                if (isset($seen[$compare])) {
                    continue;
                }

                $seen[$compare] = true;
            }

            $items[] = [
                'value' => $value,
                'label' => is_array($item) && filled($item['label'] ?? null)
                    ? (string) $item['label']
                    : 'work',
            ];
        }

        return $items;
    }

    /**
     * @param  array<int, mixed>  $contactNumbers
     */
    protected static function hasStoredValues(array $contactNumbers): bool
    {
        foreach ($contactNumbers as $item) {
            $value = is_array($item)
                ? trim((string) ($item['value'] ?? ''))
                : trim((string) $item);

            if ($value !== '') {
                return true;
            }
        }

        return false;
    }
}
