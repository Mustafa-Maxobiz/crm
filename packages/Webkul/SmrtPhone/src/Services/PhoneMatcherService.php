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

        foreach ($candidates as $candidate) {
            $person = Person::query()
                ->where('contact_numbers', 'like', '%'.$candidate.'%')
                ->orderByDesc('id')
                ->first();

            if ($person) {
                return $person;
            }
        }

        return null;
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
            ->orderByDesc('id')
            ->first();
    }

    public function digitsOnly(?string $phone): string
    {
        return preg_replace('/\D+/', '', (string) $phone) ?: '';
    }
}
