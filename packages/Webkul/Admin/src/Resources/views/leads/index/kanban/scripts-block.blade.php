@php
    $isLgeLeadVariant = ($leadVariant ?? 'main') === 'lge';
    $isCallingRoleLeadVariant = in_array($leadVariant ?? 'main', ['sdr', 'lge'], true);
    $currentUserId = auth()->guard('user')->id();
    $sourceAccessService = app(\Webkul\Lead\Services\SourceAccessService::class);
    $accessibleStages = $sourceAccessService
        ->getVisibleStagesForLeadListing($pipeline->stages, (int) $pipeline->id)
        ->values();

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
