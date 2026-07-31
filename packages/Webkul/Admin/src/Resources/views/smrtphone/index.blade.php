<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.smrtphone.index.title')
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-2">
                <x-admin::breadcrumbs name="smrtphone" />

                <div class="text-xl font-bold dark:text-white">
                    @lang('admin::app.smrtphone.index.title')
                </div>

                <p class="text-gray-600 dark:text-gray-400">
                    @lang('admin::app.smrtphone.index.subtitle')
                </p>
            </div>
        </div>

        <x-admin::datagrid :src="route('admin.smrtphone.index')">
            <x-admin::shimmer.datagrid />
        </x-admin::datagrid>
    </div>
</x-admin::layouts>
