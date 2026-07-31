@props([
    'attribute'   => null,
    'value'       => null,
    'validations' => '',
    'canAddNew'   => false,
    'storeUrl'    => null,
])

@php
    $options = $attribute->lookup_type
        ? app('Webkul\Attribute\Repositories\AttributeRepository')->getLookUpOptions($attribute->lookup_type)
        : $attribute->options()->orderBy('sort_order')->get(['id', 'name']);

    $selectedOption = old($attribute->code) ?: $value;

    if (is_array($selectedOption)) {
        $selectedIds = array_values(array_filter(array_map('intval', $selectedOption)));
    } else {
        $selectedIds = array_values(array_filter(array_map('intval', explode(',', (string) $selectedOption))));
    }

    $canAddNew = (bool) $canAddNew && filled($storeUrl);
@endphp

<v-attribute-multiselect
    name="{{ $attribute->code }}"
    label="{{ $attribute->name }}"
    placeholder="{{ $attribute->name }}"
    rules="{{ $validations }}"
    :options='@json($options)'
    :selected-ids='@json($selectedIds)'
    :can-add-new='@json($canAddNew)'
    store-url="{{ $storeUrl }}"
>
</v-attribute-multiselect>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-attribute-multiselect-template"
    >
        <div class="relative w-full">
            <div
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
                v-if="isOpen && (filteredOptions.length || (canAddNew && searchableNewLabel))"
                class="absolute left-0 right-0 z-20 mt-1 max-h-48 overflow-auto rounded-md border border-gray-200 bg-white shadow-lg dark:border-gray-800 dark:bg-gray-900"
            >
                <button
                    type="button"
                    v-for="option in filteredOptions"
                    :key="option.id"
                    class="block w-full px-3 py-2 text-left text-sm text-gray-800 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                    @mousedown.prevent="addOption(option)"
                >
                    @{{ option.name }}
                </button>

                <button
                    type="button"
                    v-if="canAddNew && searchableNewLabel"
                    class="block w-full border-t border-gray-100 px-3 py-2 text-left text-sm font-medium text-brandColor hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800"
                    :disabled="isCreating"
                    @mousedown.prevent="createOption"
                >
                    @{{ isCreating ? creatingLabel : addButtonLabel }}
                </button>
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
            },

            data() {
                return {
                    availableOptions: [...this.options],
                    selectedIdList: [...this.selectedIds].map(Number),
                    searchTerm: '',
                    isOpen: false,
                    isCreating: false,
                    addLabel: @json(__('admin::app.leads.services-offered.add-option')),
                    creatingLabel: @json(__('admin::app.leads.services-offered.creating-option')),
                };
            },

            computed: {
                selectedOptions() {
                    return this.availableOptions.filter(option => this.selectedIdList.includes(Number(option.id)));
                },

                filteredOptions() {
                    const term = this.searchTerm.trim().toLowerCase();

                    return this.availableOptions.filter(option => {
                        if (this.selectedIdList.includes(Number(option.id))) {
                            return false;
                        }

                        if (! term) {
                            return true;
                        }

                        return String(option.name).toLowerCase().includes(term);
                    });
                },

                searchableNewLabel() {
                    const term = this.searchTerm.trim();

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
            },

            mounted() {
                document.addEventListener('click', this.handleOutsideClick);
            },

            beforeUnmount() {
                document.removeEventListener('click', this.handleOutsideClick);
            },

            methods: {
                focusSearch() {
                    this.isOpen = true;
                    this.$refs.searchInput?.focus();
                },

                handleOutsideClick(event) {
                    if (! this.$el.contains(event.target)) {
                        this.isOpen = false;
                    }
                },

                addOption(option) {
                    const id = Number(option.id);

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
                },

                handleEnter() {
                    if (this.filteredOptions.length) {
                        this.addOption(this.filteredOptions[0]);
                        return;
                    }

                    if (this.canAddNew && this.searchableNewLabel) {
                        this.createOption();
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

                        this.availableOptions.push({
                            id: option.id,
                            name: option.name,
                        });

                        this.addOption(option);

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
            },
        });
    </script>
@endPushOnce
