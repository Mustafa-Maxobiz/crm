<?php

namespace Webkul\MetaLead\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Lead\Repositories\PipelineRepository;
use Webkul\Lead\Repositories\SourceRepository;
use Webkul\Lead\Repositories\TypeRepository;
use Webkul\MetaLead\Models\MetaLead;
use Webkul\MetaLead\Repositories\MetaLeadRepository;

class MetaLeadProcessorService
{
    public function __construct(
        protected MetaLeadRepository $metaLeadRepository,
        protected MetaGraphApiService $graphApiService,
        protected MetaDuplicateChecker $duplicateChecker,
        protected MetaLeadNotificationService $notificationService,
        protected LeadRepository $leadRepository,
        protected PipelineRepository $pipelineRepository,
        protected SourceRepository $sourceRepository,
        protected TypeRepository $typeRepository,
    ) {}

    public function process(string $leadgenId, ?int $pageId = null, ?array $webhookValue = null): ?MetaLead
    {
        if ($this->metaLeadRepository->findByLeadgenId($leadgenId)) {
            Log::info('Meta Lead Ads: leadgen_id already processed.', ['leadgen_id' => $leadgenId]);

            return null;
        }

        $graphData = $this->graphApiService->fetchLeadData($leadgenId);

        if (! $graphData) {
            Log::warning('Meta Lead Ads: could not fetch lead data from Graph API.', [
                'leadgen_id' => $leadgenId,
            ]);

            return $this->createPendingMetaLead($leadgenId, $webhookValue);
        }

        $fields = $this->graphApiService->parseFieldData($graphData['field_data'] ?? []);

        $receivedAt = ! empty($graphData['created_time'])
            ? Carbon::parse($graphData['created_time'])
            : now();

        $duplicate = $this->duplicateChecker->findDuplicate($fields['phone'], $fields['email']);

        $metaLeadData = [
            'leadgen_id'     => $leadgenId,
            'full_name'      => $fields['full_name'],
            'phone'          => $fields['phone'],
            'email'          => $fields['email'],
            'campaign_name'  => $graphData['campaign_name'] ?? null,
            'form_name'      => $graphData['form_name'] ?? null,
            'status'         => MetaLead::STATUS_NEW,
            'is_duplicate'   => (bool) $duplicate,
            'duplicate_of_id'=> $duplicate?->id,
            'raw_payload'    => $graphData,
            'received_at'    => $receivedAt,
        ];

        if ($duplicate) {
            $metaLead = $this->metaLeadRepository->create($metaLeadData);

            $this->assignDefaultUsers($metaLead);

            Log::info('Meta Lead Ads: duplicate lead flagged.', [
                'leadgen_id'     => $leadgenId,
                'duplicate_of'   => $duplicate->id,
            ]);

            return $metaLead;
        }

        $lead = $this->createCrmLead($fields, $graphData, $receivedAt);

        $metaLeadData['lead_id'] = $lead?->id;

        $metaLead = $this->metaLeadRepository->create($metaLeadData);

        $this->assignDefaultUsers($metaLead);

        $this->notificationService->notifyTeam($metaLead->fresh('users'));

        return $metaLead;
    }

    protected function createCrmLead(array $fields, array $graphData, Carbon $receivedAt)
    {
        $pipeline = $this->pipelineRepository->getDefaultPipeline();
        $stage = $pipeline->stages()->first();
        $source = $this->sourceRepository->findOneByField('name', config('meta_lead.lead_source_name'))
            ?? $this->sourceRepository->first();
        $type = $this->typeRepository->first();

        $person = [];

        if ($fields['full_name']) {
            $person['name'] = $fields['full_name'];
        }

        if ($fields['email']) {
            $person['emails'] = [['value' => $fields['email'], 'label' => 'work']];
        }

        if ($fields['phone']) {
            $person['contact_numbers'] = [['value' => $fields['phone'], 'label' => 'work']];
        }

        $title = $fields['full_name']
            ? 'Meta Lead: '.$fields['full_name']
            : 'Meta Lead from '.($graphData['form_name'] ?? 'Lead Form');

        $description = collect([
            'Campaign' => $graphData['campaign_name'] ?? null,
            'Form'     => $graphData['form_name'] ?? null,
            'Source'   => config('meta_lead.lead_source_name'),
            'Received' => $receivedAt->toDateTimeString(),
        ])->filter()->map(fn ($value, $key) => "{$key}: {$value}")->implode("\n");

        $data = [
            'entity_type'            => 'leads',
            'title'                  => $title,
            'description'            => $description,
            'status'                 => 1,
            'lead_value'             => 0,
            'lead_pipeline_id'       => $pipeline->id,
            'lead_pipeline_stage_id' => $stage->id,
            'lead_source_id'         => $source?->id,
            'lead_type_id'           => $type?->id,
            'source_link'            => $graphData['id'] ?? null,
            'person'                 => $person,
        ];

        if ($userId = config('meta_lead.default_user_id')) {
            $data['user_id'] = $userId;
        }

        Event::dispatch('lead.create.before');

        $lead = $this->leadRepository->create($data);

        Event::dispatch('lead.create.after', $lead);

        return $lead;
    }

    protected function createPendingMetaLead(string $leadgenId, ?array $webhookValue = null): MetaLead
    {
        $receivedAt = ! empty($webhookValue['created_time'])
            ? Carbon::createFromTimestamp((int) $webhookValue['created_time'])
            : now();

        $isTestWebhook = $leadgenId === '444444444444';

        return $this->metaLeadRepository->create([
            'leadgen_id'    => $leadgenId,
            'full_name'     => $isTestWebhook ? 'Meta Test Webhook (sample)' : 'Lead pending — fetch failed',
            'status'        => MetaLead::STATUS_NEW,
            'received_at'   => $receivedAt,
            'raw_payload'   => [
                'webhook'           => $webhookValue,
                'graph_fetch_failed'=> true,
            ],
        ])->tap(fn (MetaLead $metaLead) => $this->assignDefaultUsers($metaLead));
    }

    protected function assignDefaultUsers(MetaLead $metaLead): void
    {
        $userIds = config('meta_lead.default_assigned_user_ids', []);

        if (empty($userIds) && config('meta_lead.default_user_id')) {
            $userIds = [(int) config('meta_lead.default_user_id')];
        }

        if (! empty($userIds)) {
            $this->metaLeadRepository->syncAssignedUsers($metaLead->id, $userIds);
        }
    }
}
