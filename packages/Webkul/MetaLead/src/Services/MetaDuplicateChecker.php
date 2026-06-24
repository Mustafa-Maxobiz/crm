<?php

namespace Webkul\MetaLead\Services;

use Webkul\Contact\Repositories\PersonRepository;
use Webkul\MetaLead\Models\MetaLead;
use Webkul\MetaLead\Repositories\MetaLeadRepository;

class MetaDuplicateChecker
{
    public function __construct(
        protected MetaLeadRepository $metaLeadRepository,
        protected PersonRepository $personRepository,
    ) {}

    public function findDuplicate(?string $phone, ?string $email): ?MetaLead
    {
        if ($phone) {
            $existing = $this->metaLeadRepository
                ->getModel()
                ->where('phone', $phone)
                ->where('is_duplicate', false)
                ->orderByDesc('id')
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        if ($email) {
            $existing = $this->metaLeadRepository
                ->getModel()
                ->where('email', $email)
                ->where('is_duplicate', false)
                ->orderByDesc('id')
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        if ($email) {
            $person = $this->personRepository
                ->getModel()
                ->where('emails', 'like', '%'.$email.'%')
                ->first();

            if ($person) {
                $metaLead = $this->metaLeadRepository
                    ->getModel()
                    ->whereIn('lead_id', $person->leads()->pluck('id'))
                    ->where('is_duplicate', false)
                    ->orderByDesc('id')
                    ->first();

                if ($metaLead) {
                    return $metaLead;
                }
            }
        }

        if ($phone) {
            $person = $this->personRepository
                ->getModel()
                ->where('contact_numbers', 'like', '%'.$phone.'%')
                ->first();

            if ($person) {
                $metaLead = $this->metaLeadRepository
                    ->getModel()
                    ->whereHas('lead', fn ($query) => $query->where('person_id', $person->id))
                    ->where('is_duplicate', false)
                    ->orderByDesc('id')
                    ->first();

                if ($metaLead) {
                    return $metaLead;
                }
            }
        }

        return null;
    }
}
