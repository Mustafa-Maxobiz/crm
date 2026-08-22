    <!-- Header -->
    {!! view_render_event('admin.leads.index.header.before') !!}

    <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
        {!! view_render_event('admin.leads.index.header.left.before') !!}

        <div class="flex flex-col gap-2">
            <!-- Breadcrumb's -->
            <x-admin::breadcrumbs name="leads" />

            <div class="text-xl font-bold dark:text-white">
                @if (($leadVariant ?? 'main') === 'sdr')
                    @lang('admin::app.layouts.leads-sdr')
                @elseif (($leadVariant ?? 'main') === 'lge')
                    @lang('admin::app.layouts.leads-lge')
                @elseif (($leadVariant ?? 'main') === 'lead_clouser')
                    @lang('admin::app.layouts.leads-lead-clouser')
                @else
                    @lang('admin::app.leads.index.title')
                @endif
            </div>
        </div>

        {!! view_render_event('admin.leads.index.header.left.after') !!}

        {!! view_render_event('admin.leads.index.header.right.before') !!}

        <div class="flex items-center gap-x-2.5">
            <!-- Upload File for Lead Creation -->
            @if(core()->getConfigData('general.magic_ai.doc_generation.enabled'))
                @include('admin::leads.index.upload')
            @endif

            @if ((request()->view_type ?? "table") == "table")
                <!-- Export Modal -->
                <x-admin::datagrid.export :src="route($leadsIndexRoute ?? 'admin.leads.index')" />
            @endif

            <!-- Create button for Leads -->
            <div class="flex items-center gap-x-2.5">
                @include('admin::leads.index.partials.import-modal')


                @if (bouncer()->hasPermission(lead_permission('disqualified')))
                    <a
                        href="{{ lead_route('disqualified') }}"
                        class="secondary-button"
                    >
                        @lang('admin::app.leads.disqualification.short-title')
                    </a>
                @endif

                @if (bouncer()->hasPermission(lead_permission('create')))
                    <a
                        href="{{ lead_route('create', request()->query()) }}"
                        class="primary-button"
                    >
                        @lang('admin::app.leads.index.create-btn')
                    </a>
                @endif
            </div>
        </div>

        {!! view_render_event('admin.leads.index.header.right.after') !!}
    </div>

    {!! view_render_event('admin.leads.index.header.after') !!}
