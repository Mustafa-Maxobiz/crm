@props([
    'organizations' => collect(),
    'selectedIds' => [],
    'name' => 'organization_ids',
    'perPage' => 20,
])

@php
    $organizationsPayload = collect($organizations)->map(fn ($organization) => [
        'id'   => (int) $organization->id,
        'name' => $organization->name,
    ])->values()->all();

    $selectedPayload = collect($selectedIds)->map(fn ($id) => (int) $id)->filter()->values()->all();
@endphp

<v-organization-checkboxes
    :organizations='@json($organizationsPayload)'
    :initial-selected='@json($selectedPayload)'
    name="{{ $name }}"
    :per-page="{{ (int) $perPage }}"
>
    <div class="grid gap-2 sm:grid-cols-2">
        @for ($i = 0; $i < min(4, count($organizationsPayload)); $i++)
            <div class="shimmer h-10 rounded border border-transparent"></div>
        @endfor
    </div>
</v-organization-checkboxes>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-organization-checkboxes-template"
    >
        <div class="space-y-3">
            <div
                v-if="organizations.length"
                class="flex flex-wrap items-center justify-between gap-2 text-sm text-gray-600 dark:text-gray-400"
            >
                <span>
                    @{{ rangeLabel }}
                </span>

                <label class="flex items-center gap-2">
                    <span>@lang('admin::app.components.datagrid.toolbar.per-page')</span>
                    <select
                        v-model.number="pageSize"
                        class="rounded border border-gray-200 bg-white px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                        @change="currentPage = 1"
                    >
                        <option
                            v-for="size in pageSizeOptions"
                            :key="size"
                            :value="size"
                        >
                            @{{ size }}
                        </option>
                    </select>
                </label>
            </div>

            <div class="grid gap-2 sm:grid-cols-2">
                <label
                    v-for="organization in pageItems"
                    :key="organization.id"
                    class="flex items-center gap-2 rounded border border-gray-200 px-3 py-2 text-sm dark:border-gray-700"
                >
                    <input
                        type="checkbox"
                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800"
                        :checked="isSelected(organization.id)"
                        @change="toggle(organization.id, $event.target.checked)"
                    />

                    <span class="dark:text-white">@{{ organization.name }}</span>
                </label>

                <p
                    v-if="! organizations.length"
                    class="text-sm text-gray-500 dark:text-gray-400"
                >
                    @lang('admin::app.settings.access-scope.no-companies')
                </p>
            </div>

            <div
                v-if="totalPages > 1"
                class="flex items-center justify-between gap-2"
            >
                <button
                    type="button"
                    class="rounded border border-gray-200 px-3 py-1.5 text-sm text-gray-700 transition-all hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                    :disabled="currentPage <= 1"
                    @click="currentPage--"
                >
                    Previous
                </button>

                <span class="text-sm text-gray-600 dark:text-gray-400">
                    @{{ currentPage }} / @{{ totalPages }}
                </span>

                <button
                    type="button"
                    class="rounded border border-gray-200 px-3 py-1.5 text-sm text-gray-700 transition-all hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                    :disabled="currentPage >= totalPages"
                    @click="currentPage++"
                >
                    Next
                </button>
            </div>

            <input
                v-for="id in selected"
                :key="'org-' + id"
                type="hidden"
                :name="name + '[]'"
                :value="id"
            />
        </div>
    </script>

    <script type="module">
        app.component('v-organization-checkboxes', {
            template: '#v-organization-checkboxes-template',

            props: {
                organizations: {
                    type: Array,
                    default: () => [],
                },

                initialSelected: {
                    type: Array,
                    default: () => [],
                },

                name: {
                    type: String,
                    default: 'organization_ids',
                },

                perPage: {
                    type: Number,
                    default: 20,
                },
            },

            data() {
                return {
                    selected: [...new Set((this.initialSelected || []).map((id) => Number(id)))],
                    currentPage: 1,
                    pageSize: this.perPage || 20,
                    pageSizeOptions: [20, 50, 100],
                };
            },

            computed: {
                totalPages() {
                    return Math.max(1, Math.ceil(this.organizations.length / this.pageSize));
                },

                pageItems() {
                    const start = (this.currentPage - 1) * this.pageSize;

                    return this.organizations.slice(start, start + this.pageSize);
                },

                rangeLabel() {
                    if (! this.organizations.length) {
                        return '';
                    }

                    const start = (this.currentPage - 1) * this.pageSize + 1;
                    const end = Math.min(this.currentPage * this.pageSize, this.organizations.length);

                    return `${start}-${end} of ${this.organizations.length}`;
                },
            },

            watch: {
                pageSize() {
                    if (this.currentPage > this.totalPages) {
                        this.currentPage = this.totalPages;
                    }
                },
            },

            methods: {
                isSelected(id) {
                    return this.selected.includes(Number(id));
                },

                toggle(id, checked) {
                    const value = Number(id);

                    if (checked) {
                        if (! this.selected.includes(value)) {
                            this.selected.push(value);
                        }

                        return;
                    }

                    this.selected = this.selected.filter((item) => item !== value);
                },
            },
        });
    </script>
@endPushOnce
