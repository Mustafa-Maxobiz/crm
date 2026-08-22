{!! view_render_event('admin.leads.index.table.before') !!}

<v-leads-table>
    <x-admin::shimmer.datagrid />
</v-leads-table>

{!! view_render_event('admin.leads.index.table.after') !!}

{{-- Include contact partial: hidden wrapper suppresses the bare <v-contact-component>,
     but @pushOnce still registers its Vue template & script. --}}
<div class="hidden">
    @include('admin::leads.common.contact')
    @include('admin::components.activities.actions.activity.participants')
</div>

@pushOnce('scripts')
    @include('admin::leads.index.table.scripts-block')
@endPushOnce
