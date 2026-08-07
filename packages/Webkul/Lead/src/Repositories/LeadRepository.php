<?php

namespace Webkul\Lead\Repositories;

use Carbon\Carbon;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Attribute\Repositories\AttributeValueRepository;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Core\Eloquent\Repository;
use Webkul\Lead\Contracts\Lead;
use Webkul\Lead\Models\Lead as LeadModel;
use Webkul\Lead\Services\FollowupScheduleService;

class LeadRepository extends Repository
{
    /**
     * Searchable fields.
     */
    protected $fieldSearchable = [
        'id',
        'title',
        'description',
        'source_link',
        'lead_value',
        'status',
        'user_id',
        'user.name',
        'person_id',
        'person.name',
        'organization_id',
        'organization.name',
        'source.name',
        'lead_source_id',
        'lead_type_id',
        'lead_pipeline_id',
        'lead_pipeline_stage_id',
        'created_at',
        'closed_at',
        'expected_close_date',
    ];

    /**
     * Create a new repository instance.
     *
     * @return void
     */
    public function __construct(
        protected StageRepository $stageRepository,
        protected PersonRepository $personRepository,
        protected ProductRepository $productRepository,
        protected AttributeRepository $attributeRepository,
        protected AttributeValueRepository $attributeValueRepository,
        protected FollowupScheduleService $followupScheduleService,
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
        return Lead::class;
    }

    /**
     * Get leads query.
     *
     * @param  int  $pipelineId
     * @param  int  $pipelineStageId
     * @param  string  $term
     * @param  string  $createdAtRange
     * @return mixed
     */
    public function getLeadsQuery($pipelineId, $pipelineStageId, $term, $createdAtRange)
    {
        return $this->with([
            'attribute_values',
            'pipeline',
            'stage',
        ])->scopeQuery(function ($query) use ($pipelineId, $pipelineStageId, $term, $createdAtRange) {
            $query = $query->select(
                'leads.id as id',
                'leads.created_at as created_at',
                'title',
                'lead_value',
                'persons.name as person_name',
                'leads.person_id as person_id',
                'lead_pipelines.id as lead_pipeline_id',
                'lead_pipeline_stages.name as status',
                'lead_pipeline_stages.id as lead_pipeline_stage_id'
            )
                ->addSelect(DB::raw('DATEDIFF('.DB::getTablePrefix().'leads.created_at + INTERVAL lead_pipelines.rotten_days DAY, now()) as rotten_days'))
                ->leftJoin('persons', 'leads.person_id', '=', 'persons.id')
                ->leftJoin('lead_pipelines', 'leads.lead_pipeline_id', '=', 'lead_pipelines.id')
                ->leftJoin('lead_pipeline_stages', 'leads.lead_pipeline_stage_id', '=', 'lead_pipeline_stages.id')
                ->where('title', 'like', "%$term%")
                ->where('leads.lead_pipeline_id', $pipelineId)
                ->where('leads.lead_pipeline_stage_id', $pipelineStageId)
                ->when($createdAtRange, function ($query) use ($createdAtRange) {
                    return $query->whereBetween('leads.created_at', $createdAtRange);
                });

            return app(\Webkul\Lead\Services\SourceAccessService::class)
                ->applyLeadOwnerVisibilityScope($query);
        });
    }

    /**
     * Resolve optional lead company from payload and keep title/person in sync.
     */
    private function resolveLeadOrganization(array $data): array
    {
        $hasExplicitCompany = array_key_exists('organization_id', $data)
            || array_key_exists('organization_name', $data)
            || array_key_exists('companies', $data);

        // Legacy text field mapped to create-or-find by name.
        if (! empty($data['companies']) && empty($data['organization_name']) && empty($data['organization_id'])) {
            $data['organization_name'] = trim((string) $data['companies']);
        }

        $organizationId = $data['organization_id'] ?? null;
        $organizationName = filled($data['organization_name'] ?? null)
            ? trim((string) $data['organization_name'])
            : null;

        $personHasCompany = isset($data['person'])
            && is_array($data['person'])
            && (
                array_key_exists('organization_id', $data['person'])
                || array_key_exists('organization_name', $data['person'])
            );

        // Contact company wins when the user edited person company (edit modal path).
        if ($personHasCompany) {
            $personOrganizationId = $data['person']['organization_id'] ?? null;
            $personOrganizationName = filled($data['person']['organization_name'] ?? null)
                ? trim((string) $data['person']['organization_name'])
                : null;

            if ($personOrganizationId === '') {
                $personOrganizationId = null;
            }

            $organizationId = $personOrganizationId;
            $organizationName = $personOrganizationName;
            $hasExplicitCompany = true;
        }

        if ($organizationId === '') {
            $organizationId = null;
        }

        $organization = null;

        if ($organizationName) {
            $organization = $this->personRepository->fetchOrCreateOrganizationByName($organizationName);
        } elseif ($organizationId) {
            $organization = app(\Webkul\Contact\Repositories\OrganizationRepository::class)->find($organizationId);
        }

        unset($data['organization_name'], $data['companies']);

        if ($organization) {
            $data['organization_id'] = $organization->id;

            // Keep an explicit Title (main leads). Only fall back to company when title is empty.
            if (! filled($data['title'] ?? null)) {
                $data['title'] = $organization->name;
            }

            if (isset($data['person']) && is_array($data['person'])) {
                $data['person']['organization_id'] = $organization->id;
                unset($data['person']['organization_name']);
            }
        } elseif ($hasExplicitCompany) {
            $data['organization_id'] = null;

            if (isset($data['person']) && is_array($data['person'])) {
                $data['person']['organization_id'] = null;
                unset($data['person']['organization_name']);
            }
        }

        return $data;
    }

    /**
     * Fill title from company only when the lead has no title yet.
     */
    private function fillTitleFromCompanyName(array $data, ?string $companyName): array
    {
        if (! filled($data['title'] ?? null) && filled($companyName)) {
            $data['title'] = $companyName;
        }

        return $data;
    }

    /**
     * Create.
     *
     * @return \Webkul\Lead\Contracts\Lead
     */
    public function create(array $data)
    {
        $data = $this->resolveLeadOrganization($data);

        /**
         * If a person is provided, create or update the person and set the `person_id`.
         */
        if (isset($data['person'])) {
            if (! empty($data['person']['id'])) {
                $person = $this->personRepository->findOrFail($data['person']['id']);

                $person = $this->syncExistingPersonCompany($person, $data['person']);

                $data['person_id'] = $person->id;

                if (empty($data['organization_id']) && $person->organization_id) {
                    $data['organization_id'] = $person->organization_id;
                    $data = $this->fillTitleFromCompanyName($data, $person->organization?->name);
                }
            } else {
                // Check if person data has any meaningful values
                $hasPersonData = $this->hasPersonData($data['person']);
                
                if ($hasPersonData) {
                    $personPayload = $data['person'];

                    // Ensure the unique identity calculation includes organization_id (e.g. org_id|phone).
                    if (empty($personPayload['organization_id']) && ! empty($data['organization_id'])) {
                        $personPayload['organization_id'] = $data['organization_id'];
                    }

                    $existingPerson = $this->personRepository->findByUniqueIdentity($personPayload);

                    if ($existingPerson) {
                        $data['person_id'] = $existingPerson->id;

                        if (empty($data['organization_id']) && $existingPerson->organization_id) {
                            $data['organization_id'] = $existingPerson->organization_id;
                            $data = $this->fillTitleFromCompanyName($data, $existingPerson->organization?->name);
                        }
                    } else {
                        $person = $this->personRepository->create(array_merge($data['person'], [
                            'entity_type' => 'persons',
                        ]));
                        $data['person_id'] = $person->id;

                        if (empty($data['organization_id']) && $person->organization_id) {
                            $data['organization_id'] = $person->organization_id;
                            $data = $this->fillTitleFromCompanyName($data, $person->organization?->name);
                        }
                    }
                } else {
                    // No person data provided, set person_id to null
                    $data['person_id'] = null;
                }
            }
        }

        if (empty($data['expected_close_date'])) {
            $data['expected_close_date'] = null;
        }

        if (empty($data['organization_id'])) {
            $data['organization_id'] = null;
        }

        if (empty($data['title'])) {
            $data['title'] = $data['title'] ?? '';
        }

        $shouldScheduleFollowup = array_key_exists('schedule_followup', $data)
            ? filter_var($data['schedule_followup'], FILTER_VALIDATE_BOOLEAN)
            : true;

        if (! $shouldScheduleFollowup) {
            $data['next_followup_date'] = null;
        } elseif (empty($data['next_followup_date'])) {
            $data['next_followup_date'] = $this->followupScheduleService
                ->calculateNext(null, Carbon::now(), 0);
        }

        unset($data['schedule_followup']);

        // Convert empty lead_sub_source_id to null
        if (isset($data['lead_sub_source_id']) && empty($data['lead_sub_source_id'])) {
            $data['lead_sub_source_id'] = null;
        }

        $lead = parent::create(array_merge([
            'lead_pipeline_id'       => 1,
            'lead_pipeline_stage_id' => 1,
        ], $data));

        $this->attributeValueRepository->save(array_merge($data, [
            'entity_id'         => $lead->id,
            'next_followup_date'=> $lead->next_followup_date,
        ]));

        if (isset($data['products'])) {
            foreach ($data['products'] as $product) {
                $this->productRepository->create(array_merge($product, [
                    'lead_id' => $lead->id,
                    'amount'  => $product['price'] * $product['quantity'],
                ]));
            }
        }

        return $lead;
    }

    /**
     * Update.
     *
     * @param  int  $id
     * @param  array|\Illuminate\Database\Eloquent\Collection  $attributes
     * @return \Webkul\Lead\Contracts\Lead
     */
    public function update(array $data, $id, $attributes = [])
    {
        $data = $this->resolveLeadOrganization($data);

        /**
         * If a person is provided, create or update the person and set the `person_id`.
         * Be cautious, as a lead can be updated without providing person data.
         * For example, in the lead Kanban section, when switching stages, only the stage will be updated.
         */
        if (isset($data['person'])) {
            if (! empty($data['person']['id'])) {
                $person = $this->personRepository->findOrFail($data['person']['id']);

                $person = $this->syncExistingPersonCompany($person, $data['person'], $id);

                $data['person_id'] = $person->id;

                if (
                    ! array_key_exists('organization_id', $data)
                    && $person->organization_id
                ) {
                    $data['organization_id'] = $person->organization_id;
                    $data = $this->fillTitleFromCompanyName($data, $person->organization?->name);
                }
            } else {
                // Check if person data has any meaningful values
                $hasPersonData = $this->hasPersonData($data['person']);
                
                if ($hasPersonData) {
                    $personPayload = $data['person'];

                    // Ensure the unique identity calculation includes organization_id (e.g. org_id|phone).
                    if (empty($personPayload['organization_id']) && ! empty($data['organization_id'])) {
                        $personPayload['organization_id'] = $data['organization_id'];
                    }

                    $existingPerson = $this->personRepository->findByUniqueIdentity($personPayload);

                    if ($existingPerson) {
                        $data['person_id'] = $existingPerson->id;

                        if (empty($data['organization_id']) && $existingPerson->organization_id) {
                            $data['organization_id'] = $existingPerson->organization_id;
                            $data = $this->fillTitleFromCompanyName($data, $existingPerson->organization?->name);
                        }
                    } else {
                        $person = $this->personRepository->create(array_merge($data['person'], [
                            'entity_type' => 'persons',
                        ]));
                        $data['person_id'] = $person->id;

                        if (empty($data['organization_id']) && $person->organization_id) {
                            $data['organization_id'] = $person->organization_id;
                            $data = $this->fillTitleFromCompanyName($data, $person->organization?->name);
                        }
                    }
                } else {
                    // No person data provided, set person_id to null
                    $data['person_id'] = null;
                }
            }
        }

        // When person company changed, mirror company onto lead; keep an explicit title.
        if (
            isset($person)
            && array_key_exists('organization_id', $data['person'] ?? [])
            && ! array_key_exists('organization_name', $data)
        ) {
            $data['organization_id'] = $person->organization_id;
            $data = $this->fillTitleFromCompanyName($data, $person->organization?->name);
        }

        if (isset($data['lead_pipeline_stage_id'])) {
            $stage = $this->stageRepository->find($data['lead_pipeline_stage_id']);

            if (in_array($stage->code, ['won', 'lost'])) {
                $data['closed_at'] = $data['closed_at'] ?? Carbon::now();
            } else {
                $data['closed_at'] = null;
            }
        }

        if (empty($data['expected_close_date'])) {
            $data['expected_close_date'] = null;
        }

        if (array_key_exists('organization_id', $data) && empty($data['organization_id'])) {
            $data['organization_id'] = null;
        }

        if (array_key_exists('next_followup_date', $data) && empty($data['next_followup_date'])) {
            $existingLead = $this->find($id);

            $data['next_followup_date'] = $this->followupScheduleService->calculateNext(
                $existingLead,
                Carbon::now(),
                (int) ($existingLead->followup_count ?? 0)
            );
        }

        // Convert empty lead_sub_source_id to null
        if (isset($data['lead_sub_source_id']) && empty($data['lead_sub_source_id'])) {
            $data['lead_sub_source_id'] = null;
        }

        $existingLead = isset($existingLead) ? $existingLead : $this->find($id);
        $oldCompany = $existingLead?->organization?->name;
        $loggedViaPerson = isset($person) && (
            array_key_exists('organization_id', $data['person'] ?? [])
            || filled(($data['person']['organization_name'] ?? null))
        );

        $lead = parent::update($data, $id);

        if (! $loggedViaPerson && array_key_exists('organization_id', $data)) {
            $lead = $lead->fresh(['organization']) ?? $lead;
            $newCompany = $lead->organization?->name;

            if ($oldCompany !== $newCompany) {
                LeadModel::storeSystemActivity(
                    $lead,
                    'Company',
                    $oldCompany,
                    $newCompany
                );
            }
        }

        /**
         * If attributes are provided, only save the provided attributes and return.
         * A collection of attributes may also be provided, which will be treated as valid,
         * regardless of whether it is empty or not.
         */
        if (! empty($attributes)) {
            /**
             * If attributes are provided as an array, then fetch the attributes from the database;
             * otherwise, use the provided collection of attributes.
             */
            if (is_array($attributes)) {
                $conditions = ['entity_type' => $data['entity_type']];

                if (isset($data['quick_add'])) {
                    $conditions['quick_add'] = 1;
                }

                $attributes = $this->attributeRepository->where($conditions)
                    ->whereIn('code', $attributes)
                    ->get();
            }

            $this->attributeValueRepository->save(array_merge($data, [
                'entity_id' => $lead->id,
            ]), $attributes);

            return $lead;
        }

        $this->attributeValueRepository->save(array_merge($data, [
            'entity_id' => $lead->id,
        ]));

        $previousProductIds = $lead->products()->pluck('id');

        if (isset($data['products'])) {
            foreach ($data['products'] as $productId => $productInputs) {
                if (Str::contains($productId, 'product_')) {
                    $this->productRepository->create(array_merge([
                        'lead_id' => $lead->id,
                    ], $productInputs));
                } else {
                    if (is_numeric($index = $previousProductIds->search($productId))) {
                        $previousProductIds->forget($index);
                    }

                    $this->productRepository->update($productInputs, $productId);
                }
            }
        }

        foreach ($previousProductIds as $productId) {
            $this->productRepository->delete($productId);
        }

        return $lead;
    }

    /**
     * Check if person data has any meaningful values.
     *
     * @param  array  $personData
     * @return bool
     */
    private function hasPersonData(array $personData): bool
    {
        // Check if name is provided and not empty
        if (!empty($personData['name'])) {
            return true;
        }

        // Check if any email has a value
        if (isset($personData['emails']) && is_array($personData['emails'])) {
            foreach ($personData['emails'] as $email) {
                if (!empty($email['value'])) {
                    return true;
                }
            }
        }

        // Check if any contact number has a value
        if (isset($personData['contact_numbers']) && is_array($personData['contact_numbers'])) {
            foreach ($personData['contact_numbers'] as $number) {
                if (!empty($number['value'])) {
                    return true;
                }
            }
        }

        // Check if job title is provided
        if (!empty($personData['job_title'])) {
            return true;
        }

        // Check if organization is provided
        if (
            ! empty($personData['organization_id'])
            || ! empty($personData['organization_name'])
        ) {
            return true;
        }

        // Check if website is provided
        if (! empty($personData['website'])) {
            return true;
        }

        // Check if any address field has a value
        if (! empty($personData['address']) && is_array($personData['address'])) {
            foreach ($personData['address'] as $part) {
                if (! empty($part)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Update company/address/website on an existing person when submitted from lead edit.
     */
    private function syncExistingPersonCompany($person, array $personData, ?int $leadId = null)
    {
        $hasOrganizationId = array_key_exists('organization_id', $personData);
        $hasOrganizationName = filled($personData['organization_name'] ?? null);
        $hasAddress = array_key_exists('address', $personData);
        $hasWebsite = array_key_exists('website', $personData);

        if (
            ! $hasOrganizationId
            && ! $hasOrganizationName
            && ! $hasAddress
            && ! $hasWebsite
        ) {
            return $person;
        }

        $payload = [
            'entity_type' => 'persons',
        ];

        if ($hasOrganizationName) {
            $payload['organization_name'] = trim((string) $personData['organization_name']);
            unset($payload['organization_id']);
        } elseif ($hasOrganizationId) {
            $payload['organization_id'] = $personData['organization_id'] ?: null;
        }

        if ($hasAddress) {
            $payload['address'] = $personData['address'];
        }

        if ($hasWebsite) {
            $payload['website'] = $personData['website'] ?: null;
        }

        $oldCompany = $person->organization?->name;

        $person = $this->personRepository->update($payload, $person->id);

        $person = $person->fresh(['organization']) ?? $person;
        $newCompany = $person->organization?->name;

        if ($leadId) {
            $lead = $this->find($leadId);
            $currentTitle = trim((string) ($lead?->title ?? ''));

            $payload = [
                'organization_id' => $person->organization_id,
            ];

            // Sync title from company only when empty or still mirroring the old company name.
            if (
                $currentTitle === ''
                || ($oldCompany !== null && $currentTitle === $oldCompany)
            ) {
                $payload['title'] = $newCompany ?: '';
            }

            $this->getModel()->where('id', $leadId)->update($payload);
        }

        if ($leadId && $oldCompany !== $newCompany) {
            $lead = $this->find($leadId);

            if ($lead) {
                LeadModel::storeSystemActivity(
                    $lead,
                    'Company',
                    $oldCompany,
                    $newCompany
                );
            }
        }

        return $person;
    }
}