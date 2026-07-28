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
                        performAction
                    }">
                        <template v-if="isLoading">
                            <x-admin::shimmer.datagrid.table.body />
                        </template>

                        <template v-else>
                            <div
                                v-for="record in available.records"
                                class="row grid items-center gap-2.5 border-b px-4 py-4 text-gray-600 transition-all hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-gray-950 max-lg:hidden"
                                :style="`grid-template-columns: repeat(${gridsCount}, minmax(0, 1fr))`"
                            >
                                <p>@{{ record.id }}</p>
                                <p>@{{ record.name }}</p>
                                <p>@{{ record.sort_order }}</p>

                                <div class="flex justify-end">
                                    <a
                                        v-if="record.actions.find(action => action.index === 'edit')"
                                        @click="selectedOption=true; editModal(record.actions.find(action => action.index === 'edit')?.url)"
                                    >
                                        <span
                                            :class="record.actions.find(action => action.index === 'edit')?.icon"
                                            class="cursor-pointer rounded-md p-1.5 text-2xl transition-all hover:bg-gray-200 dark:hover:bg-gray-800 max-sm:place-self-center"
                                        ></span>
                                    </a>

                                    <a
                                        v-if="record.actions.find(action => action.index === 'delete')"
                                        @click="performAction(record.actions.find(action => action.index === 'delete'))"
                                    >
                                        <span
                                            :class="record.actions.find(action => action.index === 'delete')?.icon"
                                            class="cursor-pointer rounded-md p-1.5 text-2xl transition-all hover:bg-gray-200 dark:hover:bg-gray-800 max-sm:place-self-center"
                                        ></span>
                                    </a>
                                </div>
                            </div>

                            <div
                                class="hidden border-b px-4 py-4 text-black dark:border-gray-800 dark:text-gray-300 max-lg:block"
                                v-for="record in available.records"
                            >
                                <div class="mb-2 flex items-center justify-end gap-2">
                                    <a
                                        v-if="record.actions.find(action => action.index === 'edit')"
                                        @click="selectedOption=true; editModal(record.actions.find(action => action.index === 'edit')?.url)"
                                    >
                                        <span
                                            :class="record.actions.find(action => action.index === 'edit')?.icon"
                                            class="cursor-pointer rounded-md p-1.5 text-2xl transition-all hover:bg-gray-200 dark:hover:bg-gray-800"
                                        ></span>
                                    </a>

                                    <a
                                        v-if="record.actions.find(action => action.index === 'delete')"
                                        @click="performAction(record.actions.find(action => action.index === 'delete'))"
                                    >
                                        <span
                                            :class="record.actions.find(action => action.index === 'delete')?.icon"
                                            class="cursor-pointer rounded-md p-1.5 text-2xl transition-all hover:bg-gray-200 dark:hover:bg-gray-800"
                                        ></span>
                                    </a>
                                </div>

                                <div class="grid gap-2">
                                    <template v-for="column in available.columns">
                                        <div class="flex flex-wrap items-baseline gap-x-2">
                                            <span class="text-slate-600 dark:text-gray-300" v-html="column.label + ':'"></span>
                                            <span class="break-words font-medium text-slate-900 dark:text-white" v-html="record[column.index]"></span>
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
                },

                data() {
                    return {
                        isProcessing: false,
                        selectedOption: false,
                    };
                },

                computed: {
                    gridsCount() {
                        let count = this.$refs.datagrid.available.columns.length;

                        if (this.$refs.datagrid.available.actions.length) {
                            ++count;
                        }

                        if (this.$refs.datagrid.available.massActions.length) {
                            ++count;
                        }

                        return count;
                    },
                },

                methods: {
                    openModal() {
                        this.selectedOption = false;
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
                                _method: params.id ? 'put' : 'post',
                            }
                        ).then(response => {
                            this.isProcessing = false;
                            this.$refs.optionUpdateAndCreateModal.toggle();
                            this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                            this.$refs.datagrid.get();
                            resetForm();
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
