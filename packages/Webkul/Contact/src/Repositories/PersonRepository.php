<?php

namespace Webkul\Contact\Repositories;

use Illuminate\Container\Container;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Attribute\Repositories\AttributeValueRepository;
use Webkul\Contact\Contracts\Person;
use Webkul\Core\Eloquent\Repository;
use Webkul\Lead\Services\UsStateTimezoneService;

class PersonRepository extends Repository
{
    /**
     * Searchable fields.
     */
    protected $fieldSearchable = [
        'name',
        'emails',
        'contact_numbers',
        'organization_id',
        'job_title',
        'address_line',
        'city',
        'state',
        'country',
        'postcode',
        'timezone',
        'organization.name',
        'user_id',
        'user.name',
    ];

    /**
     * Create a new repository instance.
     *
     * @return void
     */
    public function __construct(
        protected AttributeRepository $attributeRepository,
        protected AttributeValueRepository $attributeValueRepository,
        protected OrganizationRepository $organizationRepository,
        protected UsStateTimezoneService $usStateTimezoneService,
        Container $container
    ) {
        parent::__construct($container);
    }

    /**
     * Specify model class name.
     *
     * @return mixed
     */
    public function model()
    {
        return Person::class;
    }

    /**
     * Create.
     *
     * @return \Webkul\Contact\Contracts\Person
     */
    public function create(array $data)
    {
        $data = $this->sanitizeRequestedPersonData($data);

        if (! empty($data['organization_name'])) {
            $organization = $this->fetchOrCreateOrganizationByName($data['organization_name']);

            $data['organization_id'] = $organization->id;

            unset($data['organization_name']);

            // Sanitize runs before org resolution; rebuild identity now that org_id is known.
            $data = $this->rebuildUniqueId($data);
        }

        if (isset($data['user_id'])) {
            $data['user_id'] = $data['user_id'] ?: null;
        }

        $attributeData = $this->attributePayloadWithoutAddress($data);

        $person = parent::create($data);

        $this->attributeValueRepository->save(array_merge($attributeData, [
            'entity_id' => $person->id,
        ]));

        return $person;
    }

    /**
     * Update.
     *
     * @return \Webkul\Contact\Contracts\Person
     */
    public function update(array $data, $id, $attributes = [])
    {
        $existing = $this->find($id);

        $touchesIdentity = array_key_exists('organization_id', $data)
            || ! empty($data['organization_name'])
            || array_key_exists('emails', $data)
            || array_key_exists('contact_numbers', $data)
            || array_key_exists('user_id', $data);

        if ($existing && $touchesIdentity) {
            $data['emails'] = $data['emails'] ?? $existing->emails;
            $data['contact_numbers'] = $data['contact_numbers'] ?? $existing->contact_numbers;

            if (! array_key_exists('user_id', $data)) {
                $data['user_id'] = $existing->user_id;
            }
        }

        $data = $this->sanitizeRequestedPersonData($data);

        if ($existing && ! $touchesIdentity) {
            unset($data['unique_id']);
        }

        if (array_key_exists('user_id', $data)) {
            $data['user_id'] = empty($data['user_id']) ? null : $data['user_id'];
        }

        if (! empty($data['organization_name'])) {
            $organization = $this->fetchOrCreateOrganizationByName($data['organization_name']);

            $data['organization_id'] = $organization->id;

            unset($data['organization_name']);

            // Rebuild unique_id now that organization_id is resolved.
            $data = $this->rebuildUniqueId($data);
        }

        $attributeData = $this->attributePayloadWithoutAddress($data);

        $person = parent::update($data, $id);

        /**
         * If attributes are provided then only save the provided attributes and return.
         */
        if (! empty($attributes)) {
            $attributes = array_values(array_filter(
                $attributes,
                fn ($attribute) => $attribute !== 'address'
            ));

            if (empty($attributes)) {
                return $person;
            }

            $conditions = ['entity_type' => $data['entity_type'] ?? 'persons'];

            if (isset($data['quick_add'])) {
                $conditions['quick_add'] = 1;
            }

            $attributeModels = $this->attributeRepository->where($conditions)
                ->whereIn('code', $attributes)
                ->get();

            $this->attributeValueRepository->save(array_merge($attributeData, [
                'entity_id' => $person->id,
            ]), $attributeModels);

            return $person;
        }

        $this->attributeValueRepository->save(array_merge($attributeData, [
            'entity_id' => $person->id,
        ]));

        return $person;
    }

    /**
     * Retrieves customers count based on date.
     *
     * @return int
     */
    public function getCustomerCount($startDate, $endDate)
    {
        return $this
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get()
            ->count();
    }

    /**
     * Fetch or create an organization.
     */
    public function fetchOrCreateOrganizationByName(string $organizationName)
    {
        $organization = $this->organizationRepository->findOneWhere([
            'name' => $organizationName,
        ]);

        return $organization ?: $this->organizationRepository->create([
            'entity_type' => 'organizations',
            'name'        => $organizationName,
        ]);
    }

    /**
     * Find an existing person matching organization/email/phone unique identity.
     */
    public function findByUniqueIdentity(array $data)
    {
        $sanitized = $this->sanitizeRequestedPersonData($data);

        if (empty($sanitized['unique_id'])) {
            return null;
        }

        return $this->findOneWhere([
            'unique_id' => $sanitized['unique_id'],
        ]);
    }

    /**
     * Sanitize requested person data and return the clean array.
     */
    private function sanitizeRequestedPersonData(array $data): array
    {
        if (
            array_key_exists('organization_id', $data)
            && empty($data['organization_id'])
        ) {
            $data['organization_id'] = null;
        }

        if (isset($data['contact_numbers'])) {
            $data['contact_numbers'] = collect($data['contact_numbers'])
                ->filter(fn ($number) => ! is_null($number['value'] ?? null))
                ->values()
                ->toArray();
        }

        $data = $this->rebuildUniqueId($data);

        if (array_key_exists('website', $data) && empty($data['website'])) {
            $data['website'] = null;
        }

        return $this->mapAddressFieldsToColumns($data);
    }

    /**
     * Build persons.unique_id from owner / company / email / phone parts.
     */
    private function rebuildUniqueId(array $data): array
    {
        $uniqueIdParts = array_filter([
            $data['user_id'] ?? null,
            $data['organization_id'] ?? null,
            $data['emails'][0]['value'] ?? null,
        ]);

        if (! empty($data['contact_numbers'][0]['value'])) {
            $uniqueIdParts[] = $data['contact_numbers'][0]['value'];
        }

        $data['unique_id'] = empty($uniqueIdParts)
            ? 'person_'.uniqid()
            : implode('|', $uniqueIdParts);

        return $data;
    }

    /**
     * Map nested `address[...]` payload (and flat fields) onto persons columns.
     */
    private function mapAddressFieldsToColumns(array $data): array
    {
        if (array_key_exists('address', $data)) {
            if (is_array($data['address'])) {
                $hasAddress = collect($data['address'])->contains(fn ($part) => filled($part));

                if ($hasAddress) {
                    $data['address_line'] = trim((string) ($data['address']['address'] ?? '')) ?: null;
                    $data['city'] = trim((string) ($data['address']['city'] ?? '')) ?: null;
                    $data['state'] = trim((string) ($data['address']['state'] ?? '')) ?: null;
                    $data['country'] = trim((string) ($data['address']['country'] ?? '')) ?: null;
                    $data['postcode'] = trim((string) ($data['address']['postcode'] ?? '')) ?: null;
                } else {
                    $data['address_line'] = null;
                    $data['city'] = null;
                    $data['state'] = null;
                    $data['country'] = null;
                    $data['postcode'] = null;
                    $data['timezone'] = null;
                }
            } elseif ($data['address'] === null) {
                $data['address_line'] = null;
                $data['city'] = null;
                $data['state'] = null;
                $data['country'] = null;
                $data['postcode'] = null;
                $data['timezone'] = null;
            }

            unset($data['address']);
        }

        foreach (['address_line', 'city', 'state', 'country', 'postcode'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = filled($data[$field] ?? null) ? trim((string) $data[$field]) : null;
            }
        }

        if (array_key_exists('state', $data)) {
            $data['timezone'] = $data['state']
                ? $this->usStateTimezoneService->timezoneForState($data['state'])
                : null;
        }

        return $data;
    }

    /**
     * EAV payload without address (address now lives on persons columns).
     */
    private function attributePayloadWithoutAddress(array $data): array
    {
        unset(
            $data['address'],
            $data['address_line'],
            $data['city'],
            $data['state'],
            $data['country'],
            $data['postcode'],
            $data['timezone']
        );

        return $data;
    }
}
