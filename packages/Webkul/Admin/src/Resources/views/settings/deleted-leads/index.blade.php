<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.settings.deleted-leads.index.title')
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-2">
                {!! view_render_event('admin.settings.deleted_leads.index.breadcrumbs.before') !!}

                <x-admin::breadcrumbs name="settings.deleted_leads" />

                {!! view_render_event('admin.settings.deleted_leads.index.breadcrumbs.after') !!}

                <div class="text-xl font-bold dark:text-white">
                    @lang('admin::app.settings.deleted-leads.index.title')
                </div>
            </div>
        </div>

        {!! view_render_event('admin.settings.deleted_leads.index.datagrid.before') !!}

        <x-admin::datagrid :src="route('admin.settings.deleted_leads.index')" />

        {!! view_render_event('admin.settings.deleted_leads.index.datagrid.after') !!}
    </div>
</x-admin::layouts>
