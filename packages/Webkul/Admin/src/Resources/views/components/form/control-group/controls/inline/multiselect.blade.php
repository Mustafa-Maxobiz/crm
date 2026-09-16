@props([
    'allowEdit' => true,
    'data'      => [],
    'canAddNew' => false,
    'storeUrl'  => null,
])

<v-inline-multi-select-edit
    {{ $attributes->except(['data', 'canAddNew', 'storeUrl']) }}
    :data="{{ json_encode($data) }}"
    :allow-edit="{{ $allowEdit ? 'true' : 'false' }}"
    :can-add-new="{{ $canAddNew ? 'true' : 'false' }}"
    store-url="{{ $storeUrl }}"
>
    <div class="group w-full max-w-full hover:rounded-sm">
        <div class="rounded-xs flex h-[34px] items-center pl-2.5 text-left">
            <div class="shimmer h-5 w-48 rounded border border-transparent"></div>
        </div>
    </div>
</v-inline-multi-select-edit>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-inline-multi-select-edit-template"
    >
        <div class="group w-full max-w-full hover:rounded-sm">
            <!-- Non-editing view -->
            <div
                v-if="! isEditing"
                class="flex h-[34px] items-center rounded border border-transparent transition-all"
                :class="allowEdit ? 'hover:bg-gray-100 dark:hover:bg-gray-800' : ''"
            >
                <x-admin::form.control-group.control
                    type="hidden"
                    ::id="name"
                    ::name="name"
                    v-model="inputValue"
                />

                <div
                    class="group relative h-[18px] !w-full pl-2.5"
                    :style="{ 'text-align': position }"
                >
                    <span class="cursor-pointer truncate rounded">
                        @{{ valueLabel ? valueLabel : selectedValue?.length > 20 ? selectedValue.substring(0, 20) + '...' : selectedValue }}
                    </span>
                    
                    <!-- Tooltip -->
                    <div
                        class="absolute bottom-0 mb-5 hidden flex-col group-hover:flex"
                        v-if="selectedValue?.length > 20"
                    >
                        <span class="whitespace-no-wrap relative z-10 rounded-md bg-black px-4 py-2 text-xs leading-none text-white shadow-lg dark:bg-white dark:text-gray-900">
                            @{{ selectedValue }}
                        </span>

                        <div class="-mt-2 ml-4 h-3 w-3 rotate-45 bg-black dark:bg-white"></div>
                    </div>
                </div>

                <template v-if="allowEdit">
                    <i
                        @click="toggle"
                        class="icon-edit cursor-pointer rounded p-0.5 text-2xl opacity-0 hover:bg-gray-200 group-hover:opacity-100 dark:hover:bg-gray-950 ltr:mr-1 rtl:ml-1"
                    ></i>
                </template>
            </div>

            <x-admin::form.control-group.error ::name="name"/>
        </div>

        <!-- Editing view -->
        <div
            class="relative flex w-full flex-col"
            ref="dropdownContainer"
            v-if="isEditing"
        >
            <div class="flex min-h-[38px] w-full flex-wrap items-center gap-1 rounded border border-gray-200 px-2.5 py-1.5 text-sm font-normal text-gray-800 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400 dark:focus:border-gray-400 ltr:pr-16 rtl:pl-16">
                <ul class="flex flex-wrap items-center gap-1">
                    <li
                        v-for="option in selectedDisplayOptions"
                        :key="option.id"
                        class="flex items-center gap-1 rounded-md bg-slate-100 pl-2 dark:bg-gray-800 dark:text-white"
                    >
                        <p class="max-w-[110px] truncate">@{{ option.name }}</p>

                        <span
                            class="icon-cross-large cursor-pointer p-0.5 text-xl"
                            @click="removeOption(option)"
                        ></span>
                    </li>
                </ul>

                <input
                    v-if="! isOtherSelected"
                    type="text"
                    v-model="searchTerm"
                    class="min-w-[100px] flex-1 border-0 bg-transparent p-0 text-sm outline-none dark:bg-transparent"
                    :placeholder="placeholder"
                    @keydown.enter.prevent="handleEnter"
                />
            </div>

            <!-- Dropdown (position dynamic based on space) -->
            <div
                class="absolute z-50 w-full origin-top transform rounded-lg border bg-white shadow-lg dark:border-gray-800 dark:bg-gray-800"
                :class="dropdownPosition === 'bottom' ? 'top-full mt-1' : 'bottom-full mb-1'"
                v-if="filteredOptions.length || (canAddNew && isOtherSelected)"
            >
                <ul class="max-h-40 divide-gray-100 overflow-y-auto p-0.5">
                    <template
                        v-for="option in filteredOptions"
                        :key="option.id"
                    >
                        <li
                            class="flex cursor-pointer items-center gap-2 rounded px-4 py-2 text-gray-800 transition-colors hover:bg-blue-100 dark:text-white dark:hover:bg-gray-950"
                            @click="addOption(option)"
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
                        </li>

                        <li
                            v-if="canAddNew && isOtherOption(option) && isOtherSelected"
                            class="border-y border-gray-100 px-3 py-2 dark:border-gray-700"
                        >
                            <div class="flex gap-2">
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
                                    @click="createOption"
                                >
                                    @{{ isCreating ? creatingLabel : 'Add' }}
                                </button>
                            </div>
                        </li>
                    </template>
                </ul>
            </div>
                
            <!-- Action Buttons -->
            <div class="absolute top-1/2 flex -translate-y-1/2 transform gap-0.5 ltr:right-2 rtl:left-2">
                <button
                    type="button"
                    class="flex items-center justify-center bg-green-100 p-1 hover:bg-green-200 ltr:rounded-l-md rtl:rounded-r-md"
                    @click="save"
                >
                    <i class="icon-tick text-md cursor-pointer font-bold text-green-600 dark:!text-green-600" />
                </button>
            
                <button
                    type="button"
                    class="flex items-center justify-center bg-red-100 p-1 hover:bg-red-200 ltr:rounded-r-md rtl:rounded-l-md"
                    @click="cancel"
                >
                    <i class="icon-cross-large text-md cursor-pointer font-bold text-red-600 dark:!text-red-600" />
                </button>
            </div>
        </div>
    </script>

    <script type="module">
        app.component('v-inline-multi-select-edit', {
            template: '#v-inline-multi-select-edit-template',

            emits: ['options-updated'],

            props: {
                name: {
                    type: String,
                    required: true,
                },

                value: {
                    required: true,
                },

                rules: {
                    type: String,
                    default: '',
                },

                label: {
                    type: String,
                    default: '',
                },

                placeholder: {
                    type: String,
                    default: '',
                },

                position: {
                    type: String,
                    default: 'right',
                },

                allowEdit: {
                    type: Boolean,
                    default: true,
                },

                errors: {
                    type: Object,
                    default: {},
                },

                data: {
                    type: Array,
                    required: true,
                },

                url: {
                    type: String,
                    default: '',
                },

                valueLabel: {
                    type: String,
                    default: '',
                },

                canAddNew: {
                    type: Boolean,
                    default: false,
                },

                storeUrl: {
                    type: String,
                    default: '',
                },
            },

            data() {
                return {
                    inputValue: this.value,
                    isEditing: false,
                    allOptions: [...(this.data ?? [])],
                    options: [],
                    tempOptions: [],
                    searchTerm: '',
                    newOptionName: '',
                    isAddingOther: false,
                    isCreating: false,
                    isDropdownOpen: false,
                    dropdownPosition: 'bottom',
                    addLabel: @json(__('admin::app.leads.services-offered.add-option')),
                    creatingLabel: @json(__('admin::app.leads.services-offered.creating-option')),
                };
            },

            mounted() {
                this.syncFromValue();
                window.addEventListener('resize', this.setDropdownPosition);
            },

            computed: {
                selectedIds() {
                    if (Array.isArray(this.value)) {
                        return this.value.map(Number).filter(Boolean);
                    }

                    if (this.value === null || this.value === undefined || this.value === '') {
                        return [];
                    }

                    return String(this.value)
                        .split(',')
                        .map(id => Number(id.trim()))
                        .filter(Boolean);
                },

                selectedValue() {
                    if (this.selectedDisplayOptions.length === 0) {
                        return null;
                    }

                    return this.selectedDisplayOptions.map(data => data.name).join(', ');
                },

                selectedDisplayOptions() {
                    return this.tempOptions.filter(option => ! this.isOtherOption(option));
                },

                filteredOptions() {
                    const term = this.searchTerm.trim().toLowerCase();
                    const otherOption = this.otherOption;
                    const otherId = otherOption ? Number(otherOption.id) : null;

                    if (this.isOtherSelected && ! term && otherOption) {
                        return [otherOption];
                    }

                    return this.allOptions.filter(option => {
                        const optionId = Number(option.id);
                        const isSelected = this.tempOptions.some(data => Number(data.id) === optionId);

                        if (isSelected && optionId !== otherId) {
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

                    const exists = this.allOptions.some(
                        option => String(option.name).toLowerCase() === term.toLowerCase()
                    );

                    return exists ? '' : term;
                },

                addButtonLabel() {
                    return this.addLabel.replace(':name', this.searchableNewLabel);
                },

                otherOption() {
                    return this.allOptions.find(
                        option => String(option.name || '').trim().toLowerCase() === 'other'
                    );
                },

                isOtherSelected() {
                    if (! this.otherOption) {
                        return false;
                    }

                    return this.isAddingOther || this.tempOptions.some(option => Number(option.id) === Number(this.otherOption.id));
                },
            },

            methods: {
                syncFromValue() {
                    const selected = this.selectedIds;

                    this.tempOptions = this.allOptions.filter(option => selected.includes(Number(option.id)));
                    this.options = this.allOptions.filter(option => ! selected.includes(Number(option.id)));
                },

                toggle() {
                    this.isEditing = true;
                    this.isDropdownOpen = true;
                    this.searchTerm = '';
                    this.syncFromValue();
                    this.setDropdownPosition();
                },

                save() {
                    if (this.errors[this.name]) {
                        return;
                    }

                    this.isEditing = false;

                    const selectedIds = this.tempOptions.map(data => data.id);
                    this.inputValue = selectedIds.join(',');

                    if (this.url) {
                        this.$axios.put(this.url, {
                            [this.name]: selectedIds,
                        }).then(response => {
                            this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                        }).catch(error => {
                            this.inputValue = this.value;
                            this.syncFromValue();
                            this.$emitter.emit('add-flash', {
                                type: 'error',
                                message: error.response?.data?.message || 'Update failed.',
                            });
                        });
                    }

                    this.$emit('options-updated', {
                        name: this.name,
                        value: selectedIds,
                    });
                },

                cancel() {
                    this.isEditing = false;
                    this.searchTerm = '';
                    this.newOptionName = '';
                    this.isAddingOther = false;
                    this.syncFromValue();
                },

                addOption(option) {
                    if (this.isOtherOption(option)) {
                        this.isAddingOther = true;
                        this.unselectOtherOption(false);
                        this.searchTerm = '';

                        this.$nextTick(() => {
                            this.$refs.newOptionInput?.focus();
                        });

                        return;
                    }

                    if (! this.tempOptions.some(data => Number(data.id) === Number(option.id))) {
                        this.tempOptions.push(option);
                        this.options = this.options.filter(data => Number(data.id) !== Number(option.id));
                        this.searchTerm = '';
                    }
                },

                removeOption(option) {
                    if (! this.options.some(data => Number(data.id) === Number(option.id))) {
                        this.options.push(option);
                        this.tempOptions = this.tempOptions.filter(data => Number(data.id) !== Number(option.id));
                    }

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
                    if (! this.canAddNew || ! this.storeUrl || ! this.searchableNewLabel || this.isCreating) {
                        return;
                    }

                    this.isCreating = true;

                    this.$axios.post(this.storeUrl, {
                        name: this.searchableNewLabel,
                    }).then(response => {
                        const option = response.data.data;
                        const normalizedOption = {
                            id: option.id,
                            name: option.name,
                            is_show: option.is_show ?? false,
                        };

                        this.allOptions.push(normalizedOption);

                        this.addOption(normalizedOption);
                        this.unselectOtherOption();
                        this.newOptionName = '';

                        const selectedIds = this.tempOptions.map(data => data.id);
                        this.inputValue = selectedIds.join(',');

                        if (this.url) {
                            this.$axios.put(this.url, {
                                [this.name]: selectedIds,
                            }).then(() => {
                                this.isEditing = false;

                                this.$emit('options-updated', {
                                    name: this.name,
                                    value: selectedIds,
                                });

                                this.$emitter.emit('add-flash', {
                                    type: 'success',
                                    message: response.data.message,
                                });
                            }).catch(error => {
                                this.inputValue = this.value;
                                this.syncFromValue();

                                this.$emitter.emit('add-flash', {
                                    type: 'error',
                                    message: error.response?.data?.message || 'Update failed.',
                                });
                            });

                            return;
                        }

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

                unselectOtherOption(clearAddMode = true) {
                    if (! this.otherOption) {
                        return;
                    }

                    const otherId = Number(this.otherOption.id);
                    this.tempOptions = this.tempOptions.filter(option => Number(option.id) !== otherId);

                    if (! this.options.some(option => Number(option.id) === otherId)) {
                        this.options.push(this.otherOption);
                    }

                    if (clearAddMode) {
                        this.isAddingOther = false;
                    }
                },

                isOtherOption(option) {
                    return String(option?.name || '').trim().toLowerCase() === 'other';
                },

                isSelectedOption(option) {
                    return this.tempOptions.some(data => Number(data.id) === Number(option.id));
                },

                setDropdownPosition() {
                    this.$nextTick(() => {
                        const dropdownContainer = this.$refs.dropdownContainer;

                        if (! dropdownContainer) {
                            return;
                        }

                        const dropdownRect = dropdownContainer.getBoundingClientRect();
                        const viewportHeight = window.innerHeight;

                        this.dropdownPosition = dropdownRect.bottom + 250 > viewportHeight
                            ? 'top'
                            : 'bottom';
                    });
                },
            },
        });
    </script>
@endPushOnce
