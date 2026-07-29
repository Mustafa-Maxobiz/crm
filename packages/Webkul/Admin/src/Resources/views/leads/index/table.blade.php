{!! view_render_event('admin.leads.index.table.before') !!}

@php
    $leadQuickAttributes = app(\Webkul\Attribute\Repositories\AttributeRepository::class)->findWhere([
        'entity_type' => 'leads',
        'quick_add'   => 1,
    ]);

    $pipeline = app(\Webkul\Lead\Repositories\PipelineRepository::class)->getDefaultPipeline();

    $isAdmin = auth()->guard('user')->user()?->role?->permission_type === 'all';

    $inlineOptions = [];

    if ($isAdmin) {
        $inlineOptions['lead_source_name'] = [
            'field'   => 'lead_source_id',
            'items'   => app(\Webkul\Lead\Repositories\SourceRepository::class)->all(['id as value', 'name as label'])->toArray(),
        ];
    }

    $inlineOptions += [
        'lead_type_name' => [
            'field'   => 'lead_type_id',
            'items'   => app(\Webkul\Lead\Repositories\TypeRepository::class)->all(['id as value', 'name as label'])->toArray(),
        ],
        'stage' => [
            'field'   => 'lead_pipeline_stage_id',
            'items'   => $pipeline->stages->map(fn ($s) => ['value' => $s->id, 'label' => $s->name])->values()->all(),
        ],
        'tag_name' => [
            'field'   => 'tag_id',
            'items'   => app(\Webkul\Tag\Repositories\TagRepository::class)->all(['id as value', 'name as label'])->toArray(),
        ],
        'industry' => [
            'field'   => 'industry_option_id',
            'items'   => \Illuminate\Support\Facades\DB::table('attribute_options')
                ->where('attribute_id', \Illuminate\Support\Facades\DB::table('attributes')->where('code', 'industry')->where('entity_type', 'leads')->value('id'))
                ->orderBy('sort_order')
                ->get(['id as value', 'name as label'])
                ->map(fn ($o) => ['value' => $o->value, 'label' => $o->label])
                ->all(),
        ],
        'service_offered' => [
            'field'   => 'service_option_id',
            'items'   => \Illuminate\Support\Facades\DB::table('attribute_options')
                ->where('attribute_id', \Illuminate\Support\Facades\DB::table('attributes')->where('code', 'service_offered')->where('entity_type', 'leads')->value('id'))
                ->orderBy('sort_order')
                ->get(['id as value', 'name as label'])
                ->map(fn ($o) => ['value' => $o->value, 'label' => $o->label])
                ->all(),
        ],
    ];
@endphp

<v-leads-table>
    <x-admin::shimmer.datagrid />
</v-leads-table>

{!! view_render_event('admin.leads.index.table.after') !!}

{{-- Include contact partial: hidden wrapper suppresses the bare <v-contact-component>,
     but @pushOnce still registers its Vue template & script. --}}
<div class="hidden">
    @include('admin::leads.common.contact')
</div>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-leads-table-template"
    >
        <div>
            <x-admin::datagrid
                src="{{ route('admin.leads.index') }}"
                ref="datagrid"
            >
                <template #toolbar-right-after>
                    @include('admin::leads.index.view-switcher')
                </template>

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
                                        v-model="applied.massActions.indices"
                                    >

                                    <span class="icon-checkbox-outline peer-checked:icon-checkbox-select cursor-pointer rounded-md text-2xl text-gray-500 peer-checked:text-brandColor">
                                    </span>
                                </label>
                            </p>

                            <template v-for="column in available.columns">
                                <template v-if="column.visibility">
                                    {{-- Inline-editable dropdown columns --}}
                                    <div
                                        v-if="inlineOptions[column.index]"
                                        class="min-w-0"
                                    >
                                        <select
                                            class="w-full cursor-pointer truncate rounded border border-transparent bg-transparent px-1 py-0.5 text-sm text-gray-800 outline-none transition-all hover:border-gray-300 focus:border-brandColor focus:ring-1 focus:ring-brandColor dark:text-gray-300 dark:hover:border-gray-600 dark:focus:border-brandColor"
                                            :value="record[inlineOptions[column.index].field]"
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
                                v-if="available.actions.length"
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
                            v-for="record in available.records"
                        >
                            <div class="mb-2 flex items-center justify-end gap-1">
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

            <!-- Edit Lead Modal -->
            <x-admin::form
                v-slot="{ meta, errors, handleSubmit }"
                as="div"
                ref="editModalForm"
            >
                <form @submit="handleSubmit($event, saveLead)">
                    <x-admin::modal
                        ref="editLeadModal"
                        position="center"
                        size="large"
                    >
                        <x-slot:header>
                            <h3 class="text-base font-semibold dark:text-white">
                                @lang('admin::app.leads.index.modals.edit-title')
                            </h3>
                        </x-slot>

                        <x-slot:content>
                            <div
                                class="py-8 text-center text-gray-500"
                                v-if="isEditLoading"
                            >
                                @lang('admin::app.leads.index.modals.loading')
                            </div>

                            <div
                                class="flex max-h-[60vh] flex-col gap-4 overflow-y-auto pb-4"
                                v-show="! isEditLoading"
                            >
                                <x-admin::form.control-group.control
                                    type="hidden"
                                    name="entity_type"
                                    value="leads"
                                />

                                <x-admin::form.control-group.control
                                    type="hidden"
                                    name="quick_add"
                                    value="1"
                                />

                                <x-admin::form.control-group.control
                                    type="hidden"
                                    name="lead_pipeline_id"
                                    ::value="editLead.lead_pipeline_id"
                                />

                                <x-admin::attributes
                                    :custom-attributes="$leadQuickAttributes"
                                    :custom-validations="[
                                        'expected_close_date' => [
                                            'date_format:yyyy-MM-dd',
                                            'after:' . \Carbon\Carbon::yesterday()->format('Y-m-d'),
                                        ],
                                    ]"
                                />

                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label>
                                        @lang('admin::app.leads.index.datagrid.stage')
                                    </x-admin::form.control-group.label>

                                    <x-admin::form.control-group.control
                                        type="select"
                                        name="lead_pipeline_stage_id"
                                        ::value="editLead.lead_pipeline_stage_id"
                                        :label="trans('admin::app.leads.index.datagrid.stage')"
                                    >
                                        <option value="">
                                            @lang('admin::app.leads.index.datagrid.stage')
                                        </option>
                                        <option
                                            v-for="stage in editStages"
                                            :key="stage.id"
                                            :value="stage.id"
                                        >
                                            @{{ stage.name }}
                                        </option>
                                    </x-admin::form.control-group.control>
                                </x-admin::form.control-group>

                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label>
                                        @lang('admin::app.components.tags.index.title')
                                    </x-admin::form.control-group.label>

                                    <x-admin::form.control-group.control
                                        type="tags"
                                        name="tags"
                                        label="Tags"
                                        :placeholder="trans('admin::app.components.tags.index.placeholder')"
                                        ::data="editTags"
                                        input-rules="max:100"
                                        :allow-duplicates="false"
                                        suggestions-endpoint="{{ route('admin.settings.tags.search') }}"
                                    />
                                </x-admin::form.control-group>

                                <div class="flex flex-col gap-1 border-t border-gray-200 pt-4 dark:border-gray-800">
                                    <p class="text-base font-semibold dark:text-white">
                                        @lang('admin::app.leads.edit.contact-person')
                                    </p>
                                </div>

                                <v-contact-component
                                    v-if="editLeadId"
                                    :key="'contact-' + editLeadId"
                                    :data="editPerson"
                                ></v-contact-component>
                            </div>
                        </x-slot>

                        <x-slot:footer>
                            <x-admin::button
                                button-type="submit"
                                class="primary-button"
                                :title="trans('admin::app.leads.index.modals.edit-save-btn')"
                                ::loading="isEditSaving"
                                ::disabled="isEditSaving || isEditLoading"
                            />
                        </x-slot>
                    </x-admin::modal>
                </form>
            </x-admin::form>

            <!-- Add Note Modal -->
            <x-admin::form
                v-slot="{ meta, errors, handleSubmit }"
                as="div"
                ref="noteModalForm"
            >
                <form @submit="handleSubmit($event, saveNote)">
                    <x-admin::modal
                        ref="noteLeadModal"
                        position="center"
                    >
                        <x-slot:header>
                            <h3 class="text-base font-semibold dark:text-white">
                                @lang('admin::app.leads.index.modals.note-title')
                            </h3>
                        </x-slot>

                        <x-slot:content>
                            <x-admin::form.control-group.control
                                type="hidden"
                                name="type"
                                value="note"
                            />

                            <x-admin::form.control-group.control
                                type="hidden"
                                name="lead_id"
                                ::value="noteLeadId"
                            />

                            <x-admin::form.control-group class="!mb-0">
                                <x-admin::form.control-group.label class="required">
                                    @lang('admin::app.leads.index.modals.note-comment')
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.control
                                    type="textarea"
                                    name="comment"
                                    rules="required"
                                    :label="trans('admin::app.leads.index.modals.note-comment')"
                                />

                                <x-admin::form.control-group.error control-name="comment" />
                            </x-admin::form.control-group>
                        </x-slot>

                        <x-slot:footer>
                            <x-admin::button
                                button-type="submit"
                                class="primary-button"
                                :title="trans('admin::app.leads.index.modals.note-save-btn')"
                                ::loading="isNoteSaving"
                                ::disabled="isNoteSaving"
                            />
                        </x-slot>
                    </x-admin::modal>
                </form>
            </x-admin::form>

            <!-- Incorrect Info Comment Modal -->
            <x-admin::form
                v-slot="{ meta, errors, handleSubmit }"
                as="div"
                ref="incorrectInfoForm"
            >
                <form @submit="handleSubmit($event, saveIncorrectInfo)">
                    <x-admin::modal
                        ref="incorrectInfoModal"
                        position="center"
                    >
                        <x-slot:header>
                            <h3 class="text-base font-semibold dark:text-white">
                                Incorrect Info — Add Comment
                            </h3>
                        </x-slot>

                        <x-slot:content>
                            <x-admin::form.control-group class="!mb-0">
                                <x-admin::form.control-group.label class="required">
                                    Please describe what information is incorrect
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.control
                                    type="textarea"
                                    name="incorrect_info_comment"
                                    rules="required"
                                    label="Comment"
                                    placeholder="e.g. Wrong phone number, incorrect company name..."
                                />

                                <x-admin::form.control-group.error control-name="incorrect_info_comment" />
                            </x-admin::form.control-group>
                        </x-slot>

                        <x-slot:footer>
                            <x-admin::button
                                button-type="submit"
                                class="primary-button"
                                title="Save & Apply Tag"
                                ::loading="isIncorrectInfoSaving"
                                ::disabled="isIncorrectInfoSaving"
                            />
                        </x-slot>
                    </x-admin::modal>
                </form>
            </x-admin::form>
        </div>
    </script>

    <script type="module">
        app.component('v-leads-table', {
            template: '#v-leads-table-template',

            data() {
                return {
                    src: "{{ route('admin.leads.index') }}",
                    inlineOptions: @json($inlineOptions),
                    editLeadId: null,
                    editLead: {},
                    editPerson: { name: '' },
                    editTags: [],
                    editStages: [],
                    isEditLoading: false,
                    isEditSaving: false,
                    noteLeadId: null,
                    isNoteSaving: false,
                    incorrectInfoLeadId: null,
                    incorrectInfoTagId: null,
                    incorrectInfoOldTagId: null,
                    isIncorrectInfoSaving: false,
                };
            },

            methods: {
                inlineUpdate(record, columnIndex, newValue) {
                    const config = this.inlineOptions[columnIndex];
                    if (! config) return;

                    const leadId = record.id;
                    const field = config.field;
                    const value = newValue ? parseInt(newValue) : null;

                    const fieldMap = {
                        lead_source_id: 'lead_source_id',
                        lead_type_id: 'lead_type_id',
                        lead_pipeline_stage_id: 'lead_pipeline_stage_id',
                        tag_id: 'tag_id',
                        industry_option_id: 'industry',
                        service_option_id: 'service_offered',
                    };

                    if (field === 'tag_id') {
                        if (! value) return;

                        const selectedOpt = config.items.find(o => o.value == value);
                        const tagName = (selectedOpt?.label || '').trim().toLowerCase();

                        if (tagName === 'incorrect info') {
                            this.incorrectInfoLeadId = leadId;
                            this.incorrectInfoTagId = value;
                            this.incorrectInfoOldTagId = record.tag_id;
                            this.$refs.incorrectInfoModal.open();

                            return;
                        }

                        if (tagName === 'do not call') {
                            this.attachTagAndDisqualify(leadId, record.tag_id, value, 'do_not_call');

                            return;
                        }

                        this.replaceTag(leadId, record.tag_id, value).then(() => {
                            this.$refs.datagrid.get();
                        });

                        return;
                    }

                    if (field === 'lead_pipeline_stage_id') {
                        this.$axios.put(`{{ url('admin/leads/stage/edit') }}/${leadId}`, {
                            lead_pipeline_stage_id: value,
                        }).then(response => {
                            this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                            this.$refs.datagrid.get();
                        }).catch(error => {
                            this.$emitter.emit('add-flash', { type: 'error', message: error.response?.data?.message || 'Update failed.' });
                        });

                        return;
                    }

                    const payload = {
                        entity_type: 'leads',
                    };

                    if (field === 'industry_option_id' || field === 'service_option_id') {
                        const attrCode = field === 'industry_option_id' ? 'industry' : 'service_offered';
                        payload[attrCode] = value;
                    } else {
                        payload[field] = value;
                    }

                    this.$axios.put(`{{ url('admin/leads/attributes/edit') }}/${leadId}`, payload)
                        .then(response => {
                            this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                            this.$refs.datagrid.get();
                        })
                        .catch(error => {
                            this.$emitter.emit('add-flash', { type: 'error', message: error.response?.data?.message || 'Update failed.' });
                        });
                },

                gridRowStyle(available) {
                    const dataColumnMin = 160;
                    const tracks = [];
                    const visibleColumns = available.columns.filter(column => column.visibility).length;

                    if (available.massActions.length) {
                        tracks.push('40px');
                    }

                    for (let i = 0; i < visibleColumns; i++) {
                        tracks.push(`minmax(${dataColumnMin}px, 1fr)`);
                    }

                    const actionsWidth = available.actions.length > 2 ? 160 : 72;

                    if (available.actions.length) {
                        tracks.push(`${actionsWidth}px`);
                    }

                    const minWidth =
                        (available.massActions.length ? 40 : 0)
                        + (visibleColumns * dataColumnMin)
                        + (available.actions.length ? actionsWidth : 0)
                        + ((tracks.length - 1) * 10);

                    return {
                        gridTemplateColumns: tracks.join(' '),
                        minWidth: `${minWidth}px`,
                    };
                },

                handleAction(action, record, performAction) {
                    if (action.index === 'edit') {
                        this.openEditModal(record, action.url);

                        return;
                    }

                    if (action.index === 'note') {
                        this.openNoteModal(record);

                        return;
                    }

                    performAction(action);
                },

                openEditModal(record, url) {
                    this.editLeadId = record.id;
                    this.isEditLoading = true;
                    this.editLead = { id: record.id };
                    this.editPerson = { name: '' };
                    this.editTags = [];
                    this.editStages = [];

                    this.$refs.editLeadModal.open();

                    this.$axios.get(url)
                        .then(response => {
                            const data = response.data.data || {};

                            this.editLead = data;
                            this.editPerson = data.person || { name: '' };
                            this.editTags = data.tags || [];
                            this.editStages = data.stages || [];
                            this.isEditLoading = false;

                            this.$nextTick(() => {
                                this.$refs.editModalForm.setValues(data);
                            });
                        })
                        .catch(error => {
                            this.isEditLoading = false;
                            this.$refs.editLeadModal.close();
                            this.$emitter.emit('add-flash', {
                                type: 'error',
                                message: error.response?.data?.message || 'Unable to load lead.',
                            });
                        });
                },

                saveLead(params, { setErrors }) {
                    this.isEditSaving = true;

                    this.$axios.post(`{{ url('admin/leads/edit') }}/${this.editLeadId}`, {
                        ...params,
                        entity_type: 'leads',
                        quick_add: 1,
                        _method: 'put',
                    }, {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    }).then(response => {
                        this.isEditSaving = false;
                        this.$refs.editLeadModal.close();
                        this.$emitter.emit('add-flash', {
                            type: 'success',
                            message: response.data.message,
                        });
                        this.$refs.datagrid.get();
                    }).catch(error => {
                        this.isEditSaving = false;

                        if (error.response?.status === 422) {
                            setErrors(error.response.data.errors || {});

                            return;
                        }

                        this.$emitter.emit('add-flash', {
                            type: 'error',
                            message: error.response?.data?.message || 'Unable to update lead.',
                        });
                    });
                },

                openNoteModal(record) {
                    this.noteLeadId = record.id;
                    this.$refs.noteLeadModal.open();
                },

                saveNote(params, { resetForm, setErrors }) {
                    this.isNoteSaving = true;

                    this.$axios.post("{{ route('admin.activities.store') }}", {
                        ...params,
                        type: 'note',
                        lead_id: this.noteLeadId,
                    }).then(response => {
                        this.isNoteSaving = false;
                        this.$refs.noteLeadModal.close();
                        resetForm();
                        this.$emitter.emit('add-flash', {
                            type: 'success',
                            message: response.data.message,
                        });
                    }).catch(error => {
                        this.isNoteSaving = false;

                        if (error.response?.status === 422) {
                            setErrors(error.response.data.errors || {});

                            return;
                        }

                        this.$emitter.emit('add-flash', {
                            type: 'error',
                            message: error.response?.data?.message || 'Unable to save note.',
                        });
                    });
                },

                replaceTag(leadId, oldTagId, newTagId) {
                    let chain = Promise.resolve();

                    if (oldTagId) {
                        chain = this.$axios.delete(`{{ url('admin/leads') }}/${leadId}/tags`, {
                            data: { tag_id: oldTagId },
                        });
                    }

                    return chain.then(() => {
                        return this.$axios.post(`{{ url('admin/leads') }}/${leadId}/tags`, {
                            tag_id: newTagId,
                        });
                    }).catch(error => {
                        this.$emitter.emit('add-flash', { type: 'error', message: error.response?.data?.message || 'Tag update failed.' });
                        throw error;
                    });
                },

                attachTagAndDisqualify(leadId, oldTagId, newTagId, reason) {
                    this.replaceTag(leadId, oldTagId, newTagId).then(() => {
                        return this.$axios.put(`{{ url('admin/leads/attributes/edit') }}/${leadId}`, {
                            entity_type: 'leads',
                            lead_disqualification_reason: reason,
                        });
                    }).then(response => {
                        this.$emitter.emit('add-flash', { type: 'success', message: 'Tag applied and lead disqualified.' });
                        this.$refs.datagrid.get();
                    }).catch(error => {
                        this.$emitter.emit('add-flash', { type: 'error', message: error.response?.data?.message || 'Update failed.' });
                    });
                },

                saveIncorrectInfo(params, { resetForm, setErrors }) {
                    this.isIncorrectInfoSaving = true;

                    const leadId = this.incorrectInfoLeadId;
                    const tagId = this.incorrectInfoTagId;
                    const oldTagId = this.incorrectInfoOldTagId;

                    this.replaceTag(leadId, oldTagId, tagId).then(() => {
                        return this.$axios.post("{{ route('admin.activities.store') }}", {
                            type: 'note',
                            comment: params.incorrect_info_comment,
                            lead_id: leadId,
                        });
                    }).then(() => {
                        return this.$axios.put(`{{ url('admin/leads/attributes/edit') }}/${leadId}`, {
                            entity_type: 'leads',
                            lead_disqualification_reason: 'incorrect_info',
                        });
                    }).then(response => {
                        this.isIncorrectInfoSaving = false;
                        this.$refs.incorrectInfoModal.close();
                        resetForm();
                        this.$emitter.emit('add-flash', {
                            type: 'success',
                            message: 'Tag applied, comment saved, and lead disqualified.',
                        });
                        this.$refs.datagrid.get();
                    }).catch(error => {
                        this.isIncorrectInfoSaving = false;

                        if (error.response?.status === 422) {
                            setErrors(error.response.data.errors || {});
                            return;
                        }

                        this.$emitter.emit('add-flash', {
                            type: 'error',
                            message: error.response?.data?.message || 'Unable to save.',
                        });
                    });
                },
            },
        });
    </script>
@endPushOnce
