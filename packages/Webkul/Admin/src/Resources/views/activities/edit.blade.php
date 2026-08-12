<x-admin::layouts>
    <!-- Page Title -->
    <x-slot:title>
        @lang('admin::app.activities.edit.title')
    </x-slot>

    {!! view_render_event('admin.activities.edit.form.before') !!}

    <x-admin::form
        id="activity-edit-form"
        :action="route('admin.activities.update', $activity->id)"
        method="PUT"
    >
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <!-- Breadcrumbs -->
                    <x-admin::breadcrumbs
                        name="activities.edit"
                        :entity="$activity"
                    />

                    <!-- Page Title -->
                    <div class="text-xl font-bold dark:text-gray-300">
                        @lang('admin::app.activities.edit.title')
                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <!-- Create button for person -->
                    <div class="flex items-center gap-x-2.5">
                        {!! view_render_event('admin.activities.edit.save_button.before') !!}

                        <!-- Save Button -->
                        <button
                            type="submit"
                            class="primary-button"
                        >
                            @lang('admin::app.activities.edit.save-btn')
                        </button>

                        {!! view_render_event('admin.activities.edit.save_button.after') !!}
                    </div>
                </div>
            </div>

            <!-- Form Content -->
            <div class="flex gap-2.5 max-xl:flex-wrap-reverse">
                <!-- Left sub-component -->
                <div class="box-shadow flex-1 gap-2 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900 max-xl:flex-auto">
                    {!! view_render_event('admin.activities.edit.form_controls.before') !!}

                    <!-- Schedule Date -->
                    <x-admin::form.control-group>
                        <div class="flex gap-2 max-sm:flex-wrap">
                            <div class="w-full">
                                <x-admin::form.control-group.label class="required">
                                    @lang('admin::app.activities.edit.schedule_from')
                                </x-admin::form.control-group.label>

                                <x-admin::flat-picker.datetime class="!w-full" ::allow-input="true">
                                    <input
                                        name="schedule_from"
                                        value="{{ old('schedule_from') ?? $activity->schedule_from }}"
                                        class="flex w-full rounded-md border px-3 py-2 text-sm text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400"
                                        placeholder="@lang('admin::app.activities.edit.schedule_from')"
                                    />
                                </x-admin::flat-picker.datetime>
                            </div>

                            <div class="w-full">
                                <x-admin::form.control-group.label class="required">
                                    @lang('admin::app.activities.edit.schedule_to')
                                </x-admin::form.control-group.label>

                                <x-admin::flat-picker.datetime class="!w-full" ::allow-input="true">
                                    <input
                                        name="schedule_to"
                                        id="activity-schedule-to"
                                        value="{{ old('schedule_to') ?? $activity->schedule_to }}"
                                        class="flex w-full rounded-md border px-3 py-2 text-sm text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400"
                                        placeholder="@lang('admin::app.activities.edit.schedule_to')"
                                    />
                                </x-admin::flat-picker.datetime>

                                <p
                                    id="activity-schedule-range-error"
                                    class="mt-1 hidden text-xs text-red-600"
                                >
                                    Schedule To must be later than Schedule From.
                                </p>
                            </div>
                        </div>
                    </x-admin::form.control-group>

                    <!-- Comment -->
                    <x-admin::form.control-group>
                        <x-admin::form.control-group.label>
                            @lang('admin::app.activities.edit.comment')
                        </x-admin::form.control-group.label>

                        <x-admin::form.control-group.control
                            type="textarea"
                            name="comment"
                            id="comment"
                            :value="old('comment') ?? $activity->comment"
                            :label="trans('admin::app.activities.edit.comment')"
                            :placeholder="trans('admin::app.activities.edit.comment')"
                        />

                        <x-admin::form.control-group.error control-name="comment" />
                    </x-admin::form.control-group>

                    <!-- Participants -->
                    <x-admin::form.control-group>
                        <x-admin::form.control-group.label>
                            @lang('admin::app.activities.edit.participants')
                        </x-admin::form.control-group.label>

                        <!-- Participants Multi lookup Vue Component -->
                        <v-multi-lookup-component>
                            <div
                                class="relative rounded border border-gray-200 px-2 py-1 hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:hover:border-gray-400 dark:focus:border-gray-400"
                                role="button"
                            >
                                <ul class="flex flex-wrap items-center gap-1">
                                    <li>
                                        <input
                                            type="text"
                                            class="w-full px-1 py-1 dark:bg-gray-900 dark:text-gray-300"
                                            placeholder="@lang('admin::app.activities.edit.participants')"
                                        />
                                    </li>
                                </ul>

                                <span class="icon-down-arrow absolute top-1.5 text-2xl ltr:right-1.5 rtl:left-1.5"></span>
                            </div>
                        </v-multi-lookup-component>
                    </x-admin::form.control-group>

                    <!-- Lead -->
                    <x-admin::form.control-group class="!mb-0">
                        <x-admin::form.control-group.label>
                            @lang('admin::app.activities.edit.lead')
                        </x-admin::form.control-group.label>

                        <x-admin::attributes.edit.lookup/>

                        <!-- Lead Lookup Vue Component -->
                        <v-lookup-component
                            :attribute="{'code': 'lead_id', 'name': 'Lead', 'lookup_type': 'leads'}"
                            :value='@json($lookUpEntityData)'
                        >
                            <x-admin::form.control-group.control
                                type="text"
                                placeholder="@lang('admin::app.common.start-typing')"
                            />
                        </v-lookup-component>
                    </x-admin::form.control-group>

                    {!! view_render_event('admin.activities.edit.form_controls.after') !!}
                </div>

                <!-- Right sub-component -->
                <div class="w-[360px] max-w-full gap-2 max-xl:w-full">
                    {!! view_render_event('admin.activities.edit.accordion.general.before') !!}

                    <x-admin::accordion>
                        <x-slot:header>
                            <div class="flex items-center justify-between">
                                <p class="p-2.5 text-base font-semibold text-gray-800 dark:text-white">
                                    @lang('admin::app.activities.edit.general')
                                </p>
                            </div>
                        </x-slot>

                        <x-slot:content>
                            <!-- Title -->
                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label class="required">
                                    @lang('admin::app.activities.edit.title')
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.control
                                    type="text"
                                    name="title"
                                    id="title"
                                    rules="required"
                                    :value="old('title') ?? $activity->title"
                                    :label="trans('admin::app.activities.edit.title')"
                                    :placeholder="trans('admin::app.activities.edit.title')"
                                />

                                <x-admin::form.control-group.error control-name="title" />
                            </x-admin::form.control-group>

                            <!-- Edit Type -->
                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label class="required">
                                    @lang('admin::app.activities.edit.type')
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.control
                                    type="select"
                                    name="type"
                                    id="type"
                                    :value="old('type') ?? $activity->type"
                                    rules="required"
                                    :label="trans('admin::app.activities.edit.type')"
                                    :placeholder="trans('admin::app.activities.edit.type')"
                                >
                                    <option value="call">
                                        @lang('admin::app.activities.edit.call')
                                    </option>

                                    <option value="meeting">
                                        @lang('admin::app.activities.edit.meeting')
                                    </option>
                                </x-admin::form.control-group.control>

                                <x-admin::form.control-group.error control-name="type" />
                            </x-admin::form.control-group>

                            <!-- Activity Status -->
                            @php
                                $activityStatus = old('activity_status');

                                if ($activityStatus === null) {
                                    $callStatus = $activity->call_status ?? 'scheduled';
                                    $callStatusAt = $activity->call_status_updated_at ?? $activity->updated_at ?? $activity->schedule_from;

                                    if (
                                        $callStatus === 'not_answered'
                                        && $callStatusAt
                                        && \Carbon\Carbon::parse($callStatusAt)->lte(now()->subHours(2))
                                    ) {
                                        $callStatus = 'scheduled';
                                    }

                                    $activityStatus = $activity->is_done
                                        ? 'done'
                                        : ($activity->type === 'meeting'
                                            ? 'meeting_scheduled'
                                            : ($callStatus === 'not_answered'
                                                ? 'not_answered'
                                                : 'scheduled'));
                                }
                            @endphp

                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label>
                                    Activity Status
                                </x-admin::form.control-group.label>

                                <select
                                    name="activity_status"
                                    id="activity_status"
                                    data-initial-status="{{ $activityStatus }}"
                                    class="custom-select w-full rounded border border-gray-200 px-2.5 py-2 text-sm font-normal text-gray-800 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400"
                                >
                                    <option value="scheduled" @selected($activityStatus === 'scheduled')>
                                        Scheduled
                                    </option>

                                    <option value="not_answered" @selected($activityStatus === 'not_answered')>
                                        Not Answered
                                    </option>

                                    <option value="meeting_scheduled" @selected($activityStatus === 'meeting_scheduled')>
                                        Meeting Schedule
                                    </option>

                                    <option value="done" @selected($activityStatus === 'done')>
                                        End the lead
                                    </option>
                                </select>

                                <input
                                    type="hidden"
                                    name="end_lead_comment"
                                    id="end_lead_comment"
                                    value="{{ old('end_lead_comment') }}"
                                />

                                <div
                                    id="meeting-schedule-info"
                                    class="mt-2 hidden rounded-md border border-blue-200 bg-blue-50 p-3 text-xs leading-5 text-blue-700"
                                >
                                    Meeting Schedule changes this activity to a meeting. Schedule, comment, lead, and meeting channel are required.
                                </div>

                                <button
                                    type="button"
                                    id="end-lead-comment-button"
                                    class="secondary-button mt-2 hidden !min-h-[34px] !px-3 text-xs"
                                >
                                    Add End Comment
                                </button>
                            </x-admin::form.control-group>

                            <!-- Location -->
                            <x-admin::form.control-group class="!mb-0">
                                <x-admin::form.control-group.label>
                                    @lang('admin::app.activities.edit.location')
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.control
                                    type="text"
                                    name="location"
                                    id="location"
                                    :value="old('location') ?? $activity->location"
                                    :label="trans('admin::app.activities.edit.location')"
                                    :placeholder="trans('admin::app.activities.edit.location')"
                                />

                                <x-admin::form.control-group.error control-name="location" />
                            </x-admin::form.control-group>
                        </x-slot>
                    </x-admin::accordion>

                    {!! view_render_event('admin.activities.edit.accordion.general.after') !!}
                </div>
            </div>
        </div>
    </x-admin::form>

    <div
        id="end-lead-comment-modal"
        class="fixed inset-0 z-[10002] hidden items-center justify-center bg-black/50 p-4"
    >
        <div class="w-full max-w-[520px] rounded-lg bg-white shadow-xl dark:bg-gray-900">
            <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                    End the lead
                </h3>

                <button
                    type="button"
                    id="end-lead-comment-close"
                    class="icon-cross-large rounded-md p-1 text-xl text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                ></button>
            </div>

            <div class="px-5 py-4">
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Comment <span class="text-red-600">*</span>
                </label>

                <textarea
                    id="end-lead-comment-input"
                    class="min-h-[120px] w-full rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-700 hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                    placeholder="Add details before ending this lead."
                ></textarea>

                <p
                    id="end-lead-comment-error"
                    class="mt-1 hidden text-xs text-red-600"
                >
                    Please add a comment before ending the lead.
                </p>
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-gray-200 px-5 py-4 dark:border-gray-800">
                <button
                    type="button"
                    id="end-lead-comment-cancel"
                    class="secondary-button"
                >
                    Cancel
                </button>

                <button
                    type="button"
                    id="end-lead-comment-apply"
                    class="primary-button"
                >
                    Save Comment
                </button>
            </div>
        </div>
    </div>

    <div
        id="meeting-schedule-modal"
        class="fixed inset-0 z-[10002] hidden items-center justify-center bg-black/50 p-4"
    >
        <div class="w-full max-w-[680px] rounded-lg bg-white shadow-xl dark:bg-gray-900">
            <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                    Meeting Schedule
                </h3>

                <button
                    type="button"
                    id="meeting-schedule-close"
                    class="icon-cross-large rounded-md p-1 text-xl text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                ></button>
            </div>

            <div class="grid gap-4 px-5 py-4">
                <div class="grid grid-cols-2 gap-3 max-sm:grid-cols-1">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Schedule From <span class="text-red-600">*</span>
                        </label>

                        <x-admin::flat-picker.datetime class="!w-full" ::allow-input="true">
                            <input
                                id="meeting-schedule-from"
                                class="flex w-full rounded-md border px-3 py-2 text-sm text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400"
                                placeholder="Schedule From"
                            />
                        </x-admin::flat-picker.datetime>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Schedule To <span class="text-red-600">*</span>
                        </label>

                        <x-admin::flat-picker.datetime class="!w-full" ::allow-input="true">
                            <input
                                id="meeting-schedule-to"
                                class="flex w-full rounded-md border px-3 py-2 text-sm text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400"
                                placeholder="Schedule To"
                            />
                        </x-admin::flat-picker.datetime>

                        <p
                            id="meeting-schedule-range-error"
                            class="mt-1 hidden text-xs text-red-600"
                        >
                            Schedule To must be later than Schedule From.
                        </p>
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Meeting Channel <span class="text-red-600">*</span>
                    </label>

                    <input
                        type="text"
                        id="meeting-schedule-location"
                        class="flex w-full rounded-md border px-3 py-2 text-sm text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400"
                        placeholder="Google Meet, Zoom, office, or phone"
                    />
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Comment <span class="text-red-600">*</span>
                    </label>

                    <textarea
                        id="meeting-schedule-comment"
                        class="min-h-[110px] w-full rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-700 hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                        placeholder="Add meeting details."
                    ></textarea>
                </div>

                <p
                    id="meeting-schedule-error"
                    class="hidden text-xs text-red-600"
                >
                    Please add schedule, location, and comment before changing this activity to a meeting.
                </p>
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-gray-200 px-5 py-4 dark:border-gray-800">
                <button
                    type="button"
                    id="meeting-schedule-cancel"
                    class="secondary-button"
                >
                    Cancel
                </button>

                <button
                    type="button"
                    id="meeting-schedule-apply"
                    class="primary-button"
                >
                    Apply Meeting
                </button>
            </div>
        </div>
    </div>

    {!! view_render_event('admin.activities.edit.form.after') !!}

    @pushOnce('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const form = document.getElementById('activity-edit-form');
                let status = document.getElementById('activity_status');
                const type = document.getElementById('type');
                const comment = document.getElementById('comment');
                const location = document.getElementById('location');
                const endLeadComment = document.getElementById('end_lead_comment');
                const meetingInfo = document.getElementById('meeting-schedule-info');
                const endCommentButton = document.getElementById('end-lead-comment-button');
                const modal = document.getElementById('end-lead-comment-modal');
                const modalInput = document.getElementById('end-lead-comment-input');
                const modalError = document.getElementById('end-lead-comment-error');
                const modalClose = document.getElementById('end-lead-comment-close');
                const modalCancel = document.getElementById('end-lead-comment-cancel');
                const modalApply = document.getElementById('end-lead-comment-apply');
                const meetingModal = document.getElementById('meeting-schedule-modal');
                const meetingFromInput = document.getElementById('meeting-schedule-from');
                const meetingToInput = document.getElementById('meeting-schedule-to');
                const meetingLocationInput = document.getElementById('meeting-schedule-location');
                const meetingCommentInput = document.getElementById('meeting-schedule-comment');
                const meetingError = document.getElementById('meeting-schedule-error');
                const meetingRangeError = document.getElementById('meeting-schedule-range-error');
                const activityScheduleRangeError = document.getElementById('activity-schedule-range-error');
                const meetingClose = document.getElementById('meeting-schedule-close');
                const meetingCancel = document.getElementById('meeting-schedule-cancel');
                const meetingApply = document.getElementById('meeting-schedule-apply');
                let meetingModalApplied = false;

                if (! form || ! status) {
                    return;
                }

                const scheduleFrom = form.querySelector('[name="schedule_from"]');
                const scheduleTo = form.querySelector('[name="schedule_to"]');

                const isScheduleToAfterFrom = function (fromValue, toValue) {
                    if (! fromValue || ! toValue) {
                        return true;
                    }

                    const fromTime = new Date(fromValue).getTime();
                    const toTime = new Date(toValue).getTime();

                    if (Number.isNaN(fromTime) || Number.isNaN(toTime)) {
                        return true;
                    }

                    return toTime > fromTime;
                };

                const syncScheduleRangeError = function (fromInput, toInput, errorEl) {
                    if (! errorEl) {
                        return true;
                    }

                    const isValid = isScheduleToAfterFrom(fromInput?.value, toInput?.value);

                    errorEl.classList.toggle('hidden', isValid);

                    return isValid;
                };
                let fallbackStatus = status.dataset.initialStatus === 'done'
                    ? 'scheduled'
                    : (status.dataset.initialStatus || 'scheduled');
                let endLeadCommentApplied = Boolean(endLeadComment?.value?.trim());

                const openEndCommentModal = function () {
                    if (! modal.classList.contains('hidden')) {
                        return;
                    }

                    modalInput.value = endLeadComment?.value || '';
                    modalError.classList.add('hidden');
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                    document.body.style.overflow = 'hidden';
                    setTimeout(() => modalInput.focus(), 50);
                };

                const closeEndCommentModal = function (revert = false) {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                    document.body.style.overflow = 'auto';

                    if (revert && ! endLeadCommentApplied) {
                        status.value = fallbackStatus;
                        syncStatusFields();
                    }
                };

                const setDateValue = function (input, value) {
                    if (! input) {
                        return;
                    }

                    if (input._flatpickr && value) {
                        input._flatpickr.setDate(value, true, 'Y-m-d H:i:S');

                        return;
                    }

                    input.value = value || '';
                };

                const openMeetingScheduleModal = function () {
                    if (! meetingModal.classList.contains('hidden')) {
                        return;
                    }

                    setDateValue(meetingFromInput, scheduleFrom?.value || '');
                    setDateValue(meetingToInput, scheduleTo?.value || '');
                    meetingLocationInput.value = location?.value || '';
                    meetingCommentInput.value = comment?.value || '';
                    meetingError.classList.add('hidden');
                    meetingModal.classList.remove('hidden');
                    meetingModal.classList.add('flex');
                    document.body.style.overflow = 'hidden';
                    setTimeout(() => meetingFromInput.focus(), 50);
                };

                const closeMeetingScheduleModal = function (revert = false) {
                    meetingModal.classList.add('hidden');
                    meetingModal.classList.remove('flex');
                    document.body.style.overflow = 'auto';

                    if (revert && ! meetingModalApplied) {
                        status.value = fallbackStatus;
                        syncStatusFields();
                    }
                };

                const syncStatusFields = function () {
                    const isMeetingSchedule = status.value === 'meeting_scheduled';
                    const isEndLead = status.value === 'done';

                    meetingInfo?.classList.toggle('hidden', ! isMeetingSchedule);
                    endCommentButton?.classList.toggle('hidden', ! isEndLead);

                    if (isMeetingSchedule) {
                        type.value = 'meeting';
                        location?.setAttribute('required', 'required');
                    } else {
                        location?.removeAttribute('required');
                    }
                };

                const handleStatusChange = function (event = null) {
                    if (event?.target?.id === 'activity_status') {
                        status = event.target;
                    }

                    if (status.value === 'meeting_scheduled') {
                        meetingModalApplied = false;
                        openMeetingScheduleModal();
                        syncStatusFields();

                        return;
                    }

                    if (status.value === 'done') {
                        endLeadCommentApplied = false;
                        endLeadComment.value = '';
                        openEndCommentModal();
                        syncStatusFields();

                        return;
                    }

                    fallbackStatus = status.value || 'scheduled';
                    endLeadComment.value = '';
                    endLeadCommentApplied = false;
                    syncStatusFields();
                };

                status.addEventListener('change', handleStatusChange);
                status.addEventListener('input', handleStatusChange);

                document.addEventListener('change', function (event) {
                    if (event.target?.id === 'activity_status') {
                        handleStatusChange(event);
                    }
                });

                document.addEventListener('input', function (event) {
                    if (event.target?.id === 'activity_status') {
                        handleStatusChange(event);
                    }
                });

                endCommentButton?.addEventListener('click', openEndCommentModal);
                modalClose?.addEventListener('click', function () {
                    closeEndCommentModal(true);
                });
                modalCancel?.addEventListener('click', function () {
                    closeEndCommentModal(true);
                });
                meetingClose?.addEventListener('click', function () {
                    closeMeetingScheduleModal(true);
                });
                meetingCancel?.addEventListener('click', function () {
                    closeMeetingScheduleModal(true);
                });

                modalApply?.addEventListener('click', function () {
                    if (! modalInput.value.trim()) {
                        modalError.classList.remove('hidden');

                        return;
                    }

                    endLeadComment.value = modalInput.value.trim();
                    comment.value = modalInput.value.trim();
                    endLeadCommentApplied = true;
                    fallbackStatus = 'done';
                    closeEndCommentModal();
                });

                meetingApply?.addEventListener('click', function () {
                    if (
                        ! meetingFromInput.value.trim()
                        || ! meetingToInput.value.trim()
                        || ! meetingLocationInput.value.trim()
                        || ! meetingCommentInput.value.trim()
                    ) {
                        meetingError.classList.remove('hidden');
                        meetingError.textContent = 'Please add schedule, location, and comment before changing this activity to a meeting.';

                        return;
                    }

                    if (! syncScheduleRangeError(meetingFromInput, meetingToInput, meetingRangeError)) {
                        meetingError.classList.add('hidden');

                        return;
                    }

                    meetingError.classList.add('hidden');

                    setDateValue(scheduleFrom, meetingFromInput.value.trim());
                    setDateValue(scheduleTo, meetingToInput.value.trim());
                    location.value = meetingLocationInput.value.trim();
                    comment.value = meetingCommentInput.value.trim();
                    type.value = 'meeting';
                    meetingModalApplied = true;
                    fallbackStatus = 'meeting_scheduled';
                    closeMeetingScheduleModal();
                    syncStatusFields();
                });

                const bindScheduleRangeWatchers = function (fromInput, toInput, errorEl) {
                    if (! fromInput || ! toInput) {
                        return;
                    }

                    const validate = function () {
                        syncScheduleRangeError(fromInput, toInput, errorEl);
                    };

                    ['change', 'input', 'blur'].forEach((eventName) => {
                        fromInput.addEventListener(eventName, validate);
                        toInput.addEventListener(eventName, validate);
                    });
                };

                bindScheduleRangeWatchers(scheduleFrom, scheduleTo, activityScheduleRangeError);
                bindScheduleRangeWatchers(meetingFromInput, meetingToInput, meetingRangeError);

                const handleActivityFormSubmit = function (event) {
                    status = document.getElementById('activity_status') || status;

                    if (! syncScheduleRangeError(scheduleFrom, scheduleTo, activityScheduleRangeError)) {
                        event.preventDefault();
                        scheduleTo?.focus();

                        return;
                    }

                    if (
                        status.value === 'meeting_scheduled'
                        && (
                            ! scheduleFrom?.value?.trim()
                            || ! scheduleTo?.value?.trim()
                            || ! location?.value?.trim()
                            || ! comment?.value?.trim()
                        )
                    ) {
                        event.preventDefault();
                        openMeetingScheduleModal();

                        return;
                    }

                    if (status.value !== 'done' || endLeadComment?.value?.trim()) {
                        return;
                    }

                    event.preventDefault();
                    openEndCommentModal();
                };

                form.addEventListener('submit', handleActivityFormSubmit, true);

                document.addEventListener('submit', function (event) {
                    if (event.target?.id === 'activity-edit-form') {
                        handleActivityFormSubmit(event);
                    }
                }, true);

                syncStatusFields();
            });
        </script>

        <script
            type="text/x-template"
            id="v-multi-lookup-component-template"
        >
            <!-- Search Button -->
            <div class="relative">
                <div class="relative rounded border border-gray-200 px-2 py-1 hover:border-gray-400 focus:border-gray-400 dark:border-gray-800" role="button">
                    <ul class="flex flex-wrap items-center gap-1">
                        <!-- Added Participants -->
                        <template v-for="userType in ['users', 'persons']">
                            <template v-if="! addedParticipants[userType].length">
                                <input
                                    type="hidden"
                                    :name="`participants[${userType}][]`"
                                    value=""
                                />
                            </template>

                            <li
                                class="flex items-center gap-1 rounded-md bg-slate-100 pl-2 dark:bg-slate-950 dark:text-gray-300"
                                v-for="(user, index) in addedParticipants[userType]"
                            >
                                <!-- Person and User Hidden Input Field -->
                                <input
                                    type="hidden"
                                    :name="`participants[${userType}][]`"
                                    :value="user.id"
                                />

                                @{{ user.name }}

                                <span
                                    class="icon-cross-large cursor-pointer p-0.5 text-xl"
                                    @click="remove(userType, user)"
                                ></span>
                            </li>
                        </template>

                        <!-- Search Input Box -->
                        <li>
                            <input
                                type="text"
                                class="w-full px-1 py-1 dark:bg-gray-900 dark:text-gray-300"
                                placeholder="@lang('admin::app.activities.edit.participants')"
                                v-model="searchTerm"
                                @input="queueSearch"
                                @keyup.enter="searchNow"
                            />
                        </li>
                    </ul>

                    <!-- Search and Spinner Icon -->
                    <div>
                        <template v-if="isPending || isSearching.users || isSearching.persons">
                            <div
                                class="app-search-spinner absolute top-2 ltr:right-2 rtl:left-2"
                                title="Searching..."
                            ></div>
                        </template>

                        <template v-else>
                            <span
                                class="absolute top-1.5 text-2xl ltr:right-1.5 rtl:left-1.5"
                                :class="[searchTerm.length >= 2 ? 'icon-up-arrow' : 'icon-down-arrow']"
                            ></span>
                        </template>
                    </div>
                </div>

                <!-- Search Dropdown -->
                <div
                    class="absolute z-10 w-full rounded bg-white shadow-[0px_10px_20px_0px_#0000001F] dark:bg-gray-900"
                    v-if="searchTerm.length >= 2"
                >
                    <ul class="flex flex-col gap-1 p-2">
                        <!-- Users and Person Searched Participants -->
                        <li
                            class="flex flex-col gap-2"
                            v-for="userType in ['users', 'persons']"
                        >
                            <h3 class="text-sm font-bold text-gray-600 dark:text-gray-400">
                                <template v-if="userType === 'users'">
                                    @lang('admin::app.activities.edit.users')
                                </template>

                                <template v-else>
                                    @lang('admin::app.activities.edit.persons')
                                </template>
                            </h3>

                            <ul>
                                <li
                                    class="rounded-sm px-5 py-2 text-sm text-gray-800 dark:text-gray-300"
                                    v-if="! searchedParticipants[userType].length && ! isSearching[userType]"
                                >
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        @lang('admin::app.activities.edit.no-result-found')
                                    </p>
                                </li>

                                <li
                                    class="cursor-pointer rounded-sm px-3 py-2 text-sm text-gray-800 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-950"
                                    v-for="user in searchedParticipants[userType]"
                                    @click="add(userType, user)"
                                >
                                    @{{ user.name }}
                                </li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </script>

        <script type="module">
            app.component('v-multi-lookup-component', {
                template: '#v-multi-lookup-component-template',

                data() {
                    return {
                        isSearching: {
                            users: false,

                            persons: false,
                        },

                        searchTerm: '',

                        isPending: false,

                        debounceTimer: null,

                        debounceMs: 2000,

                        addedParticipants: {
                            users: [],

                            persons: [],
                        },

                        searchedParticipants: {
                            users: [],

                            persons: [],
                        },

                        searchEnpoints: {
                            users: "{{ route('admin.settings.users.search') }}",

                            persons: "{{ route('admin.contacts.persons.search') }}",
                        },
                    };
                },

                created() {
                    @json($activity->participants).forEach(participant => {
                        if (participant.user) {
                            this.addedParticipants.users.push(participant.user);
                        } else if (participant.person) {
                            this.addedParticipants.persons.push(participant.person);
                        }
                    });
                },

                beforeUnmount() {
                    clearTimeout(this.debounceTimer);
                },

                methods: {
                    queueSearch() {
                        clearTimeout(this.debounceTimer);

                        if (! (this.searchTerm || '').trim()) {
                            this.isPending = false;
                            this.search('users');
                            this.search('persons');

                            return;
                        }

                        this.isPending = true;

                        this.debounceTimer = setTimeout(() => {
                            this.isPending = false;
                            this.search('users');
                            this.search('persons');
                        }, this.debounceMs);
                    },

                    searchNow() {
                        clearTimeout(this.debounceTimer);
                        this.isPending = false;
                        this.search('users');
                        this.search('persons');
                    },

                    search(userType) {
                        if (this.searchTerm.length <= 1) {
                            this.searchedParticipants[userType] = [];

                            this.isSearching[userType] = false;

                            return;
                        }

                        this.isSearching[userType] = true;

                        this.$axios.get(this.searchEnpoints[userType], {
                                params: {
                                    search: 'name:' + this.searchTerm,
                                    searchFields: 'name:like',
                                }
                            })
                            .then ((response) => {
                                this.addedParticipants[userType].forEach(addedParticipant =>
                                    response.data.data = response.data.data.filter(participant => participant.id !== addedParticipant.id)
                                );

                                this.searchedParticipants[userType] = response.data.data;

                                this.isSearching[userType] = false;
                            })
                            .catch (function (error) {
                                this.isSearching[userType] = false;
                            });
                    },

                    add(userType, participant) {
                        this.addedParticipants[userType].push(participant);

                        this.searchTerm = '';

                        this.searchedParticipants = {
                            users: [],

                            persons: [],
                        };
                    },

                    remove(userType, participant) {
                        this.addedParticipants[userType] = this.addedParticipants[userType].filter(addedParticipant =>
                            addedParticipant.id !== participant.id
                        );
                    },
                },
            });
        </script>
    @endPushOnce

    @pushOnce('styles')
        <style>
            .app-search-spinner {
                width: 16px;
                height: 16px;
                border: 2px solid #d1d5db;
                border-top-color: #f97316;
                border-radius: 9999px;
                animation: app-search-spin 0.7s linear infinite;
                pointer-events: none;
            }

            .dark .app-search-spinner {
                border-color: #4b5563;
                border-top-color: #fb923c;
            }

            @keyframes app-search-spin {
                to {
                    transform: rotate(360deg);
                }
            }
        </style>
    @endPushOnce
</x-admin::layouts>
