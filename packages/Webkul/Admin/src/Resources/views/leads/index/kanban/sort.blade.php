{!! view_render_event('admin.leads.index.kanban.toolbar.sort.before') !!}

<x-admin::dropdown position="bottom-right">
    <x-slot:toggle>
        <button
            type="button"
            class="flex h-[37px] w-full cursor-pointer appearance-none items-center justify-between gap-x-2 rounded-md border bg-white px-2.5 py-1.5 text-center leading-6 text-gray-600 transition-all marker:shadow hover:border-gray-400 focus:border-gray-400 focus:ring-black dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400 dark:focus:border-gray-400"
        >
            <span class="icon-sort text-2xl"></span>

            <span class="text-sm font-medium">
                @{{ sortLabel }}
            </span>

            <span class="icon-sort-down text-2xl"></span>
        </button>
    </x-slot>

    <x-slot:menu class="!p-0">
        <div class="grid w-[220px] gap-1 p-1.5">
            <!-- Sort by Date (Newest First) -->
            <div
                class="flex cursor-pointer items-center gap-1.5 rounded-md px-2 py-1.5 hover:bg-gray-100 dark:hover:bg-gray-950"
                :class="{ 'bg-gray-100 dark:bg-gray-950': applied.sort.by === 'created_at' && applied.sort.order === 'desc' }"
                @click="sort('created_at', 'desc')"
            >
                <span class="icon-sort-down text-2xl"></span>

                <div class="flex flex-col gap-0.5">
                    <p class="text-sm font-semibold leading-none text-gray-800 dark:text-white">
                        @lang('admin::app.leads.index.kanban.toolbar.sort.newest-first')
                    </p>

                    <p class="text-xs leading-none text-gray-600 dark:text-gray-300">
                        @lang('admin::app.leads.index.kanban.toolbar.sort.newest-first-desc')
                    </p>
                </div>
            </div>

            <!-- Sort by Date (Oldest First) -->
            <div
                class="flex cursor-pointer items-center gap-1.5 rounded-md px-2 py-1.5 hover:bg-gray-100 dark:hover:bg-gray-950"
                :class="{ 'bg-gray-100 dark:bg-gray-950': applied.sort.by === 'created_at' && applied.sort.order === 'asc' }"
                @click="sort('created_at', 'asc')"
            >
                <span class="icon-sort-up text-2xl"></span>

                <div class="flex flex-col gap-0.5">
                    <p class="text-sm font-semibold leading-none text-gray-800 dark:text-white">
                        @lang('admin::app.leads.index.kanban.toolbar.sort.oldest-first')
                    </p>

                    <p class="text-xs leading-none text-gray-600 dark:text-gray-300">
                        @lang('admin::app.leads.index.kanban.toolbar.sort.oldest-first-desc')
                    </p>
                </div>
            </div>

            <!-- Sort by Lead Value (High to Low) -->
            <div
                class="flex cursor-pointer items-center gap-1.5 rounded-md px-2 py-1.5 hover:bg-gray-100 dark:hover:bg-gray-950"
                :class="{ 'bg-gray-100 dark:bg-gray-950': applied.sort.by === 'lead_value' && applied.sort.order === 'desc' }"
                @click="sort('lead_value', 'desc')"
            >
                <span class="icon-sort-down text-2xl"></span>

                <div class="flex flex-col gap-0.5">
                    <p class="text-sm font-semibold leading-none text-gray-800 dark:text-white">
                        @lang('admin::app.leads.index.kanban.toolbar.sort.value-high-low')
                    </p>

                    <p class="text-xs leading-none text-gray-600 dark:text-gray-300">
                        @lang('admin::app.leads.index.kanban.toolbar.sort.value-high-low-desc')
                    </p>
                </div>
            </div>

            <!-- Sort by Lead Value (Low to High) -->
            <div
                class="flex cursor-pointer items-center gap-1.5 rounded-md px-2 py-1.5 hover:bg-gray-100 dark:hover:bg-gray-950"
                :class="{ 'bg-gray-100 dark:bg-gray-950': applied.sort.by === 'lead_value' && applied.sort.order === 'asc' }"
                @click="sort('lead_value', 'asc')"
            >
                <span class="icon-sort-up text-2xl"></span>

                <div class="flex flex-col gap-0.5">
                    <p class="text-sm font-semibold leading-none text-gray-800 dark:text-white">
                        @lang('admin::app.leads.index.kanban.toolbar.sort.value-low-high')
                    </p>

                    <p class="text-xs leading-none text-gray-600 dark:text-gray-300">
                        @lang('admin::app.leads.index.kanban.toolbar.sort.value-low-high-desc')
                    </p>
                </div>
            </div>

            <!-- Sort by Title (A-Z) -->
            <div
                class="flex cursor-pointer items-center gap-1.5 rounded-md px-2 py-1.5 hover:bg-gray-100 dark:hover:bg-gray-950"
                :class="{ 'bg-gray-100 dark:bg-gray-950': applied.sort.by === 'title' && applied.sort.order === 'asc' }"
                @click="sort('title', 'asc')"
            >
                <span class="icon-sort-up text-2xl"></span>

                <div class="flex flex-col gap-0.5">
                    <p class="text-sm font-semibold leading-none text-gray-800 dark:text-white">
                        @lang('admin::app.leads.index.kanban.toolbar.sort.title-az')
                    </p>

                    <p class="text-xs leading-none text-gray-600 dark:text-gray-300">
                        @lang('admin::app.leads.index.kanban.toolbar.sort.title-az-desc')
                    </p>
                </div>
            </div>

            <!-- Sort by Title (Z-A) -->
            <div
                class="flex cursor-pointer items-center gap-1.5 rounded-md px-2 py-1.5 hover:bg-gray-100 dark:hover:bg-gray-950"
                :class="{ 'bg-gray-100 dark:bg-gray-950': applied.sort.by === 'title' && applied.sort.order === 'desc' }"
                @click="sort('title', 'desc')"
            >
                <span class="icon-sort-down text-2xl"></span>

                <div class="flex flex-col gap-0.5">
                    <p class="text-sm font-semibold leading-none text-gray-800 dark:text-white">
                        @lang('admin::app.leads.index.kanban.toolbar.sort.title-za')
                    </p>

                    <p class="text-xs leading-none text-gray-600 dark:text-gray-300">
                        @lang('admin::app.leads.index.kanban.toolbar.sort.title-za-desc')
                    </p>
                </div>
            </div>
        </div>
    </x-slot>
</x-admin::dropdown>

{!! view_render_event('admin.leads.index.kanban.toolbar.sort.after') !!}
