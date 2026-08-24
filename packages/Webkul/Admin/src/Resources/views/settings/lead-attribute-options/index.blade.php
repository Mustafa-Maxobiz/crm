<x-admin::layouts>
    <x-slot:title>
        @lang($lang.'.index.title')
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-2">
                <x-admin::breadcrumbs :name="$breadcrumb" />

                <div class="text-xl font-bold dark:text-gray-300">
                    @lang($lang.'.index.title')
                </div>
            </div>

            <div class="flex items-center gap-x-2.5">
                @if (bouncer()->hasPermission($permission.'.create'))
                    <button
                        type="button"
                        class="primary-button"
                        @click="$refs.optionSettings.openModal()"
                    >
                        @lang($lang.'.index.create-btn')
                    </button>
                @endif
            </div>
        </div>

        @php
            $optionLabels = [
                'createTitle' => __($lang.'.index.create.title'),
                'editTitle'   => __($lang.'.index.edit.title'),
                'name'        => __($lang.'.index.create.name'),
                'sortOrder'   => __($lang.'.index.create.sort-order'),
                'saveBtn'     => __($lang.'.index.create.save-btn'),
            ];
        @endphp

        <v-lead-attribute-option-settings
            ref="optionSettings"
            index-route="{{ route($routePrefix.'.index') }}"
            store-route="{{ route($routePrefix.'.store') }}"
            update-route-template="{{ $updateRouteTemplate }}"
            :labels='@json($optionLabels)'
            :show-service-owner-assignment='@json($showServiceOwnerAssignment ?? false)'
            :assignable-meeting-owners='@json($assignableMeetingOwners ?? [])'
        >
            <x-admin::shimmer.datagrid />
        </v-lead-attribute-option-settings>
    </div>

    @pushOnce('scripts')
        <script
            type="text/x-template"
            id="lead-attribute-option-settings-template"
        >
            <div>
                <x-admin::datagrid
                    ::src="indexRoute"
                    ref="datagrid"
                >
                    <template #body="{
                        isLoading,
                        available,
                        applied,
                        selectAll,
                        sort,
                        performAction,
                        gridRowStyle
                    }">
                        <template v-if="isLoading">
                            <x-admin::shimmer.datagrid.table.body />
                        </template>

                        <template v-else>
                            <div
                                v-for="record in available.records"
                                :key="'desktop-' + record.id"
                                class="row grid items-center gap-2.5 border-b px-4 py-4 text-gray-600 transition-all hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-gray-950 max-lg:hidden"
                                :style="gridRowStyle"
                            >
                                <template
                                    v-for="column in available.columns"
                                    :key="'col-' + record.id + '-' + column.index"
                                >
                                    <div v-if="column.visibility && column.index === 'is_show'" class="min-w-0 text-left">
                                        <button
                                            type="button"
                                            class="inline-flex cursor-pointer items-center justify-center rounded-full px-3 py-1 text-center text-xs font-semibold transition-all"
                                            style="width: 5.5rem; min-width: 5.5rem;"
                                            :class="(record.is_show == 1 || record.is_show === true) ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-red-100 text-red-600 hover:bg-red-200'"
                                            @click="toggleServiceVisibility(record)"
                                        >
                                            @{{ (record.is_show == 1 || record.is_show === true) ? 'Active' : 'Inactive' }}
                                        </button>
                                    </div>
                                    <p
                                        v-else-if="column.visibility"
                                        class="min-w-0 break-words text-left"
                                        v-html="record[column.index] || '--'"
                                    ></p>
                                </template>

                                <div
                                    class="flex h-full items-center justify-end place-self-end"
                                    v-if="available.actions.length"
                                >
                                    <a
                                        v-if="getAction(record, 'edit')"
                                        @click="selectedOption=true; editModal(getAction(record, 'edit').url)"
                                    >
                                        <span
                                            :class="getAction(record, 'edit').icon"
                                            class="cursor-pointer rounded-md p-1.5 text-2xl transition-all hover:bg-gray-200 dark:hover:bg-gray-800 max-sm:place-self-center"
                                        ></span>
                                    </a>

                                    <a
                                        v-if="getAction(record, 'delete')"
                                        @click="performAction(getAction(record, 'delete'))"
                                    >
                                        <span
                                            :class="getAction(record, 'delete').icon"
                                            class="cursor-pointer rounded-md p-1.5 text-2xl transition-all hover:bg-gray-200 dark:hover:bg-gray-800 max-sm:place-self-center"
                                        ></span>
                                    </a>
                                </div>
                            </div>

                            <div
                                class="hidden border-b px-4 py-4 text-black dark:border-gray-800 dark:text-gray-300 max-lg:block"
                                v-for="record in available.records"
                                :key="'mobile-' + record.id"
                            >
                                <div class="mb-2 flex items-center justify-end gap-2">
                                    <a
                                        v-if="getAction(record, 'edit')"
                                        @click="selectedOption=true; editModal(getAction(record, 'edit').url)"
                                    >
                                        <span
                                            :class="getAction(record, 'edit').icon"
                                            class="cursor-pointer rounded-md p-1.5 text-2xl transition-all hover:bg-gray-200 dark:hover:bg-gray-800"
                                        ></span>
                                    </a>

                                    <a
                                        v-if="getAction(record, 'delete')"
                                        @click="performAction(getAction(record, 'delete'))"
                                    >
                                        <span
                                            :class="getAction(record, 'delete').icon"
                                            class="cursor-pointer rounded-md p-1.5 text-2xl transition-all hover:bg-gray-200 dark:hover:bg-gray-800"
                                        ></span>
                                    </a>
                                </div>

                                <div class="grid gap-2">
                                    <template v-for="column in available.columns">
                                        <div class="flex flex-wrap items-baseline gap-x-2">
                                            <span class="text-slate-600 dark:text-gray-300" v-html="column.label + ':'"></span>
                                            <span class="break-words font-medium text-slate-900 dark:text-white" v-html="record[column.index] || '--'"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </template>
                </x-admin::datagrid>

                <x-admin::form
                    v-slot="{ meta, errors, handleSubmit }"
                    as="div"
                    ref="modalForm"
                >
                    <form @submit="handleSubmit($event, updateOrCreate)">
                        <x-admin::modal ref="optionUpdateAndCreateModal">
                            <x-slot:header>
                                <p class="text-lg font-bold text-gray-800 dark:text-white">
                                    @{{ selectedOption ? labels.editTitle : labels.createTitle }}
                                </p>
                            </x-slot>

                            <x-slot:content>
                                <x-admin::form.control-group.control
                                    type="hidden"
                                    name="id"
                                />

                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label class="required">
                                        @{{ labels.name }}
                                    </x-admin::form.control-group.label>

                                    <x-admin::form.control-group.control
                                        type="text"
                                        id="name"
                                        name="name"
                                        rules="required"
                                        ::label="labels.name"
                                        ::placeholder="labels.name"
                                    />

                                    <x-admin::form.control-group.error control-name="name" />
                                </x-admin::form.control-group>

                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label>
                                        @{{ labels.sortOrder }}
                                    </x-admin::form.control-group.label>

                                    <x-admin::form.control-group.control
                                        type="text"
                                        id="sort_order"
                                        name="sort_order"
                                        rules="numeric|min_value:1"
                                        ::label="labels.sortOrder"
                                        ::placeholder="labels.sortOrder"
                                    />

                                    <x-admin::form.control-group.error control-name="sort_order" />
                                </x-admin::form.control-group>

                                <div v-if="showServiceOwnerAssignment" class="mb-4 flex items-center gap-3">
                                    <label class="relative inline-flex cursor-pointer items-center">
                                        <input
                                            type="checkbox"
                                            name="is_show"
                                            class="peer sr-only"
                                            v-model="isShow"
                                        />
                                        <div class="peer h-5 w-9 rounded-full bg-gray-200 after:absolute after:left-[2px] after:top-[2px] after:h-4 after:w-4 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-600 peer-checked:after:translate-x-full peer-checked:after:border-white dark:bg-gray-700"></div>
                                    </label>
                                    <span class="text-sm font-medium text-gray-800 dark:text-white">
                                        Show in Dropdowns
                                    </span>
                                </div>

                                <div v-if="showServiceOwnerAssignment" class="grid gap-2">
                                    <label class="text-sm font-medium text-gray-800 dark:text-white">
                                        Eligible Lead Owners
                                    </label>

                                    <div class="max-h-48 overflow-auto rounded-md border border-gray-200 p-3 dark:border-gray-800">
                                        <label
                                            v-for="user in assignableMeetingOwners"
                                            :key="'service-owner-' + user.id"
                                            class="mb-2 flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"
                                        >
                                            <input
                                                type="checkbox"
                                                :value="Number(user.id)"
                                                v-model="selectedUserIds"
                                            />

                                            <span>
                                                @{{ user.name }}
                                                <template v-if="user.role_name">(@{{ user.role_name }})</template>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </x-slot>

                            <x-slot:footer>
                                <x-admin::button
                                    button-type="submit"
                                    class="primary-button justify-center"
                                    ::title="labels.saveBtn"
                                    ::loading="isProcessing"
                                    ::disabled="isProcessing"
                                />
                            </x-slot>
                        </x-admin::modal>
                    </form>
                </x-admin::form>
            </div>
        </script>

        <script type="module">
            app.component('v-lead-attribute-option-settings', {
                template: '#lead-attribute-option-settings-template',

                props: {
                    indexRoute: {
                        type: String,
                        required: true,
                    },

                    storeRoute: {
                        type: String,
                        required: true,
                    },

                    updateRouteTemplate: {
                        type: String,
                        required: true,
                    },

                    labels: {
                        type: Object,
                        required: true,
                    },

                    showServiceOwnerAssignment: {
                        type: Boolean,
                        default: false,
                    },

                    assignableMeetingOwners: {
                        type: Array,
                        default: () => [],
                    },
                },

                data() {
                    return {
                        isProcessing: false,
                        selectedOption: false,
                        selectedUserIds: [],
                        isShow: false,
                    };
                },

                methods: {
                    getAction(record, index) {
                        return (record.actions || []).find(action => action.index === index);
                    },

                    toggleServiceVisibility(record) {
                        this.$axios.post("{{ route('admin.settings.services_offered.toggle_visibility', '__ID__') }}".replace('__ID__', record.id))
                            .then(response => {
                                this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                                this.$refs.datagrid.get();
                            })
                            .catch(error => {
                                this.$emitter.emit('add-flash', { type: 'error', message: 'Failed to update visibility.' });
                                this.$refs.datagrid.get();
                            });
                    },

                    openModal() {
                        this.selectedOption = false;
                        this.selectedUserIds = [];
                        this.isShow = false;
                        this.$refs.optionUpdateAndCreateModal.toggle();
                    },

                    updateOrCreate(params, { resetForm, setErrors }) {
                        this.isProcessing = true;

                        const url = params.id
                            ? this.updateRouteTemplate.replace('__ID__', params.id)
                            : this.storeRoute;

                        this.$axios.post(
                            url,
                            {
                                ...params,
                                user_ids: (this.selectedUserIds || []).map(id => Number(id)).filter(id => id > 0),
                                is_show: this.isShow ? 1 : 0,
                                _method: params.id ? 'put' : 'post',
                            }
                        ).then(response => {
                            this.isProcessing = false;
                            this.$refs.optionUpdateAndCreateModal.toggle();
                            this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                            this.$refs.datagrid.get();
                            resetForm();
                            this.selectedUserIds = [];
                            this.isShow = false;
                        }).catch(error => {
                            this.isProcessing = false;

                            if (error.response?.status === 422) {
                                setErrors(error.response.data.errors);
                            }
                        });
                    },

                    editModal(url) {
                        this.$axios.get(url)
                            .then(response => {
                                this.selectedUserIds = (response.data.data.user_ids || []).map(id => Number(id));
                                this.isShow = response.data.data.is_show !== undefined ? !!response.data.data.is_show : false;
                                this.$refs.modalForm.setValues(response.data.data);
                                this.$refs.optionUpdateAndCreateModal.toggle();
                            })
                            .catch(() => {});
                    },
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>
