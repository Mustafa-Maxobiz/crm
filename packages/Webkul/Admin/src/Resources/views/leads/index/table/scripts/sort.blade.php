    <script
        type="text/x-template"
        id="v-leads-table-sort-template"
    >
        <x-admin::dropdown position="bottom-{{ in_array(app()->getLocale(), ['fa', 'ar']) ? 'left' : 'right' }}">
            <x-slot:toggle>
                <button
                    type="button"
                    class="flex h-[38px] cursor-pointer appearance-none items-center justify-between gap-x-2 rounded-md border bg-white px-2.5 py-1.5 text-center leading-6 text-gray-600 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400 dark:focus:border-gray-400"
                >
                    <span class="icon-sort text-2xl"></span>

                    <span class="whitespace-nowrap text-sm font-medium">
                        @{{ sortLabel }}
                    </span>

                    <span class="icon-sort-down text-2xl"></span>
                </button>
            </x-slot>

            <x-slot:menu class="!p-0">
                <div class="grid w-[220px] gap-1 p-1.5">
                    <div
                        class="flex cursor-pointer items-center gap-1.5 rounded-md px-2 py-1.5 hover:bg-gray-100 dark:hover:bg-gray-950"
                        :class="{ 'bg-gray-100 dark:bg-gray-950': applied.sort.column === 'created_at' && applied.sort.order === 'desc' }"
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

                    <div
                        class="flex cursor-pointer items-center gap-1.5 rounded-md px-2 py-1.5 hover:bg-gray-100 dark:hover:bg-gray-950"
                        :class="{ 'bg-gray-100 dark:bg-gray-950': applied.sort.column === 'created_at' && applied.sort.order === 'asc' }"
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

                    <div
                        class="flex cursor-pointer items-center gap-1.5 rounded-md px-2 py-1.5 hover:bg-gray-100 dark:hover:bg-gray-950"
                        :class="{ 'bg-gray-100 dark:bg-gray-950': applied.sort.column === 'title' && applied.sort.order === 'asc' }"
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

                    <div
                        class="flex cursor-pointer items-center gap-1.5 rounded-md px-2 py-1.5 hover:bg-gray-100 dark:hover:bg-gray-950"
                        :class="{ 'bg-gray-100 dark:bg-gray-950': applied.sort.column === 'title' && applied.sort.order === 'desc' }"
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
    </script>
