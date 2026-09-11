<?php

namespace Webkul\SmrtPhone\Services;

use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;

class PhoneMatcherService
{
    /**
     * Find a person by phone number using flexible digit matching.
     */
    public function findPersonByPhone(?string $phone): ?Person
    {
        $digits = $this->digitsOnly($phone);

        if (strlen($digits) < 7) {
            return null;
        }

        $candidates = [
            $digits,
            ltrim($digits, '0'),
            substr($digits, -10),
            substr($digits, -7),
        ];

        $candidates = array_values(array_unique(array_filter($candidates, fn ($value) => strlen((string) $value) >= 7)));

        return Person::query()
            ->where(function ($query) use ($candidates) {
                foreach ($candidates as $candidate) {
                    $query->orWhere('contact_numbers', 'like', '%'.$candidate.'%');
                }
            })
            ->orderByDesc('id')
            ->get()
            ->first(fn (Person $person) => $this->personHasPhone($person, $digits));
    }

    /**
     * Find the most relevant open/recent lead for a person.
     */
    public function findLeadForPerson(?Person $person): ?Lead
    {
        if (! $person) {
            return null;
        }

        return Lead::query()
            ->where('person_id', $person->id)
            ->leftJoin('lead_pipeline_stages', 'leads.lead_pipeline_stage_id', '=', 'lead_pipeline_stages.id')
            ->orderByRaw('CASE WHEN leads.closed_at IS NULL THEN 0 ELSE 1 END')
            ->orderByRaw("CASE WHEN lead_pipeline_stages.code IN ('won', 'lost') THEN 1 ELSE 0 END")
            ->orderByDesc('id')
            ->select('leads.*')
            ->first();
    }

    public function digitsOnly(?string $phone): string
    {
        return preg_replace('/\D+/', '', (string) $phone) ?: '';
    }

    protected function personHasPhone(Person $person, string $needleDigits): bool
    {
        foreach ($this->personPhoneValues($person) as $phone) {
            if ($this->phonesMatch($this->digitsOnly($phone), $needleDigits)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    protected function personPhoneValues(Person $person): array
    {
        $numbers = $person->contact_numbers;

        if (is_string($numbers)) {
            $decoded = json_decode($numbers, true);
            $numbers = json_last_error() === JSON_ERROR_NONE ? $decoded : $numbers;
        }

        if (! is_array($numbers)) {
            return array_filter([(string) $numbers]);
        }

        $values = [];

        foreach ($numbers as $number) {
            if (is_array($number)) {
                $values[] = (string) ($number['value'] ?? $number['number'] ?? '');
            } else {
                $values[] = (string) $number;
            }
        }

        return array_values(array_filter($values));
    }

    protected function phonesMatch(string $storedDigits, string $needleDigits): bool
    {
        if ($storedDigits === '' || $needleDigits === '') {
            return false;
        }

        if ($storedDigits === $needleDigits) {
            return true;
        }

        if (strlen($storedDigits) >= 10 && strlen($needleDigits) >= 10) {
            return substr($storedDigits, -10) === substr($needleDigits, -10);
        }

        return false;
    }
}
