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
                        <div
                            v-if="! isOtherServiceSelected(openServiceRecord)"
                            class="shrink-0 border-b border-gray-100 p-2 dark:border-gray-800"
                            style="background:#fff;"
                        >
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
                            <template
                                v-for="opt in filteredServiceOptions"
                                :key="'service-opt-' + opt.value"
                            >
                                <li
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
                                    v-if="canAddServiceOffered && isOtherServiceOption(opt) && isOtherServiceSelected(openServiceRecord)"
                                    class="border-y border-gray-100 px-3 py-2 dark:border-gray-800"
                                    style="background:#fff;"
                                >
                                    <div class="flex gap-2">
                                        <input
                                            ref="newServiceInput"
                                            type="text"
                                            v-model="newServiceName"
                                            class="min-w-0 flex-1 rounded border border-gray-200 px-2 py-1.5 text-sm outline-none focus:border-brandColor dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300"
                                            placeholder="@lang('admin::app.leads.index.datagrid.service-offered')"
                                            @keydown.enter.stop.prevent="createOtherServiceOption(openServiceRecord)"
                                        />

                                        <button
                                            v-if="newServiceName.trim()"
                                            type="button"
                                            class="rounded bg-brandColor px-3 py-1.5 text-sm font-semibold text-white hover:bg-brandColor/90"
                                            :disabled="isCreatingService"
                                            @click.stop.prevent="createOtherServiceOption(openServiceRecord)"
                                        >
                                            @{{ isCreatingService ? serviceCreatingLabel : 'Add' }}
                                        </button>
                                    </div>
                                </li>
                            </template>

                            <li
                                v-if="! filteredServiceOptions.length"
                                class="px-3 py-2 text-sm text-gray-500"
                                style="background:#fff;"
                            >
                                @lang('admin::app.components.lookup.no-results')
                            </li>
                        </ul>
                    </div>
                </template>
            </Teleport>
        </div>
