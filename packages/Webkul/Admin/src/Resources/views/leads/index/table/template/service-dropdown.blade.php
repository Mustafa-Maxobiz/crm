            {{-- Services Offered: fixed overlay + panel so table content never bleeds through --}}
            <Teleport to="body">
                <template v-if="openServiceRecord">
                    <div
                        class="service-offered-dropdown-overlay fixed inset-0 z-[9998]"
                        @mousedown.prevent="closeServiceDropdown"
                    ></div>

                    <div
                        class="service-offered-dropdown-portal fixed z-[9999] flex w-72 flex-col rounded-md border border-gray-200 shadow-2xl dark:border-gray-800"
                        :style="serviceDropdownStyle"
                        @mousedown.stop
                        @click.stop
                        @wheel.stop
                    >
                        <div class="shrink-0 border-b border-gray-100 p-2 dark:border-gray-800" style="background:#fff;">
                            <input
                                type="text"
                                v-model="serviceSearchTerm"
                                class="w-full rounded border border-gray-200 px-2 py-1.5 text-sm outline-none focus:border-brandColor dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300"
                                style="background:#fff;"
                                placeholder="@lang('admin::app.leads.index.datagrid.service-offered')"
                                @keydown.enter.prevent="handleServiceEnter(openServiceRecord)"
                            />
                        </div>

                        <ul
                            class="min-h-0 flex-1 overflow-y-auto overscroll-contain py-1"
                            style="background:#fff;"
                            :style="{ maxHeight: serviceListMaxHeight }"
                        >
                            <li
                                v-for="opt in filteredServiceOptions"
                                :key="'service-opt-' + opt.value"
                                class="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm text-gray-800 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                                style="background:#fff;"
                                @click="toggleServiceOption(openServiceRecord, opt.value)"
                            >
                                <span
                                    class="flex h-4 w-4 shrink-0 items-center justify-center rounded border"
                                    :class="isServiceSelected(openServiceRecord, opt.value)
                                        ? 'border-brandColor bg-brandColor text-white'
                                        : 'border-gray-300'"
                                    :style="isServiceSelected(openServiceRecord, opt.value) ? '' : 'background:#fff;'"
                                >
                                    <i
                                        v-if="isServiceSelected(openServiceRecord, opt.value)"
                                        class="icon-tick text-xs"
                                    ></i>
                                </span>
                                <span class="truncate">@{{ opt.label }}</span>
                            </li>

                            <li
                                v-if="! filteredServiceOptions.length && ! serviceSearchableNewLabel"
                                class="px-3 py-2 text-sm text-gray-500"
                                style="background:#fff;"
                            >
                                @lang('admin::app.components.lookup.no-results')
                            </li>

                            <li
                                v-if="canAddServiceOffered && serviceSearchableNewLabel"
                                class="cursor-pointer border-t border-gray-100 px-3 py-2 text-sm font-medium text-brandColor hover:bg-gray-50"
                                style="background:#fff;"
                                @click="createServiceOption(openServiceRecord)"
                            >
                                <i class="icon-add text-md"></i>
                                @{{ isCreatingService ? serviceCreatingLabel : serviceAddLabel.replace(':name', serviceSearchableNewLabel) }}
                            </li>
                        </ul>

                        <div class="flex shrink-0 justify-end gap-1 border-t border-gray-100 p-2 dark:border-gray-800" style="background:#fff;">
                            <button
                                type="button"
                                class="rounded px-2 py-1 text-sm text-gray-600 hover:bg-gray-100"
                                @click="closeServiceDropdown"
                            >
                                @lang('admin::app.leads.index.datagrid.cancel')
                            </button>
                            <button
                                type="button"
                                class="rounded bg-brandColor px-2 py-1 text-sm text-white"
                                @click="saveServiceOffered(openServiceRecord)"
                            >
                                @lang('admin::app.leads.index.datagrid.save')
                            </button>
                        </div>
                    </div>
                </template>
            </Teleport>
        </div>
