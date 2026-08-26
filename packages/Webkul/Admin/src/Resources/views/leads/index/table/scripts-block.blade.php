@php
    $leadsIndexRoute = $leadsIndexRoute ?? 'admin.leads.index';

    $canEditLeadValue = lead_variant() === 'main'
        && bouncer()->hasPermission(lead_permission('edit'));

    $lockedLeadAttributeCodes = [
        'lead_source_id',
        'lead_type_id',
        'lead_sub_source_id',
        'industry',
    ];

    $modalExcludedAttributeCodes = [
        'lead_type_id',
        'user_id',
        'lead_pipeline_id',
        'lead_pipeline_stage_id',
        'next_followup_date',
        // Company is edited via contact person; avoid a second lead-level company field
        // that would overwrite the contact company on save.
        'organization_id',
        'companies',
        'title',
    ];

    $showTitleInEditModal = lead_variant() === 'main';

    if (! $canEditLeadValue) {
        $modalExcludedAttributeCodes[] = 'lead_value';
    }

    $leadQuickAttributes = app(\Webkul\Attribute\Repositories\AttributeRepository::class)
        ->findWhere([
            'entity_type' => 'leads',
            'quick_add'   => 1,
        ])
        ->reject(fn ($attribute) => in_array($attribute->code, $modalExcludedAttributeCodes, true))
        ->values();

    $pipeline = app(\Webkul\Lead\Repositories\PipelineRepository::class)->getDefaultPipeline();

    $leadTypeOptions = \Webkul\Lead\Models\Type::query()
        ->orderBy('name')
        ->get(['id', 'name'])
        ->map(fn ($type) => ['id' => $type->id, 'name' => $type->name])
        ->values()
        ->all();

    $salesOwnerOptions = \Webkul\User\Models\User::query()
        ->where('status', 1)
        ->orderBy('name')
        ->get(['id', 'name'])
        ->map(fn ($user) => ['id' => $user->id, 'name' => $user->name])
        ->values()
        ->all();

    $pipelineOptions = \Webkul\Lead\Models\Pipeline::query()
        ->orderBy('name')
        ->get(['id', 'name'])
        ->map(fn ($item) => ['id' => $item->id, 'name' => $item->name])
        ->values()
        ->all();

    $sourceAccessService = app(\Webkul\Lead\Services\SourceAccessService::class);
    $leadForwardService = app(\Webkul\Lead\Services\LeadForwardService::class);
    $accessibleStages = in_array(lead_variant(), ['sdr', 'lge'], true)
        ? $pipeline->stages->values()
        : $sourceAccessService->filterAccessibleStages($pipeline->stages);

    if (in_array(lead_variant(), ['sdr', 'lge'], true)) {
        $meetingStage = $pipeline->stages->firstWhere('code', 'meeting');

        if ($meetingStage) {
            $accessibleStages = $accessibleStages
                ->filter(fn ($stage) => (int) $stage->sort_order <= (int) $meetingStage->sort_order)
                ->values();
        }
    }

    $inlineOptions = [
        'stage' => [
            'field'   => 'lead_pipeline_stage_id',
            'items'   => $accessibleStages->map(fn ($s) => [
                'value'      => $s->id,
                'label'      => $s->name,
                'code'       => $s->code,
                'sort_order' => $s->sort_order,
            ])->values()->all(),
        ],
        'tag_name' => [
            'field'   => 'tag_id',
            'items'   => app(\Webkul\Tag\Repositories\TagRepository::class)->all(['id as value', 'name as label'])->toArray(),
        ],
        'service_offered' => [
            'field'    => 'services',
            'multiple' => true,
            'items'    => app(\Webkul\Lead\Repositories\ServiceRepository::class)->getDropdownOptions(),
        ],
    ];

    $isSdrUser = $sourceAccessService->isSdrUser();
    $isLgeLeadVariant = ($leadVariant ?? 'main') === 'lge';
    $isLgeUser = $sourceAccessService->isLgeUser();
    $isCallingRoleLeadVariant = in_array($leadVariant ?? 'main', ['sdr', 'lge'], true);
    $currentUserId = auth()->guard('user')->id();
    $activeSdrUsers = $isLgeUser
        ? $leadForwardService->activeSdrUsers()
            ->map(fn ($user) => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
            ])
            ->values()
            ->all()
        : [];

    $canAddServiceOffered = bouncer()->hasPermission('settings.lead.services_offered.create')
        || bouncer()->hasPermission(lead_permission('create'))
        || bouncer()->hasPermission(lead_permission('edit'))
        || $isSdrUser;

    $defaultMeetingParticipants = [
        'users' => ! $isCallingRoleLeadVariant && auth()->guard('user')->user()
            ? [[
                'id'   => auth()->guard('user')->id(),
                'name' => auth()->guard('user')->user()->name,
            ]]
            : [],
        'persons' => [],
    ];
@endphp

<script
    type="text/x-template"
    id="v-leads-table-template"
>
    @include('admin::leads.index.table.template.datagrid')

    @include('admin::leads.index.table.template.modals.edit')
    @include('admin::leads.index.table.template.modals.note')
    @include('admin::leads.index.table.template.modals.incorrect-info')
    @include('admin::leads.index.table.template.modals.meeting')
    @include('admin::leads.index.table.template.modals.followup')
    @include('admin::leads.index.table.template.modals.handoff')
    @include('admin::leads.index.table.template.modals.cold-forward')
    @include('admin::leads.index.table.template.service-dropdown')
</script>

@include('admin::leads.index.table.scripts.component')
@include('admin::leads.index.table.scripts.sort-component')
@include('admin::leads.index.table.scripts.sort')
