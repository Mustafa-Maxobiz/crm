<x-admin::layouts>
    <!-- Page Title -->
    <x-slot:title>
        @lang('admin::app.settings.tags.index.title')
    </x-slot>

    <div class="flex flex-col gap-4">
        <!-- Header Section -->
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-2">
                {!! view_render_event('admin.settings.tags.index.breadcrumbs.before') !!}

                <!-- Breadcrumbs -->
                <x-admin::breadcrumbs name="settings.tags" />

                {!! view_render_event('admin.settings.tags.index.breadcrumbs.after') !!}

                <div class="text-xl font-bold dark:text-white">
                    @lang('admin::app.settings.tags.index.title')
                </div>
            </div>

            <div class="flex items-center gap-x-2.5">
                {!! view_render_event('admin.settings.tags.index.create_button.before') !!}
                
                <!-- Create button for Tags -->
                @if (! empty($canManageTags))
                    <div class="flex items-center gap-x-2.5">
                        <button
                            type="button"
                            class="primary-button"
                            @click="$refs.tagSettings.openModal()"
                        >
                            @lang('admin::app.settings.tags.index.create-btn')
                        </button>
                    </div>
                @endif

                {!! view_render_event('admin.settings.tags.index.create_button.after') !!}
            </div>
        </div>

        <p class="text-sm text-gray-600 dark:text-gray-300">
            @lang('admin::app.settings.tags.index.static-info')
        </p>
        
        <v-tag-settings ref="tagSettings">
            <!-- DataGrid Shimmer -->
            <x-admin::shimmer.datagrid />
        </v-tag-settings>
    </div>

    @pushOnce('scripts')
        <script
            type="text/x-template"
            id="tag-settings-template"
        >
            {!! view_render_event('admin.settings.tags.index.datagrid.before') !!}
        
            <!-- Datagrid -->
            <x-admin::datagrid
                :src="route('admin.settings.tags.index')"
                ref="datagrid"
            >
                <template #body="{
                    isLoading,
                    available,
                    applied,
                    performAction
                }">
                    <template v-if="isLoading">
                        <x-admin::shimmer.datagrid.table.body />
                    </template>

                    <template v-else-if="available.records.length">
                        <div
                            v-for="record in available.records"
                            :key="record[available.meta.primary_column]"
                            class="row grid items-center gap-2.5 border-b px-4 py-4 text-gray-600 transition-all hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-gray-950 max-lg:hidden"
                            :style="gridRowStyle"
                        >
                            <p v-if="available.massActions.length">
                                <label :for="`mass_action_select_record_${record[available.meta.primary_column]}`">
                                    <input
                                        type="checkbox"
                                        :name="`mass_action_select_record_${record[available.meta.primary_column]}`"
                                        :id="`mass_action_select_record_${record[available.meta.primary_column]}`"
                                        :value="record[available.meta.primary_column]"
                                        class="peer hidden"
                                        v-model="applied.massActions.indices"
                                    >

                                    <span class="icon-checkbox-outline peer-checked:icon-checkbox-select cursor-pointer rounded-md text-2xl text-gray-600 peer-checked:text-brandColor dark:text-gray-300"></span>
                                </label>
                            </p>

                            <template v-for="column in available.columns">
                                <p
                                    v-if="column.visibility"
                                    class="min-w-0 break-words"
                                    v-html="record[column.index] || '--'"
                                ></p>
                            </template>

                            <p
                                v-if="available.actions.length"
                                class="flex justify-end gap-1"
                            >
                                <span
                                    v-for="action in record.actions"
                                    :key="action.index"
                                    :class="action.icon"
                                    class="cursor-pointer rounded-md p-1.5 text-2xl transition-all hover:bg-gray-200 dark:hover:bg-gray-800 max-sm:place-self-center"
                                    @click="handleTagAction(action, performAction)"
                                ></span>
                            </p>
                        </div>

                        <div
                            v-for="record in available.records"
                            :key="`mobile_${record[available.meta.primary_column]}`"
                            class="hidden border-b px-4 py-4 text-black dark:border-gray-800 dark:text-gray-300 max-lg:block"
                        >
                            <div class="mb-2 flex items-center justify-between">
                                <label
                                    v-if="available.massActions.length"
                                    :for="`mobile_mass_action_select_record_${record[available.meta.primary_column]}`"
                                >
                                    <input
                                        type="checkbox"
                                        :name="`mass_action_select_record_${record[available.meta.primary_column]}`"
                                        :id="`mobile_mass_action_select_record_${record[available.meta.primary_column]}`"
                                        :value="record[available.meta.primary_column]"
                                        class="peer hidden"
                                        v-model="applied.massActions.indices"
                                    >

                                    <span class="icon-checkbox-outline peer-checked:icon-checkbox-select cursor-pointer rounded-md text-2xl text-gray-500 peer-checked:text-brandColor"></span>
                                </label>

                                <div
                                    v-if="available.actions.length"
                                    class="flex items-center justify-end gap-1"
                                >
                                    <span
                                        v-for="action in record.actions"
                                        :key="action.index"
                                        :class="action.icon"
                                        class="cursor-pointer rounded-md p-1.5 text-2xl transition-all hover:bg-gray-200 dark:hover:bg-gray-800"
                                        @click="handleTagAction(action, performAction)"
                                    ></span>
                                </div>
                            </div>

                            <div class="grid gap-2">
                                <template v-for="column in available.columns">
                                    <div
                                        v-if="column.visibility"
                                        class="flex flex-wrap items-baseline gap-x-2"
                                    >
                                        <span class="text-slate-600 dark:text-gray-300" v-html="column.label + ':'"></span>
                                        <span class="break-words font-medium text-slate-900 dark:text-white" v-html="record[column.index] || '--'"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    <template v-else>
                        <div class="row grid border-b px-4 py-4 text-center text-gray-600 dark:border-gray-800 dark:text-gray-300">
                            <p>
                                @lang('admin::app.components.datagrid.table.no-records-available')
                            </p>
                        </div>
                    </template>
                </template>
            </x-admin::datagrid>
            
            {!! view_render_event('admin.settings.tags.index.datagrid.after') !!}
            
            <x-admin::form
                v-slot="{ meta, errors, handleSubmit }"
                as="div"
                ref="modalForm"
            >
                <form @submit="handleSubmit($event, updateOrCreate)">
                    {!! view_render_event('admin.settings.tags.index.form_controls.before') !!}

                    <x-admin::modal ref="tagsUpdateAndCreateModal">
                        <!-- Modal Header -->
                        <x-slot:header>
                            {!! view_render_event('admin.settings.tags.index.form_controls.modal.title.before') !!}

                            <p class="text-lg font-bold text-gray-800 dark:text-white">
                                @{{ 
                                    selectedTag
                                    ? "@lang('admin::app.settings.tags.index.edit.title')" 
                                    : "@lang('admin::app.settings.tags.index.create.title')"
                                }}
                            </p>

                            {!! view_render_event('admin.settings.tags.index.form_controls.modal.title.after') !!}
                        </x-slot>

                        <!-- Modal Content -->
                        <x-slot:content>
                            {!! view_render_event('admin.settings.tags.index.content.before') !!}

                            <x-admin::form.control-group.control
                                type="hidden"
                                name="id"
                            />

                            {!! view_render_event('admin.settings.tags.index.form_controls.before') !!}

                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label class="required">
                                    @lang('admin::app.settings.tags.index.create.name')
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.control
                                    type="text"
                                    id="name"
                                    name="name"
                                    rules="required|max:50"
                                    :label="trans('admin::app.settings.tags.index.create.name')"
                                    :placeholder="trans('admin::app.settings.tags.index.create.name')"
                                />

                                <x-admin::form.control-group.error control-name="name" />
                            </x-admin::form.control-group>

                            <x-admin::form.control-group.label>
                                @lang('admin::app.settings.tags.index.create.color')
                            </x-admin::form.control-group.label>
                            
                            <div class="flex gap-3">
                                <template v-for="(color, index) in colors">
                                    <span class="relative inline-block">
                                        <x-admin::form.control-group.control
                                            type="radio" 
                                            ::id="index" 
                                            name="color" 
                                            ::value="color.background" 
                                            class="peer absolute left-0 right-3 top-5 z-10 h-full w-full cursor-pointer opacity-0"
                                        />
    
                                        <label 
                                            :for="index" 
                                            class="block h-6 w-6 cursor-pointer rounded-full shadow-md transition duration-200 ease-in-out peer-checked:border-2 peer-checked:border-solid peer-checked:border-brandColor"
                                            :style="`background-color: ${color.background}`"
                                        >
                                        </label>
                                    </span>
                                </template>
                            </div>

                            {!! view_render_event('admin.settings.tags.index.content.after') !!}
                        </x-slot>

                        <!-- Modal Footer -->
                        <x-slot:footer>
                            {!! view_render_event('admin.settings.tags.index.form_controls.modal.footer.save_button.before') !!}

                            <!-- Save Button -->
                            <x-admin::button
                                button-type="submit"
                                class="primary-button justify-center"
                                :title="trans('admin::app.settings.tags.index.create.save-btn')"
                                ::loading="isProcessing"
                                ::disabled="isProcessing"
                            />

                            {!! view_render_event('admin.settings.tags.index.form_controls.modal.footer.save_button.after') !!}
                        </x-slot>
                    </x-admin::modal>

                    {!! view_render_event('admin.settings.tags.index.form_controls.after') !!}
                </form>
            </x-admin::form>
        </script>

        <script type="module">
            app.component('v-tag-settings', {
                template: '#tag-settings-template',
        
                data() {
                    return {
                        isProcessing: false,
                        
                        selectedTag: false,

                        colors: [
                            {
                                background: '#FEE2E2',
                            }, {
                                background: '#FFEDD5',
                            }, {
                                background: '#FEF3C7',
                            }, {
                                background: '#FEF9C3',
                            }, {
                                background: '#ECFCCB',
                            }, {
                                background: '#DCFCE7',
                            },
                        ],
                    };
                },
        
                computed: {
                    gridRowStyle() {
                        const dataColumnMin = 160;
                        const available = this.$refs.datagrid.available;
                        const tracks = [];

                        if (available.massActions.length) {
                            tracks.push('40px');
                        }

                        const visibleColumns = available.columns.filter((column) => column.visibility).length;

                        for (let i = 0; i < visibleColumns; i++) {
                            tracks.push(`minmax(${dataColumnMin}px, 1fr)`);
                        }

                        if (available.actions.length) {
                            tracks.push(available.actions.length > 2 ? '160px' : '72px');
                        }

                        const actionsWidth = available.actions.length
                            ? (available.actions.length > 2 ? 160 : 72)
                            : 0;

                        const minWidth =
                            (available.massActions.length ? 40 : 0)
                            + (visibleColumns * dataColumnMin)
                            + actionsWidth
                            + ((tracks.length - 1) * 10);

                        return {
                            gridTemplateColumns: tracks.join(' '),
                            minWidth: `${minWidth}px`,
                        };
                    },
                },

                methods: {
                    openModal() {
                        this.selectedTag=false;
                        
                        this.$refs.tagsUpdateAndCreateModal.toggle();
                    },
                    
                    updateOrCreate(params, {resetForm, setErrors}) {
                        this.isProcessing = true;

                        this.$axios.post(params.id ? `{{ route('admin.settings.tags.update', '') }}/${params.id}` : "{{ route('admin.settings.tags.store') }}", {
                            ...params,
                            _method: params.id ? 'put' : 'post'
                        },

                        ).then(response => {
                            this.isProcessing = false;

                            this.$refs.tagsUpdateAndCreateModal.toggle();

                            this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });

                            this.$refs.datagrid.get();

                            resetForm();
                        }).catch(error => {
                            this.isProcessing = false;

                            if (error.response.status === 422) {
                                setErrors(error.response.data.errors);
                            }
                        });
                    },
                    
                    editModal(url) {
                        this.$axios.get(url)
                            .then(response => {
                                this.$refs.modalForm.setValues(response.data.data);
                                
                                this.$refs.tagsUpdateAndCreateModal.toggle();
                            })
                            .catch(error => {});
                    },

                    handleTagAction(action, performAction) {
                        if (! action) {
                            return;
                        }

                        if (action.index === 'edit') {
                            this.selectedTag = true;

                            this.editModal(action.url);

                            return;
                        }

                        performAction(action);
                    },
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>
