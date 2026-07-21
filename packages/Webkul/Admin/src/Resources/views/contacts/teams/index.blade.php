<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.contacts.teams.index.title')
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-2">
                {!! view_render_event('admin.teams.index.breadcrumbs.before') !!}

                <x-admin::breadcrumbs name="contacts.teams" />

                {!! view_render_event('admin.teams.index.breadcrumbs.after') !!}

                <div class="text-xl font-bold dark:text-gray-300">
                    @lang('admin::app.contacts.teams.index.title')
                </div>
            </div>

            <div class="flex items-center gap-x-2.5">
                @if (bouncer()->hasPermission('contacts.teams.create'))
                    <a
                        href="{{ route('admin.contacts.teams.create') }}"
                        class="primary-button"
                    >
                        @lang('admin::app.contacts.teams.index.create-btn')
                    </a>
                @endif
            </div>
        </div>

        {!! view_render_event('admin.teams.datagrid.index.before') !!}

        <x-admin::datagrid :src="route('admin.contacts.teams.index')">
            <x-admin::shimmer.datagrid />
        </x-admin::datagrid>

        {!! view_render_event('admin.teams.datagrid.index.after') !!}
    </div>
</x-admin::layouts>
