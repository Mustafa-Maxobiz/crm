@props([
    'attribute'   => null,
    'value'       => null,
    'validations' => '',
    'canAddNew'   => false,
    'storeUrl'    => null,
    'updateUrl'   => null,
    'options'     => null,
])

@php
    if ($options !== null) {
        $resolvedOptions = collect($options);
    } elseif ($attribute?->lookup_type) {
        $resolvedOptions = app('Webkul\Attribute\Repositories\AttributeRepository')->getLookUpOptions($attribute->lookup_type);
    } elseif (is_object($attribute) && method_exists($attribute, 'options')) {
        $resolvedOptions = $attribute->options()->orderBy('sort_order')->get(['id', 'name']);
    } else {
        $resolvedOptions = collect();
    }

    $selectedOption = old($attribute->code ?? 'services') ?: $value;

    if (is_array($selectedOption)) {
        $selectedIds = array_values(array_filter(array_map('intval', $selectedOption)));
    } else {
        $selectedIds = array_values(array_filter(array_map('intval', explode(',', (string) $selectedOption))));
    }

    $canAddNew = (bool) $canAddNew && filled($storeUrl);
    $fieldName = $attribute->code ?? 'services';
    $fieldLabel = $attribute->name ?? __('admin::app.leads.index.datagrid.service-offered');
@endphp

<v-attribute-multiselect
    name="{{ $fieldName }}"
    label="{{ $fieldLabel }}"
    placeholder="{{ $fieldLabel }}"
    rules="{{ $validations }}"
    :options='@json($resolvedOptions)'
    :selected-ids='@json($selectedIds)'
    :can-add-new='@json($canAddNew)'
    store-url="{{ $storeUrl }}"
    update-url="{{ $updateUrl }}"
>
</v-attribute-multiselect>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-attribute-multiselect-template"
    >
        <div class="relative w-full">
            <button
                v-if="isImmediateUpdateMode"
                type="button"
                class="flex min-h-[39px] w-full items-center justify-between gap-2 rounded-md border border-gray-200 bg-white px-2.5 py-1.5 text-left text-sm text-gray-800 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400"
                @click="toggleDropdown"
            >
                <span class="truncate">@{{ selectedDisplayLabel }}</span>
                <i class="icon-sort-down text-xl"></i>
            </button>

            <div
                v-else
                class="flex min-h-[39px] w-full flex-wrap items-center gap-1 rounded-md border border-gray-200 px-2.5 py-1.5 text-sm text-gray-800 transition-all hover:border-gray-400 focus-within:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400"
                @click="focusSearch"
            >
                <span
                    v-for="option in selectedOptions"
                    :key="option.id"
                    class="flex items-center gap-1 rounded-md bg-gray-100 pl-2 dark:bg-gray-800 dark:text-white"
                >
                    <input
                        type="hidden"
                        :name="name + '[]'"
                        :value="option.id"
                    />

                    <span class="max-w-[140px] truncate">@{{ option.name }}</span>

                    <button
                        type="button"
                        class="icon-cross-large cursor-pointer p-0.5 text-xl"
                        @click.stop="removeOption(option)"
                    ></button>
                </span>

                <input
                    v-if="! isOtherSelected"
                    ref="searchInput"
                    type="text"
                    v-model="searchTerm"
                    class="min-w-[120px] flex-1 border-0 bg-transparent p-0 text-sm outline-none dark:bg-transparent"
                    :placeholder="selectedOptions.length ? '' : placeholder"
                    @focus="isOpen = true"
                    @keydown.enter.prevent="handleEnter"
                    @keydown.escape="isOpen = false"
                />
            </div>

            <!-- Ensure empty selection is posted so values can be cleared -->
            <input
                v-if="! selectedOptions.length"
                type="hidden"
                :name="name"
                value=""
            />

            <div
                v-if="isOpen && (filteredOptions.length || (canAddNew && isOtherSelected))"
                class="absolute left-0 right-0 z-50 mt-1 max-h-48 overflow-auto rounded-md border border-gray-200 bg-white shadow-lg dark:border-gray-800 dark:bg-gray-900"
                @mousedown.stop
                @click.stop
            >
                <div
                    v-if="isImmediateUpdateMode && ! isOtherSelected"
                    class="border-b border-gray-100 p-2 dark:border-gray-800"
                >
                    <input
                        ref="dropdownSearchInput"
                        type="text"
                        v-model="searchTerm"
                        class="w-full rounded border border-gray-200 px-2 py-1.5 text-sm outline-none focus:border-brandColor dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300"
                        :placeholder="placeholder"
                        @keydown.enter.prevent="handleEnter"
                    />
                </div>

                <template
                    v-for="option in filteredOptions"
                    :key="option.id"
                >
                    <button
                        type="button"
                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-800 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                        @mousedown.prevent.stop="addOption(option)"
                    >
                        <span
                            class="flex h-4 w-4 shrink-0 items-center justify-center rounded border"
                            :class="isSelectedOption(option)
                                ? 'border-brandColor bg-brandColor text-white'
                                : 'border-gray-300 bg-white'"
                        >
                            <i
                                v-if="isSelectedOption(option)"
                                class="icon-tick text-xs"
                            ></i>
                        </span>

                        <span class="truncate">@{{ option.name }}</span>
                    </button>

                    <div
                        v-if="canAddNew && isOtherOption(option) && isOtherSelected"
                        class="flex gap-2 border-y border-gray-100 p-2 dark:border-gray-800"
                        @mousedown.stop
                    >
                        <input
                            ref="newOptionInput"
                            type="text"
                            v-model="newOptionName"
                            class="min-w-0 flex-1 rounded border border-gray-200 px-2 py-1.5 text-sm outline-none focus:border-brandColor dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300"
                            :placeholder="placeholder"
                            @keydown.enter.prevent="createOption"
                        />

                        <button
                            v-if="newOptionName.trim()"
                            type="button"
                            class="rounded bg-brandColor px-3 py-1.5 text-sm font-semibold text-white"
                            :disabled="isCreating"
                            @mousedown.prevent="createOption"
                        >
                            @{{ isCreating ? creatingLabel : 'Add' }}
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </script>

    <script type="module">
        app.component('v-attribute-multiselect', {
            template: '#v-attribute-multiselect-template',

            props: {
                name: {
                    type: String,
                    required: true,
                },

                label: {
                    type: String,
                    default: '',
                },

                placeholder: {
                    type: String,
                    default: '',
                },

                rules: {
                    type: String,
                    default: '',
                },

                options: {
                    type: Array,
                    default: () => [],
                },

                selectedIds: {
                    type: Array,
                    default: () => [],
                },

                canAddNew: {
                    type: Boolean,
                    default: false,
                },

                storeUrl: {
                    type: String,
                    default: '',
                },

                updateUrl: {
                    type: String,
                    default: '',
                },
            },

            data() {
                return {
                    availableOptions: [...this.options],
                    selectedIdList: [...this.selectedIds].map(Number),
                    searchTerm: '',
                    newOptionName: '',
                    isAddingOther: false,
                    isOpen: false,
                    isCreating: false,
                    addLabel: @json(__('admin::app.leads.services-offered.add-option')),
                    creatingLabel: @json(__('admin::app.leads.services-offered.creating-option')),
                };
            },

            computed: {
                selectedOptions() {
                    return this.availableOptions.filter(option => {
                        return this.selectedIdList.includes(Number(option.id)) && ! this.isOtherOption(option);
                    });
                },

                selectedDisplayLabel() {
                    const labels = this.selectedOptions.map(option => option.name);

                    return labels.length ? labels.join(', ') : '--';
                },

                isImmediateUpdateMode() {
                    return Boolean(this.updateUrl);
                },

                filteredOptions() {
                    const term = this.searchTerm.trim().toLowerCase();
                    const otherOption = this.otherOption;
                    const otherId = otherOption ? Number(otherOption.id) : null;

                    if (this.isOtherSelected && ! term && otherOption) {
                        return [otherOption];
                    }

                    return this.availableOptions.filter(option => {
                        const optionId = Number(option.id);

                        if (! this.isImmediateUpdateMode && this.selectedIdList.includes(optionId) && optionId !== otherId) {
                            return false;
                        }

                        if (! term) {
                            return option.is_show !== false;
                        }

                        return String(option.name).toLowerCase().includes(term);
                    });
                },

                searchableNewLabel() {
                    const term = this.newOptionName.trim();

                    if (! term) {
                        return '';
                    }

                    const exists = this.availableOptions.some(
                        option => String(option.name).toLowerCase() === term.toLowerCase()
                    );

                    if (exists) {
                        return '';
                    }

                    return term;
                },

                addButtonLabel() {
                    return this.addLabel.replace(':name', this.searchableNewLabel);
                },

                otherOption() {
                    return this.availableOptions.find(
                        option => String(option.name || '').trim().toLowerCase() === 'other'
                    );
                },

                isOtherSelected() {
                    if (! this.otherOption) {
                        return false;
                    }

                    return this.isAddingOther || this.selectedIdList.includes(Number(this.otherOption.id));
                },
            },

            mounted() {
                document.addEventListener('mousedown', this.handleOutsideClick);
            },

            beforeUnmount() {
                document.removeEventListener('mousedown', this.handleOutsideClick);
            },

            methods: {
                toggleDropdown() {
                    this.isOpen = ! this.isOpen;
                    this.searchTerm = '';

                    if (! this.isOpen) {
                        this.newOptionName = '';
                        this.isAddingOther = false;

                        return;
                    }

                    this.$nextTick(() => {
                        if (this.isOtherSelected) {
                            this.$refs.newOptionInput?.focus();

                            return;
                        }

                        this.$refs.dropdownSearchInput?.focus();
                    });
                },

                focusSearch() {
                    this.isOpen = true;
                    this.$refs.searchInput?.focus();

                    if (this.isOtherSelected) {
                        this.$nextTick(() => {
                            this.$refs.newOptionInput?.focus();
                        });
                    }
                },

                handleOutsideClick(event) {
                    // Don't close if we're in "Add Other" mode
                    if (this.isAddingOther) {
                        return;
                    }

                    if (! this.$el.contains(event.target)) {
                        this.isOpen = false;
                        this.newOptionName = '';
                        this.isAddingOther = false;

                        return;
                    }
                },

                addOption(option) {
                    const id = Number(option.id);

                    if (this.isOtherOption(option)) {
                        if (this.isAddingOther) {
                            return;
                        }

                        this.isAddingOther = true;
                        this.unselectOtherOption(false);
                        this.searchTerm = '';
                        this.isOpen = true;

                        this.$nextTick(() => {
                            this.$refs.newOptionInput?.focus();
                        });

                        return;
                    }

                    if (this.isImmediateUpdateMode) {
                        const previousSelectedIds = [...this.selectedIdList];
                        const selectedIds = [...this.selectedIdList].filter(selectedId => {
                            return Number(selectedId) !== Number(this.otherOption?.id);
                        });
                        const selectedIndex = selectedIds.findIndex(selectedId => Number(selectedId) === id);

                        if (selectedIndex >= 0) {
                            selectedIds.splice(selectedIndex, 1);
                        } else {
                            selectedIds.push(id);
                        }

                        this.selectedIdList = selectedIds;
                        this.searchTerm = '';

                        this.persistSelectedOptions(previousSelectedIds);

                        return;
                    }

                    if (! this.selectedIdList.includes(id)) {
                        this.selectedIdList.push(id);
                    }

                    this.searchTerm = '';
                    this.isOpen = true;
                    this.$refs.searchInput?.focus();
                },

                removeOption(option) {
                    const id = Number(option.id);
                    this.selectedIdList = this.selectedIdList.filter(selectedId => selectedId !== id);

                    if (this.isOtherOption(option)) {
                        this.newOptionName = '';
                    }
                },

                handleEnter() {
                    if (this.filteredOptions.length) {
                        this.addOption(this.filteredOptions[0]);
                        return;
                    }
                },

                createOption() {
    const optionName = String(this.newOptionName || '').trim();

    if (! this.canAddNew || ! this.storeUrl || ! optionName || this.isCreating) {
        return;
    }

    this.isCreating = true;

    this.$axios.post(this.storeUrl, {
        name: optionName,
    }).then(response => {
                        const option = response.data.data;
                        const normalizedOption = {
                            id: option.id,
                            name: option.name,
                            is_show: option.is_show ?? false,
                        };

                        this.availableOptions.push(normalizedOption);

                        if (this.isImmediateUpdateMode) {
                            const previousSelectedIds = [...this.selectedIdList];
                            const otherId = this.otherOption ? Number(this.otherOption.id) : null;
                            const newId = Number(option.id);

                            let newIds = this.selectedIdList.filter(id => Number(id) !== otherId);

                            if (! newIds.includes(newId)) {
                                newIds.push(newId);
                            }

                            this.selectedIdList = newIds;
                            this.searchTerm = '';
                            this.unselectOtherOption();
                            this.newOptionName = '';

                            return this.persistSelectedOptions(previousSelectedIds, response.data.message, true);
                        }

                        this.addOption(normalizedOption);
                        this.unselectOtherOption();
                        this.newOptionName = '';

                        this.$emitter.emit('add-flash', {
                            type: 'success',
                            message: response.data.message,
                        });
                    }).catch(error => {
                        this.$emitter.emit('add-flash', {
                            type: 'error',
                            message: error.response?.data?.message
                                || Object.values(error.response?.data?.errors || {})?.[0]?.[0]
                                || 'Unable to add service offered option.',
                        });
                    }).finally(() => {
                        this.isCreating = false;
                    });
                },

                persistSelectedOptions(previousSelectedIds = null, successMessage = null, closeAfterSave = false) {
                    const serviceIds = this.selectedIdList.filter(selectedId => {
                        return Number(selectedId) !== Number(this.otherOption?.id);
                    });

                    this.selectedIdList = serviceIds;

                    return this.$axios.put(this.updateUrl, {
                        entity_type: 'leads',
                        services: serviceIds,
                        service_offered: serviceIds,
                    }).then(response => {
                        this.$emitter.emit('add-flash', {
                            type: 'success',
                            message: successMessage || response.data.message,
                        });

                        if (closeAfterSave) {
                            this.isOpen = false;
                        }
                    }).catch(error => {
                        if (previousSelectedIds) {
                            this.selectedIdList = previousSelectedIds;
                        }

                        this.$emitter.emit('add-flash', {
                            type: 'error',
                            message: error.response?.data?.message || 'Update failed.',
                        });
                    });
                },

                unselectOtherOption(clearAddMode = true) {
                    if (! this.otherOption) {
                        return;
                    }

                    const otherId = Number(this.otherOption.id);
                    this.selectedIdList = this.selectedIdList.filter(selectedId => selectedId !== otherId);

                    if (clearAddMode) {
                        this.isAddingOther = false;
                    }
                },

                isOtherOption(option) {
                    return String(option?.name || '').trim().toLowerCase() === 'other';
                },

                isSelectedOption(option) {
                    if (this.isOtherOption(option)) {
                        return this.isOtherSelected;
                    }

                    return this.selectedIdList.includes(Number(option.id));
                },
            },
        });
    </script>
@endPushOnce
