{!! view_render_event('admin.leads.index.kanban.search.before') !!}

<v-kanban-search
    :is-loading="isLoading"
    :available="available"
    :applied="applied"
    @search="search"
>
</v-kanban-search>

{!! view_render_event('admin.leads.index.kanban.search.after') !!}

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-kanban-search-template"
    >
        <div class="relative flex w-1/2 items-center max-md:w-full">
            <div class="icon-search absolute top-1.5 flex items-center text-2xl ltr:left-3 rtl:right-3"></div>

            <input
                type="text"
                name="search"
                v-model="searchTerm"
                class="block w-full rounded-lg border bg-white py-1.5 leading-6 text-gray-600 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400 dark:focus:border-gray-400 ltr:pl-10 ltr:pr-10 rtl:pl-10 rtl:pr-10"
                placeholder="@lang('admin::app.leads.index.kanban.toolbar.search.title')"
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
    </script>

    <script type="module">
        app.component('v-kanban-search', {
            template: '#v-kanban-search-template',

            props: ['isLoading', 'available', 'applied'],

            emits: ['search'],

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

                searchNow() {
                    clearTimeout(this.debounceTimer);
                    this.isPending = false;
                    this.commitSearch();
                },

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

                search($event) {
                    this.searchTerm = $event.target.value;
                    this.searchNow();
                },

                getSearchedValues() {
                    let appliedColumn = this.filters.columns.find(column => column.index === 'all');

                    return appliedColumn?.value ?? [];
                },
            },
        });
    </script>
@endPushOnce

@pushOnce('styles')
    <style>
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
    </style>
@endPushOnce
