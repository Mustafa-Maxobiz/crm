{!! view_render_event('admin.leads.index.kanban.before') !!}

<!-- Kanban Vue Component -->
<v-leads-kanban ref="leadsKanban">
    <div class="flex flex-col gap-4">
        <!-- Shimmer -->
        <x-admin::shimmer.leads.index.kanban />
    </div>
</v-leads-kanban>

{!! view_render_event('admin.leads.index.kanban.after') !!}

<div class="hidden">
    @include('admin::components.activities.actions.activity.participants')
</div>

@pushOnce('scripts')
    @include('admin::leads.index.kanban.scripts-block')
@endPushOnce
