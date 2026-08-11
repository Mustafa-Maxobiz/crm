<!-- Stages Navigation -->
@php
    $accessibleViewStages = lead_variant() === 'lge'
        ? $lead->pipeline->stages->values()
        : app(\Webkul\Lead\Services\SourceAccessService::class)
            ->filterAccessibleStages($lead->pipeline->stages);
@endphp

{!! view_render_event('admin.leads.view.stages.before', ['lead' => $lead]) !!}

<!-- Stages Vue Component -->
<v-lead-stages>
    <x-admin::shimmer.leads.view.stages :count="max($accessibleViewStages->count() - 1, 0)" />
</v-lead-stages>

{!! view_render_event('admin.leads.view.stages.after', ['lead' => $lead]) !!}

<div class="hidden">
    @include('admin::components.activities.actions.activity.participants')
</div>

@pushOnce('scripts')
    @php
        $hasMeetingActivity = $lead->activities()->where('type', 'meeting')->exists();

        $defaultMeetingParticipants = [
            'users' => lead_variant() !== 'lge' && auth()->guard('user')->user()
                ? [[
                    'id'   => auth()->guard('user')->id(),
                    'name' => auth()->guard('user')->user()->name,
                ]]
                : [],
            'persons' => [],
        ];
    @endphp

    <script type="text/x-template" id="v-lead-stages-template">
        <!-- Stages Container -->
        <div
            class="flex w-full max-w-full"
            :class="{'opacity-50 pointer-events-none': isUpdating}"
        >
            <!-- Stages Item -->
            <template v-for="stage in stages">
                {!! view_render_event('admin.leads.view.stages.items.before', ['lead' => $lead]) !!}

                <div
                    class="stage relative flex h-7 cursor-pointer items-center justify-center bg-white pl-7 pr-4 dark:bg-gray-900 ltr:first:rounded-l-lg rtl:first:rounded-r-lg"
                    :class="{
                        '!bg-green-500 text-white dark:text-gray-900 ltr:after:bg-green-500 rtl:before:bg-green-500': currentStage.sort_order >= stage.sort_order,
                        '!bg-red-500 text-white dark:text-gray-900 ltr:after:bg-red-500 rtl:before:bg-red-500': currentStage.code == 'lost',
                    }"
                    v-if="! ['won', 'lost'].includes(stage.code)"
                    @click="update(stage)"
                >
                    <span class="z-20 whitespace-nowrap text-sm font-medium dark:text-white">
                        @{{ stage.name }}
                    </span>
                </div>

                {!! view_render_event('admin.leads.view.stages.items.after', ['lead' => $lead]) !!}
            </template>

            {!! view_render_event('admin.leads.view.stages.items.dropdown.before', ['lead' => $lead]) !!}

            <!-- Won/Lost Stage Item -->
            <x-admin::dropdown position="bottom-right">
                <x-slot:toggle>
                    {!! view_render_event('admin.leads.view.stages.items.dropdown.toggle.before', ['lead' => $lead]) !!}

                    <div
                        class="relative flex h-7 min-w-24 cursor-pointer items-center justify-center rounded-r-lg bg-white pl-7 pr-4 dark:bg-gray-900"
                        :class="{
                            '!bg-green-500 text-white dark:text-gray-900 after:bg-green-500': ['won', 'lost'].includes(currentStage.code) && currentStage.code == 'won',
                            '!bg-red-500 text-white dark:text-gray-900 after:bg-red-500': ['won', 'lost'].includes(currentStage.code) && currentStage.code == 'lost',
                        }"
                        @click="stageToggler = ! stageToggler"
                    >
                        <span class="z-20 whitespace-nowrap text-sm font-medium dark:text-white">
                            {{ __('admin::app.leads.view.stages.won-lost') }}
                        </span>

                        <span
                            class="text-2xl dark:text-gray-900"
                            :class="{'icon-up-arrow': stageToggler, 'icon-down-arrow': ! stageToggler}"
                        ></span>
                    </div>

                    {!! view_render_event('admin.leads.view.stages.items.dropdown.toggle.after', ['lead' => $lead]) !!}
                </x-slot>

                <x-slot:menu>
                    {!! view_render_event('admin.leads.view.stages.items.dropdown.menu_item.before', ['lead' => $lead]) !!}

                    <x-admin::dropdown.menu.item
                        @click="openModal(this.stages.find(stage => stage.code == 'won'))"
                    >
                        @lang('admin::app.leads.view.stages.won')
                    </x-admin::dropdown.menu.item>

                    <x-admin::dropdown.menu.item
                        @click="openModal(this.stages.find(stage => stage.code == 'lost'))"
                    >
                        @lang('admin::app.leads.view.stages.lost')
                    </x-admin::dropdown.menu.item>

                    {!! view_render_event('admin.leads.view.stages.items.dropdown.menu_item.after', ['lead' => $lead]) !!}
                </x-slot>
            </x-admin::dropdown>

            {!! view_render_event('admin.leads.view.stages.items.dropdown.after', ['lead' => $lead]) !!}

            {!! view_render_event('admin.leads.view.stages.form_controls.before', ['lead' => $lead]) !!}

            <x-admin::form
                v-slot="{ meta, errors, handleSubmit }"
                as="div"
                ref="stageUpdateForm"
            >
                <form @submit="handleSubmit($event, handleFormSubmit)">
                    {!! view_render_event('admin.leads.view.stages.form_controls.modal.before', ['lead' => $lead]) !!}

                    <x-admin::modal ref="stageUpdateModal">
                        <x-slot:header>
                            {!! view_render_event('admin.leads.view.stages.form_controls.modal.header.before', ['lead' => $lead]) !!}

                            <h3 class="text-base font-semibold dark:text-white">
                                @lang('admin::app.leads.view.stages.need-more-info')
                            </h3>

                            {!! view_render_event('admin.leads.view.stages.form_controls.modal.header.after', ['lead' => $lead]) !!}
                        </x-slot>

                        <x-slot:content>
                            {!! view_render_event('admin.leads.view.stages.form_controls.modal.content.before', ['lead' => $lead]) !!}

                            <!-- Won Value -->
                            <template v-if="nextStage.code == 'won'">
                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label>
                                        @lang('admin::app.leads.view.stages.won-value')
                                    </x-admin::form.control-group.label>

                                    <x-admin::form.control-group.control
                                        type="price"
                                        name="lead_value"
                                        :value="$lead->lead_value"
                                        v-model="nextStage.lead_value"
                                    />
                                </x-admin::form.control-group>
                            </template>

                            <!-- Lost Reason -->
                            <template v-else>
                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label>
                                        @lang('admin::app.leads.view.stages.lost-reason')
                                    </x-admin::form.control-group.label>

                                    <x-admin::form.control-group.control
                                        type="textarea"
                                        name="lost_reason"
                                        v-model="nextStage.lost_reason"
                                    />
                                </x-admin::form.control-group>
                            </template>

                            <!-- Closed At -->
                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label>
                                    @lang('admin::app.leads.view.stages.closed-at')
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.control
                                    type="datetime"
                                    name="closed_at"
                                    v-model="nextStage.closed_at"
                                    :label="trans('admin::app.leads.view.stages.closed-at')"
                                />

                                <x-admin::form.control-group.error control-name="closed_at"/>
                            </x-admin::form.control-group>

                            {!! view_render_event('admin.leads.view.stages.form_controls.modal.content.after', ['lead' => $lead]) !!}
                        </x-slot>

                        <x-slot:footer>
                            {!! view_render_event('admin.leads.view.stages.form_controls.modal.footer.before', ['lead' => $lead]) !!}

                            <button
                                type="submit"
                                class="primary-button"
                            >
                                @lang('admin::app.leads.view.stages.save-btn')
                            </button>

                            {!! view_render_event('admin.leads.view.stages.form_controls.modal.footer.after', ['lead' => $lead]) !!}
                        </x-slot>
                    </x-admin::modal>

                    {!! view_render_event('admin.leads.view.stages.form_controls.modal.after', ['lead' => $lead]) !!}
                </form>
            </x-admin::form>

                    {!! view_render_event('admin.leads.view.stages.form_controls.after', ['lead' => $lead]) !!}

            <x-admin::form
                v-slot="{ meta, errors, handleSubmit }"
                as="div"
                ref="meetingActivityForm"
            >
                <form @submit="handleSubmit($event, createMeetingAndMove)">
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

                                @if (lead_variant() === 'lge')
                                    <x-admin::form.control-group>
                                        <x-admin::form.control-group.label class="required">
                                            Assigned Owner
                                        </x-admin::form.control-group.label>

                                        <select
                                            name="assigned_user_id"
                                            class="w-full rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-800 outline-none focus:border-brandColor dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                        >
                                            <option value="">Select Admin / Lead User</option>

                                            @foreach ($meetingOwnerOptions ?? [] as $user)
                                                <option value="{{ $user['id'] }}">
                                                    {{ $user['name'] }}@if (! empty($user['role_name'])) - {{ $user['role_name'] }}@endif @if (! empty($user['email']))({{ $user['email'] }})@endif
                                                </option>
                                            @endforeach
                                        </select>

                                        <x-admin::form.control-group.error control-name="assigned_user_id" />
                                    </x-admin::form.control-group>
                                @endif

                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label class="required">
                                        Participants
                                    </x-admin::form.control-group.label>

                                    <v-activity-participants
                                        :participants="defaultMeetingParticipants"
                                        :show-all-users="true"
                                        :users-only="true"
                                        :user-role-names="['administrator', 'lead']"
                                    ></v-activity-participants>

                                    <p
                                        class="mt-1 text-xs text-red-600"
                                        v-if="meetingErrors.participants"
                                    >
                                        @{{ meetingErrors.participants }}
                                    </p>
                                </x-admin::form.control-group>

                                <x-admin::form.control-group class="!mb-0">
                                    <x-admin::form.control-group.label class="required">
                                        Meeting Channel
                                    </x-admin::form.control-group.label>

                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="location"
                                        rules="required"
                                        label="Meeting Channel"
                                    />

                                    <x-admin::form.control-group.error control-name="location" />
                                </x-admin::form.control-group>
                            </div>
                        </x-slot>

                        <x-slot:footer>
                            <button
                                type="submit"
                                class="primary-button"
                                :disabled="isMeetingStoring"
                            >
                                <template v-if="isMeetingStoring">
                                    Saving...
                                </template>

                                <template v-else>
                                    Save Meeting
                                </template>
                            </button>
                        </x-slot>
                    </x-admin::modal>
                </form>
            </x-admin::form>

            <x-admin::modal
                ref="lgeSdrHandoffModal"
                position="center"
            >
                <x-slot:header>
                    <h3 class="text-base font-semibold dark:text-white">
                        Assign Admin/Lead Owner
                    </h3>
                </x-slot>

                <x-slot:content>
                    <p class="mb-4 text-sm text-gray-600 dark:text-gray-300">
                        Select the Admin or Lead user who will own this lead after the meeting.
                    </p>

                    <x-admin::form.control-group class="!mb-0">
                        <x-admin::form.control-group.label class="required">
                            Admin / Lead User
                        </x-admin::form.control-group.label>

                        <select
                            v-model="pendingHandoffSdrUserId"
                            class="w-full rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-800 outline-none focus:border-brandColor dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        >
                            <option value="">Select Admin / Lead User</option>

                            <option
                                v-for="user in meetingOwnerOptions"
                                :key="'detail-handoff-owner-' + user.id"
                                :value="user.id"
                            >
                                @{{ user.name }} <template v-if="user.role_name">- @{{ user.role_name }}</template> <template v-if="user.email">(@{{ user.email }})</template>
                            </option>
                        </select>
                    </x-admin::form.control-group>
                </x-slot>

                <x-slot:footer>
                    <button
                        type="button"
                        class="primary-button"
                        :disabled="isHandoffSaving"
                        @click="applyLgeSdrHandoff"
                    >
                        <template v-if="isHandoffSaving">Saving...</template>
                        <template v-else>Assign & Move</template>
                    </button>
                </x-slot>
            </x-admin::modal>
        </div>
    </script>

    <script type="module">
        app.component('v-lead-stages', {
            template: '#v-lead-stages-template',

            data() {
                return {
                    isUpdating: false,

                    currentStage: @json($lead->stage),

                    nextStage: null,

                    pendingMeetingStage: null,

                    stages: @json($accessibleViewStages->values()),

                    isLgeLeadVariant: @json(lead_variant() === 'lge'),

                    meetingOwnerOptions: @json($meetingOwnerOptions ?? []),

                    pendingHandoffStage: null,

                    pendingHandoffParams: null,

                    pendingHandoffSdrUserId: '',

                    isHandoffSaving: false,

                    lead: @json([
                        'id'    => $lead->id,
                        'title' => $lead->title,
                    ]),

                    hasMeetingActivity: @json($hasMeetingActivity),

                    defaultMeetingParticipants: @json($defaultMeetingParticipants),

                    isMeetingStoring: false,

                    meetingErrors: {},

                    stageToggler: '',
                }
            },

            methods: {
                openModal(stage) {
                    if (this.currentStage.code == stage.code) {
                        return;
                    }

                    this.nextStage = stage;

                    this.$refs.stageUpdateModal.open();
                },

                handleFormSubmit(event) {
                    let params = {
                        'lead_pipeline_stage_id': this.nextStage.id
                    };

                    if (this.nextStage.code == 'won') {
                        params.lead_value = this.nextStage.lead_value;

                        params.closed_at = this.nextStage.closed_at;
                    } else if (this.nextStage.code == 'lost') {
                        params.lost_reason = this.nextStage.lost_reason;

                        params.closed_at = this.nextStage.closed_at;
                    }

                    this.update(this.nextStage, params);
                },

                update(stage, params = null) {
                    if (this.currentStage.code == stage.code) {
                        return;
                    }

                    if (stage.code === 'meeting' && ! this.hasMeetingActivity) {
                        this.pendingMeetingStage = stage;
                        this.meetingErrors = {};

                        this.$refs.meetingActivityModal.open();

                        return;
                    }

                    if (this.requiresLgeSdrHandoff(stage)) {
                        this.pendingHandoffStage = stage;
                        this.pendingHandoffParams = params ?? {
                            lead_pipeline_stage_id: stage.id,
                        };
                        this.pendingHandoffSdrUserId = '';
                        this.$refs.stageUpdateModal.close();
                        this.$refs.lgeSdrHandoffModal.open();

                        return;
                    }

                    this.$refs.stageUpdateModal.close();

                    this.isUpdating = true;

                    this.$axios
                        .put("{{ lead_route('stage.update', $lead->id) }}", params ?? {
                            'lead_pipeline_stage_id': stage.id
                        })
                        .then ((response) => {
                            this.isUpdating = false;

                            this.currentStage = stage;

                            this.$parent.$refs.activities.get();

                            this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                        })
                        .catch ((error) => {
                            this.isUpdating = false;

                            this.$emitter.emit('add-flash', { type: 'error', message: error.response.data.message });
                        });
                },

                requiresLgeSdrHandoff(stage) {
                    if (! this.isLgeLeadVariant || ! stage) {
                        return false;
                    }

                    const meetingStage = this.stages.find(item => item.code === 'meeting');

                    return this.currentStage?.code === 'meeting'
                        && meetingStage
                        && Number(stage.sort_order) > Number(meetingStage.sort_order);
                },

                applyLgeSdrHandoff() {
                    if (! this.pendingHandoffSdrUserId) {
                        this.$emitter.emit('add-flash', {
                            type: 'error',
                            message: 'Please select an SDR user.',
                        });

                        return;
                    }

                    const stage = this.pendingHandoffStage;
                    const params = {
                        ...(this.pendingHandoffParams || {}),
                        lead_pipeline_stage_id: stage.id,
                        sdr_user_id: this.pendingHandoffSdrUserId,
                    };

                    this.isHandoffSaving = true;

                    this.$axios
                        .put("{{ lead_route('stage.update', $lead->id) }}", params)
                        .then((response) => {
                            this.isHandoffSaving = false;
                            this.currentStage = stage;
                            this.pendingHandoffStage = null;
                            this.pendingHandoffParams = null;
                            this.pendingHandoffSdrUserId = '';
                            this.$refs.lgeSdrHandoffModal.close();
                            this.$parent.$refs.activities.get();
                            this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                        })
                        .catch((error) => {
                            this.isHandoffSaving = false;
                            this.$emitter.emit('add-flash', {
                                type: 'error',
                                message: error.response?.data?.message || error.response?.data?.errors?.sdr_user_id?.[0] || 'Update failed.',
                            });
                        });
                },

                createMeetingAndMove(params) {
                    this.meetingErrors = {};

                    if (! this.hasParticipants(params.participants || {})) {
                        this.meetingErrors = {
                            participants: 'Please select at least one participant.',
                        };

                        return;
                    }

                    this.isMeetingStoring = true;

                    this.$axios
                        .post("{{ route('admin.activities.store') }}", {
                            ...params,
                            type: 'meeting',
                            activity_status: 'scheduled',
                            stage_meeting: 1,
                            lead_id: this.lead.id,
                        })
                        .then((response) => {
                            this.isMeetingStoring = false;
                            this.hasMeetingActivity = true;

                            this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                            this.$emitter.emit('on-activity-added', response.data.data);
                            this.$emitter.emit('activity-created');

                            this.$refs.meetingActivityModal.close();

                            const stage = this.pendingMeetingStage;

                            this.pendingMeetingStage = null;

                            if (stage) {
                                this.update(stage, {
                                    lead_pipeline_stage_id: stage.id,
                                    assigned_user_id: params.assigned_user_id,
                                });
                            }
                        })
                        .catch((error) => {
                            this.isMeetingStoring = false;

                            if (error.response?.status === 422) {
                                setErrors(error.response.data.errors || {});

                                this.meetingErrors = {
                                    participants: error.response.data.errors?.participants?.[0],
                                };

                                return;
                            }

                            this.$emitter.emit('add-flash', { type: 'error', message: error.response?.data?.message || 'Meeting could not be saved.' });
                        });
                },

                hasParticipants(participants) {
                    return ['users', 'persons'].some(type => {
                        return (participants[type] || []).some(participantId => !! participantId);
                    });
                },
            },
        });
    </script>
@endPushOnce
