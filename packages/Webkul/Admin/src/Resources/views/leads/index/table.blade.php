{!! view_render_event('admin.leads.index.table.before') !!}

@php
    $leadQuickAttributes = app(\Webkul\Attribute\Repositories\AttributeRepository::class)->findWhere([
        'entity_type' => 'leads',
        'quick_add'   => 1,
    ]);

    $pipeline = app(\Webkul\Lead\Repositories\PipelineRepository::class)->getDefaultPipeline();

    $inlineOptions = [
        'lead_source_name' => [
            'field' => 'lead_source_id',
            'items' => app(\Webkul\Lead\Repositories\SourceRepository::class)->getRootDropdownOptions(),
        ],
        'lead_type_name' => [
            'field'   => 'lead_type_id',
            'items'   => app(\Webkul\Lead\Repositories\TypeRepository::class)->all(['id as value', 'name as label'])->toArray(),
        ],
        'stage' => [
            'field'   => 'lead_pipeline_stage_id',
            'items'   => $pipeline->stages->map(fn ($s) => [
                'value' => $s->id,
                'label' => $s->name,
                'code'  => $s->code,
            ])->values()->all(),
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

    $defaultMeetingParticipants = [
        'users' => auth()->guard('user')->user()
            ? [[
                'id'   => auth()->guard('user')->id(),
                'name' => auth()->guard('user')->user()->name,
            ]]
            : [],
        'persons' => [],
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
    @include('admin::components.activities.actions.activity.participants')
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
                <x-slot:toolbar-left-after>
                    <v-leads-table-sort></v-leads-table-sort>
                </x-slot>

                <x-slot:toolbar-right-after>
                    @include('admin::leads.index.view-switcher')
                </x-slot>

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

            <!-- Meeting Activity Modal -->
            <x-admin::form
                v-slot="{ meta, errors, handleSubmit }"
                as="div"
                ref="meetingModalForm"
            >
                <form @submit="handleSubmit($event, saveMeetingAndMove)">
                    <x-admin::modal
                        ref="meetingActivityModal"
                        position="center"
                        size="medium"
                    >
                        <x-slot:header>
                            <h3 class="text-base font-semibold dark:text-white">
                                Add Meeting
                            </h3>
                        </x-slot>

                        <x-slot:content>
                            <div class="grid gap-4">
                                <div class="flex gap-4 max-sm:flex-wrap">
                                    <x-admin::form.control-group class="w-full">
                                        <x-admin::form.control-group.label class="required">
                                            Schedule From
                                        </x-admin::form.control-group.label>

                                        <x-admin::form.control-group.control
                                            type="datetime"
                                            name="schedule_from"
                                            rules="required"
                                            label="Schedule From"
                                        />

                                        <x-admin::form.control-group.error control-name="schedule_from" />
                                    </x-admin::form.control-group>

                                    <x-admin::form.control-group class="w-full">
                                        <x-admin::form.control-group.label class="required">
                                            Schedule To
                                        </x-admin::form.control-group.label>

                                        <x-admin::form.control-group.control
                                            type="datetime"
                                            name="schedule_to"
                                            rules="required|after_datetime:@schedule_from"
                                            label="Schedule To"
                                        />

                                        <x-admin::form.control-group.error control-name="schedule_to" />
                                    </x-admin::form.control-group>
                                </div>

                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label class="required">
                                        Comment
                                    </x-admin::form.control-group.label>

                                    <x-admin::form.control-group.control
                                        type="textarea"
                                        name="comment"
                                        rules="required|max:500"
                                        label="Comment"
                                    />

                                    <x-admin::form.control-group.error control-name="comment" />
                                </x-admin::form.control-group>

                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label class="required">
                                        Participants
                                    </x-admin::form.control-group.label>

                                    <v-activity-participants :participants="defaultMeetingParticipants"></v-activity-participants>

                                    <p
                                        class="mt-1 text-xs text-red-600"
                                        v-if="meetingErrors.participants"
                                    >
                                        @{{ meetingErrors.participants }}
                                    </p>
                                </x-admin::form.control-group>

                                <x-admin::form.control-group class="!mb-0">
                                    <x-admin::form.control-group.label class="required">
                                        Location
                                    </x-admin::form.control-group.label>

                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="location"
                                        rules="required"
                                        label="Location"
                                    />

                                    <x-admin::form.control-group.error control-name="location" />
                                </x-admin::form.control-group>
                            </div>
                        </x-slot>

                        <x-slot:footer>
                            <x-admin::button
                                button-type="submit"
                                class="primary-button"
                                title="Save Meeting"
                                ::loading="isMeetingSaving"
                                ::disabled="isMeetingSaving"
                            />
                        </x-slot>
                    </x-admin::modal>
                </form>
            </x-admin::form>

            <!-- Follow-up Schedule Modal -->
            <x-admin::modal
                ref="followupStageModal"
                position="center"
            >
                <x-slot:header>
                    <h3 class="text-base font-semibold dark:text-white">
                        Schedule Follow-up
                    </h3>
                </x-slot>

                <x-slot:content>
                    <p class="mb-4 text-sm text-gray-600 dark:text-gray-300">
                        Choose how to set the next follow-up for this lead.
                    </p>

                    <div
                        class="mb-4"
                        v-if="followupMode === 'custom'"
                    >
                        <label class="mb-1.5 block text-sm font-medium text-gray-800 dark:text-white">
                            Next Follow-up Date <span class="text-red-500">*</span>
                        </label>

                        <input
                            type="datetime-local"
                            v-model="customFollowupDate"
                            class="w-full rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-800 outline-none focus:border-brandColor dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        >
                    </div>
                </x-slot>

                <x-slot:footer>
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <button
                            type="button"
                            class="transparent-button"
                            :disabled="isFollowupSaving"
                            @click="applyFollowupStage('auto')"
                        >
                            Use Auto
                        </button>

                        <button
                            type="button"
                            class="secondary-button"
                            :disabled="isFollowupSaving"
                            @click="followupMode === 'custom' ? applyFollowupStage('custom') : (followupMode = 'custom')"
                        >
                            @{{ followupMode === 'custom' ? 'Save Custom' : 'Custom' }}
                        </button>
                    </div>
                </x-slot>
            </x-admin::modal>
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
                    pendingStageLeadId: null,
                    pendingStageId: null,
                    isMeetingSaving: false,
                    meetingErrors: {},
                    defaultMeetingParticipants: @json($defaultMeetingParticipants),
                    followupMode: null,
                    customFollowupDate: '',
                    isFollowupSaving: false,
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
                        if (! value) return;

                        const selectedOpt = config.items.find(o => o.value == value);
                        const stageCode = selectedOpt?.code || '';

                        if (stageCode === 'follow-up') {
                            this.pendingStageLeadId = leadId;
                            this.pendingStageId = value;
                            this.followupMode = null;
                            this.customFollowupDate = '';
                            this.$refs.followupStageModal.open();

                            return;
                        }

                        if (stageCode === 'meeting') {
                            if (Number(record.meeting_activity_count) > 0) {
                                this.updateStage(leadId, value)
                                    .then(() => this.$refs.datagrid.get())
                                    .catch(error => {
                                        this.$emitter.emit('add-flash', {
                                            type: 'error',
                                            message: error.response?.data?.message || 'Update failed.',
                                        });
                                        this.$refs.datagrid.get();
                                    });

                                return;
                            }

                            this.pendingStageLeadId = leadId;
                            this.pendingStageId = value;
                            this.meetingErrors = {};
                            this.$refs.meetingActivityModal.open();

                            return;
                        }

                        this.updateStage(leadId, value)
                            .then(() => this.$refs.datagrid.get())
                            .catch(error => {
                                this.$emitter.emit('add-flash', {
                                    type: 'error',
                                    message: error.response?.data?.message || 'Update failed.',
                                });
                                this.$refs.datagrid.get();
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

                updateStage(leadId, stageId, extra = {}) {
                    return this.$axios.put(`{{ url('admin/leads/stage/edit') }}/${leadId}`, {
                        lead_pipeline_stage_id: stageId,
                        ...extra,
                    }).then(response => {
                        this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });

                        return response;
                    });
                },

                hasParticipants(participants = {}) {
                    return ['users', 'persons'].some(type => {
                        return (participants[type] || []).some(participantId => !! participantId);
                    });
                },

                saveMeetingAndMove(params, { setErrors }) {
                    this.meetingErrors = {};

                    if (! this.hasParticipants(params.participants || {})) {
                        this.meetingErrors = {
                            participants: 'Please select at least one participant.',
                        };

                        return;
                    }

                    this.isMeetingSaving = true;

                    this.$axios.post("{{ route('admin.activities.store') }}", {
                        ...params,
                        type: 'meeting',
                        activity_status: 'scheduled',
                        stage_meeting: 1,
                        lead_id: this.pendingStageLeadId,
                    }).then(() => {
                        return this.updateStage(this.pendingStageLeadId, this.pendingStageId);
                    }).then(() => {
                        this.isMeetingSaving = false;
                        this.$refs.meetingActivityModal.close();
                        this.pendingStageLeadId = null;
                        this.pendingStageId = null;
                        this.$refs.datagrid.get();
                    }).catch(error => {
                        this.isMeetingSaving = false;

                        if (error.response?.status === 422) {
                            setErrors(error.response.data.errors || {});
                            this.meetingErrors = {
                                participants: error.response.data.errors?.participants?.[0],
                            };

                            return;
                        }

                        this.$emitter.emit('add-flash', {
                            type: 'error',
                            message: error.response?.data?.message || 'Meeting could not be saved.',
                        });
                    });
                },

                applyFollowupStage(mode) {
                    if (mode === 'custom') {
                        if (! this.customFollowupDate) {
                            this.$emitter.emit('add-flash', {
                                type: 'error',
                                message: 'Please select a next follow-up date.',
                            });

                            return;
                        }
                    }

                    this.isFollowupSaving = true;

                    const payload = {
                        followup_mode: mode,
                    };

                    if (mode === 'custom') {
                        // datetime-local -> Y-m-d H:i:s
                        payload.next_followup_date = this.customFollowupDate.replace('T', ' ') + ':00';
                    }

                    this.updateStage(this.pendingStageLeadId, this.pendingStageId, payload)
                        .then(() => {
                            this.isFollowupSaving = false;
                            this.$refs.followupStageModal.close();
                            this.pendingStageLeadId = null;
                            this.pendingStageId = null;
                            this.followupMode = null;
                            this.customFollowupDate = '';
                            this.$refs.datagrid.get();
                        })
                        .catch(error => {
                            this.isFollowupSaving = false;
                            this.$emitter.emit('add-flash', {
                                type: 'error',
                                message: error.response?.data?.message || error.response?.data?.errors?.next_followup_date?.[0] || 'Update failed.',
                            });
                            this.$refs.datagrid.get();
                        });
                },

                replaceTag(leadId, oldTagId, newTagId) {
                    return this.$axios.patch(`{{ url('admin/leads') }}/${leadId}/tags`, {
                        tag_id: newTagId,
                        old_tag_id: oldTagId || null,
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

        app.component('v-leads-table-sort', {
            template: '#v-leads-table-sort-template',

            computed: {
                datagrid() {
                    let parent = this.$parent;

                    while (parent) {
                        if (parent.applied && typeof parent.get === 'function') {
                            return parent;
                        }

                        parent = parent.$parent;
                    }

                    return null;
                },

                applied() {
                    return this.datagrid?.applied || { sort: { column: null, order: null } };
                },

                sortLabel() {
                    const column = this.applied.sort?.column;
                    const order = this.applied.sort?.order;

                    const labels = {
                        'created_at_desc': '@lang('admin::app.leads.index.kanban.toolbar.sort.newest-first')',
                        'created_at_asc': '@lang('admin::app.leads.index.kanban.toolbar.sort.oldest-first')',
                        'title_asc': '@lang('admin::app.leads.index.kanban.toolbar.sort.title-az')',
                        'title_desc': '@lang('admin::app.leads.index.kanban.toolbar.sort.title-za')',
                    };

                    return labels[`${column}_${order}`] || '@lang('admin::app.leads.index.kanban.toolbar.sort.newest-first')';
                },
            },

            methods: {
                sort(column, order) {
                    if (! this.datagrid) {
                        return;
                    }

                    this.datagrid.applied.sort = {
                        column,
                        order,
                    };

                    this.datagrid.applied.pagination.page = 1;
                    this.datagrid.get();
                },
            },
        });
    </script>

    <script
        type="text/x-template"
        id="v-leads-table-sort-template"
    >
        <x-admin::dropdown position="bottom-{{ in_array(app()->getLocale(), ['fa', 'ar']) ? 'left' : 'right' }}">
            <x-slot:toggle>
                <button
                    type="button"
                    class="flex h-[38px] cursor-pointer appearance-none items-center justify-between gap-x-2 rounded-md border bg-white px-2.5 py-1.5 text-center leading-6 text-gray-600 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400 dark:focus:border-gray-400"
                >
                    <span class="icon-sort text-2xl"></span>

                    <span class="whitespace-nowrap text-sm font-medium">
                        @{{ sortLabel }}
                    </span>

                    <span class="icon-sort-down text-2xl"></span>
                </button>
            </x-slot>

            <x-slot:menu class="!p-0">
                <div class="grid w-[220px] gap-1 p-1.5">
                    <div
                        class="flex cursor-pointer items-center gap-1.5 rounded-md px-2 py-1.5 hover:bg-gray-100 dark:hover:bg-gray-950"
                        :class="{ 'bg-gray-100 dark:bg-gray-950': applied.sort.column === 'created_at' && applied.sort.order === 'desc' }"
                        @click="sort('created_at', 'desc')"
                    >
                        <span class="icon-sort-down text-2xl"></span>

                        <div class="flex flex-col gap-0.5">
                            <p class="text-sm font-semibold leading-none text-gray-800 dark:text-white">
                                @lang('admin::app.leads.index.kanban.toolbar.sort.newest-first')
                            </p>

                            <p class="text-xs leading-none text-gray-600 dark:text-gray-300">
                                @lang('admin::app.leads.index.kanban.toolbar.sort.newest-first-desc')
                            </p>
                        </div>
                    </div>

                    <div
                        class="flex cursor-pointer items-center gap-1.5 rounded-md px-2 py-1.5 hover:bg-gray-100 dark:hover:bg-gray-950"
                        :class="{ 'bg-gray-100 dark:bg-gray-950': applied.sort.column === 'created_at' && applied.sort.order === 'asc' }"
                        @click="sort('created_at', 'asc')"
                    >
                        <span class="icon-sort-up text-2xl"></span>

                        <div class="flex flex-col gap-0.5">
                            <p class="text-sm font-semibold leading-none text-gray-800 dark:text-white">
                                @lang('admin::app.leads.index.kanban.toolbar.sort.oldest-first')
                            </p>

                            <p class="text-xs leading-none text-gray-600 dark:text-gray-300">
                                @lang('admin::app.leads.index.kanban.toolbar.sort.oldest-first-desc')
                            </p>
                        </div>
                    </div>

                    <div
                        class="flex cursor-pointer items-center gap-1.5 rounded-md px-2 py-1.5 hover:bg-gray-100 dark:hover:bg-gray-950"
                        :class="{ 'bg-gray-100 dark:bg-gray-950': applied.sort.column === 'title' && applied.sort.order === 'asc' }"
                        @click="sort('title', 'asc')"
                    >
                        <span class="icon-sort-up text-2xl"></span>

                        <div class="flex flex-col gap-0.5">
                            <p class="text-sm font-semibold leading-none text-gray-800 dark:text-white">
                                @lang('admin::app.leads.index.kanban.toolbar.sort.title-az')
                            </p>

                            <p class="text-xs leading-none text-gray-600 dark:text-gray-300">
                                @lang('admin::app.leads.index.kanban.toolbar.sort.title-az-desc')
                            </p>
                        </div>
                    </div>

                    <div
                        class="flex cursor-pointer items-center gap-1.5 rounded-md px-2 py-1.5 hover:bg-gray-100 dark:hover:bg-gray-950"
                        :class="{ 'bg-gray-100 dark:bg-gray-950': applied.sort.column === 'title' && applied.sort.order === 'desc' }"
                        @click="sort('title', 'desc')"
                    >
                        <span class="icon-sort-down text-2xl"></span>

                        <div class="flex flex-col gap-0.5">
                            <p class="text-sm font-semibold leading-none text-gray-800 dark:text-white">
                                @lang('admin::app.leads.index.kanban.toolbar.sort.title-za')
                            </p>

                            <p class="text-xs leading-none text-gray-600 dark:text-gray-300">
                                @lang('admin::app.leads.index.kanban.toolbar.sort.title-za-desc')
                            </p>
                        </div>
                    </div>
                </div>
            </x-slot>
        </x-admin::dropdown>
    </script>
@endPushOnce
