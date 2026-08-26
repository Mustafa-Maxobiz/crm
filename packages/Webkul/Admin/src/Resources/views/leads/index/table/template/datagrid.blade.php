        <div>
            <x-admin::datagrid
                src="{{ route($leadsIndexRoute) }}"
                ref="datagrid"
                fixed-height
            >
                <x-slot:toolbar-left-after>
                    <v-leads-table-sort></v-leads-table-sort>
                </x-slot>

                <x-slot:toolbar-right-after>
                    @include('admin::leads.index.view-switcher')
                </x-slot>

                <template #body="{
                    isLoading,
                    available,
                    applied,
                    selectAll,
                    sort,
                    performAction
                }">
                    <template v-if="isLoading">
                        <x-admin::shimmer.datagrid.table.body />
                    </template>

                    <template v-else>
	                        <div
	                            v-for="record in available.records"
	                            class="row grid items-center gap-2.5 border-b px-4 py-4 text-black transition-all hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-gray-950 max-lg:hidden"
	                            :class="isStageEditingLocked(record, inlineOptions.stage?.items || []) ? 'opacity-60 grayscale' : ''"
	                            :style="gridRowStyle(available)"
	                        >
                            <p v-if="available.massActions.length">
                                <label :for="`mass_action_select_record_${record[available.meta.primary_column]}`">
                                    <input
                                        type="checkbox"
                                        :name="`mass_action_select_record_${record[available.meta.primary_column]}`"
                                        :value="record[available.meta.primary_column]"
	                                        :id="`mass_action_select_record_${record[available.meta.primary_column]}`"
	                                        class="peer hidden"
	                                        :disabled="isStageEditingLocked(record, inlineOptions.stage?.items || [])"
	                                        v-model="applied.massActions.indices"
	                                    >

                                    <span class="icon-checkbox-outline peer-checked:icon-checkbox-select cursor-pointer rounded-md text-2xl text-gray-500 peer-checked:text-brandColor">
                                    </span>
                                </label>
                            </p>

                            <template v-for="column in available.columns">
                                <template v-if="column.visibility">
                                    {{-- Multi-select Services Offered trigger --}}
                                    <div
                                        v-if="inlineOptions[column.index]?.multiple"
                                        class="service-offered-cell min-w-0"
                                        @click.stop
                                    >
                                        <button
                                            type="button"
	                                            class="flex w-full items-center justify-between gap-1 truncate rounded border border-transparent bg-transparent px-1 py-0.5 text-left text-sm text-gray-800 outline-none transition-all hover:border-gray-300 focus:border-brandColor dark:text-gray-300 dark:hover:border-gray-600"
	                                            :class="openServiceLeadId === record.id ? 'border-brandColor ring-1 ring-brandColor' : ''"
	                                            :disabled="isStageEditingLocked(record, inlineOptions.stage?.items || [])"
	                                            :ref="el => setServiceTriggerRef(record.id, el)"
	                                            @click="toggleServiceDropdown(record, $event)"
                                        >
                                            <span class="truncate">
                                                @{{ serviceOfferedLabel(record) }}
                                            </span>
                                            <i class="icon-down-arrow shrink-0 text-lg"></i>
                                        </button>
                                    </div>

                                    {{-- Inline-editable single dropdown columns --}}
                                    <div
                                        v-else-if="inlineOptions[column.index]"
                                        class="min-w-0"
                                    >
	                                        <select
	                                            class="w-full cursor-pointer truncate rounded border border-transparent bg-transparent px-1 py-0.5 text-sm text-gray-800 outline-none transition-all hover:border-gray-300 focus:border-brandColor focus:ring-1 focus:ring-brandColor dark:text-gray-300 dark:hover:border-gray-600 dark:focus:border-brandColor"
	                                            :value="record[inlineOptions[column.index].field]"
	                                            :disabled="isStageEditingLocked(record, inlineOptions.stage?.items || [])"
	                                            @change="inlineUpdate(record, column.index, $event.target.value)"
	                                        >
                                            <option value="">--</option>
                                            <option
                                                v-for="opt in inlineOptions[column.index].items"
                                                :key="opt.value"
                                                :value="opt.value"
                                                v-text="opt.label"
                                            ></option>
                                        </select>
                                    </div>

                                    {{-- Inline-editable Lead Value (main leads only) --}}
                                    <div
                                        v-else-if="column.index === 'lead_value' && canEditLeadValue"
                                        class="min-w-0"
                                        @click.stop
                                    >
                                        <input
                                            type="number"
                                            min="0"
	                                            step="0.01"
	                                            class="w-full min-w-[6rem] rounded border border-transparent bg-transparent px-1 py-0.5 text-sm text-gray-800 outline-none transition-all hover:border-gray-300 focus:border-brandColor focus:ring-1 focus:ring-brandColor dark:text-gray-300 dark:hover:border-gray-600 dark:focus:border-brandColor"
	                                            :value="record.lead_value ?? 0"
	                                            :disabled="isStageEditingLocked(record, inlineOptions.stage?.items || [])"
	                                            @change="updateLeadValue(record, $event.target.value)"
	                                        />
                                    </div>

                                    {{-- Regular read-only columns --}}
                                    <p
                                        v-else
                                        class="min-w-0 break-words"
                                        v-html="record[column.index]"
                                    >
                                    </p>
                                </template>
                            </template>

	                            <p
	                                class="flex h-full items-center justify-end gap-0.5 place-self-end"
	                                v-if="available.actions.length && ! isStageEditingLocked(record, inlineOptions.stage?.items || [])"
	                            >
                                <span
                                    class="cursor-pointer rounded-md p-1.5 text-2xl transition-all hover:bg-gray-200 dark:hover:bg-gray-800"
                                    :class="action.icon"
                                    :title="action.title"
                                    v-for="action in record.actions"
                                    :key="action.index || action.title"
                                    @click="handleAction(action, record, performAction)"
                                >
                                </span>
                            </p>
                        </div>

	                        <div
	                            class="hidden border-b px-4 py-4 text-black dark:border-gray-800 dark:text-gray-300 max-lg:block"
	                            :class="isStageEditingLocked(record, inlineOptions.stage?.items || []) ? 'opacity-60 grayscale' : ''"
	                            v-for="record in available.records"
	                        >
	                            <div
	                                class="mb-2 flex items-center justify-end gap-1"
	                                v-if="! isStageEditingLocked(record, inlineOptions.stage?.items || [])"
	                            >
	                                <span
                                    class="cursor-pointer rounded-md p-1.5 text-2xl transition-all hover:bg-gray-200 dark:hover:bg-gray-800"
	                                    :class="action.icon"
	                                    :title="action.title"
	                                    v-for="action in record.actions"
	                                    :key="action.index || action.title"
                                    @click="handleAction(action, record, performAction)"
                                >
                                </span>
                            </div>

                            <div class="grid gap-2">
                                <template v-for="column in available.columns">
                                    <div
                                        class="flex flex-wrap items-baseline gap-x-2"
                                        v-if="column.visibility"
                                    >
                                        <span class="text-slate-600 dark:text-gray-300" v-html="column.label + ':'"></span>
                                        <span class="break-words font-medium text-slate-900 dark:text-white" v-html="record[column.index]"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </template>
            </x-admin::datagrid>
