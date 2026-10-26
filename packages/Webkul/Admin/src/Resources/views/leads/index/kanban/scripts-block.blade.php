@php
    $isLgeLeadVariant = ($leadVariant ?? 'main') === 'lge';
    $isCallingRoleLeadVariant = in_array($leadVariant ?? 'main', ['bde', 'sdr', 'lge', 'de_data_scraper'], true);
    $isOwnerRestrictedLeadVariant = in_array($leadVariant ?? 'main', ['bde', 'sdr', 'lge', 'de_data_scraper'], true);
    $currentUserId = auth()->guard('user')->id();
    $sourceAccessService = app(\Webkul\Lead\Services\SourceAccessService::class);
    $isBdeDemoUser = $sourceAccessService->isBdeDemoUser();
    $accessibleStages = $sourceAccessService
        ->getVisibleStagesForLeadListing($pipeline->stages, (int) $pipeline->id)
        ->values();

    $followupRuleOptions = \Illuminate\Support\Facades\DB::table('followup_rule_groups')
        ->where('is_active', 1)
        ->orderByDesc('is_default')
        ->orderBy('name')
        ->get(['id', 'name', 'is_default'])
        ->map(fn ($rule) => [
            'id'         => (int) $rule->id,
            'name'       => $rule->name,
            'is_default' => (bool) $rule->is_default,
        ])
        ->values()
        ->all();

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
    id="v-leads-kanban-template"
>
    @include('admin::leads.index.kanban.template.main')

    @include('admin::leads.index.kanban.template.modals.stage-update')
    @include('admin::leads.index.kanban.template.modals.followup')
    @include('admin::leads.index.kanban.template.modals.meeting')
        </template>
</script>

@include('admin::leads.index.kanban.scripts.component')
