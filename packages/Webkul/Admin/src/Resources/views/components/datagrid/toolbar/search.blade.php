<v-datagrid-search
    :is-loading="isLoading"
    :available="available"
    :applied="applied"
    :src="src"
    @search="search"
    @filter="filter"
    @applySavedFilter="applySavedFilter"
>
    {{ $slot }}
</v-datagrid-search>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-datagrid-search-template"
    >
        <slot
            name="search"
            :available="available"
            :applied="applied"
            :search="search"
            :get-searched-values="getSearchedValues"
        >
            <div class="datagrid-toolbar-search-row flex w-full min-w-0 flex-1 items-center gap-x-1.5">
                <!-- Search Panel -->
                <div class="datagrid-toolbar-search flex min-w-0 flex-1 items-center">
                    <div class="relative w-full">
                        <div class="icon-search absolute top-1.5 flex items-center text-2xl ltr:left-3 rtl:right-3"></div>

                        <input
                            type="text"
                            name="search"
                            v-model="searchTerm"
                            class="block w-full rounded-lg border bg-white py-1.5 leading-6 text-gray-600 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400 dark:focus:border-gray-400 ltr:pl-10 ltr:pr-10 rtl:pl-10 rtl:pr-10"
                            placeholder="@lang('admin::app.components.datagrid.toolbar.search.title')"
                            autocomplete="off"
                            @input="queueSearch"
                            @keyup.enter="searchNow"
                        >

                        <div
                            v-if="isPending || isLoading"
                            class="absolute top-1/2 -translate-y-1/2 ltr:right-3 rtl:left-3"
                            title="Searching..."
                        >
                            <div class="app-search-spinner"></div>
                        </div>
                    </div>
                </div>

                <!-- Filter Panel -->
                <x-admin::datagrid.toolbar.filter>
                    <template #filter="{
                        available,
                        applied,
                        filters,
                        applyFilter,
                        applyColumnValues,
                        findAppliedColumn,
                        hasAnyAppliedColumnValues,
                        getAppliedColumnValues,
                        removeAppliedColumnValue,
                        removeAppliedColumnAllValues
                    }">
                        <slot
                            name="filter"
                            :available="available"
                            :applied="applied"
                            :filters="filters"
                            :apply-filter="applyFilter"
                            :apply-column-values="applyColumnValues"
                            :find-applied-column="findAppliedColumn"
                            :has-any-applied-column-values="hasAnyAppliedColumnValues"
                            :get-applied-column-values="getAppliedColumnValues"
                            :remove-applied-column-value="removeAppliedColumnValue"
                            :remove-applied-column-all-values="removeAppliedColumnAllValues"
                        >
                        </slot>
                    </template>
                </x-admin::datagrid.toolbar.filter>
            </div>
        </slot>
    </script>

    <script type="module">
        app.component('v-datagrid-search', {
            template: '#v-datagrid-search-template',

            props: ['isLoading', 'available', 'applied', 'src'],

            emits: ['search', 'filter', 'applySavedFilter'],

            data() {
                return {
                    filters: {
                        columns: [],
                    },
                    searchTerm: '',
                    isPending: false,
                    debounceTimer: null,
                    debounceMs: 2000,
                };
            },

            mounted() {
                this.filters.columns = this.applied.filters.columns.filter((column) => column.index === 'all');
                this.searchTerm = this.getSearchTerm();
            },

            beforeUnmount() {
                clearTimeout(this.debounceTimer);
            },

            methods: {
                getSearchTerm() {
                    const appliedColumn = this.filters.columns.find(column => column.index === 'all');
                    const value = appliedColumn?.value ?? [];

                    return Array.isArray(value) ? (value[0] || '') : (value || '');
                },

                /**
                 * Wait for typing to pause, then search.
                 * Clearing the field resets immediately (no debounce).
                 */
                queueSearch() {
                    clearTimeout(this.debounceTimer);

                    if (! (this.searchTerm || '').trim()) {
                        this.isPending = false;
                        this.commitSearch();

                        return;
                    }

                    this.isPending = true;

                    this.debounceTimer = setTimeout(() => {
                        this.isPending = false;
                        this.commitSearch();
                    }, this.debounceMs);
                },

                /**
                 * Search immediately (Enter).
                 */
                searchNow() {
                    clearTimeout(this.debounceTimer);
                    this.isPending = false;
                    this.commitSearch();
                },

                /**
                 * Apply the current search term to filters and emit.
                 */
                commitSearch() {
                    const requestedValue = (this.searchTerm || '').trim();
                    let appliedColumn = this.filters.columns.find(column => column.index === 'all');

                    if (! requestedValue) {
                        if (appliedColumn) {
                            appliedColumn.value = [];
                        }

                        this.$emit('search', this.filters);

                        return;
                    }

                    if (appliedColumn) {
                        appliedColumn.value = [requestedValue];
                    } else {
                        this.filters.columns.push({
                            index: 'all',
                            value: [requestedValue],
                        });
                    }

                    this.$emit('search', this.filters);
                },

                /**
                 * Perform a search operation based on the input value.
                 *
                 * @param {Event} $event
                 * @returns {void}
                 */
                search($event) {
                    this.searchTerm = $event.target.value;
                    this.searchNow();
                },

                filter(filter) {
                    this.$emit('filter', filter);
                },

                applySavedFilter(filter) {
                    this.$emit('applySavedFilter', filter);
                },

                /**
                 * Get the searched values for a specific column.
                 *
                 * @param {string} columnIndex
                 * @returns {Array}
                 */
                getSearchedValues(columnIndex) {
                    let appliedColumn = this.filters.columns.find(column => column.index === 'all');

                    return appliedColumn?.value ?? [];
                },
            },
        });
    </script>
@endPushOnce

@pushOnce('styles')
    <style>
        .datagrid-toolbar-search-row {
            width: 100%;
            max-width: 720px;
        }

        .datagrid-toolbar-search {
            width: 100%;
            min-width: 0;
        }

        .datagrid-search-spinner {
            width: 16px;
            height: 16px;
            border: 2px solid #d1d5db;
            border-top-color: #f97316;
            border-radius: 9999px;
            animation: datagrid-search-spin 0.7s linear infinite;
        }

        .dark .datagrid-search-spinner {
            border-color: #4b5563;
            border-top-color: #fb923c;
        }

        @keyframes datagrid-search-spin {
            to {
                transform: rotate(360deg);
            }
        }

        @media (max-width: 767px) {
            .datagrid-toolbar-search-row {
                max-width: 100%;
            }
        }
    </style>
@endPushOnce
