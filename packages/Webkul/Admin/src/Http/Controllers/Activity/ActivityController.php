<?php

namespace Webkul\Admin\Http\Controllers\Activity;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Activity\Repositories\FileRepository;
use Webkul\Admin\DataGrids\Activity\ActivityDataGrid;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Requests\MassDestroyRequest;
use Webkul\Admin\Http\Requests\MassUpdateRequest;
use Webkul\Admin\Http\Resources\ActivityResource;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Email\Repositories\EmailRepository;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Lead\Services\FollowupScheduleService;

class ActivityController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected ActivityRepository $activityRepository,
        protected FileRepository $fileRepository,
        protected AttributeRepository $attributeRepository,
        protected LeadRepository $leadRepository,
        protected EmailRepository $emailRepository,
        protected FollowupScheduleService $followupScheduleService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        return view('admin::activities.index');
    }

    /**
     * Returns a listing of the resource.
     */
    public function get(): JsonResponse
    {
        if (! request()->has('view_type')) {
            return datagrid(ActivityDataGrid::class)->process();
        }

        $startDate = request()->get('startDate')
            ? Carbon::createFromTimeString(request()->get('startDate').' 00:00:01')
            : Carbon::now()->startOfWeek()->format('Y-m-d H:i:s');

        $endDate = request()->get('endDate')
            ? Carbon::createFromTimeString(request()->get('endDate').' 23:59:59')
            : Carbon::now()->endOfWeek()->format('Y-m-d H:i:s');

        $activities = $this->activityRepository->getActivities([$startDate, $endDate])->toArray();

        return response()->json([
            'activities' => $activities,
        ]);
    }

    /**
     * Returns due follow-up and meeting notifications for the bell.
     */
    public function notifications(): JsonResponse
    {
        $query = $this->dueFollowupNotificationQuery();

        $followupNotifications = $query
            ->with(['person'])
            ->orderBy('leads.next_followup_date')
            ->get()
            ->map(function ($lead) {
                $dueAt = Carbon::parse($lead->next_followup_date);
                $isUpcoming = $dueAt->greaterThan(Carbon::now());

                return [
                    'id'            => $lead->id,
                    'title'         => $isUpcoming
                        ? 'Follow-up in 15 min: '.$lead->title
                        : 'Follow-up: '.$lead->title,
                    'type'          => 'followup',
                    'comment'       => $lead->followup_notes,
                    'schedule_from' => $dueAt->toIso8601String(),
                    'schedule_to'   => null,
                    'lead_title'    => $lead->title,
                    'person_name'   => $lead->person?->name,
                    'edit_url'      => route('admin.leads.view', $lead->id),
                ];
            });

        $meetingNotifications = $this->dueActivityNotifications();

        $notifications = collect($followupNotifications->all())
            ->merge($meetingNotifications)
            ->sortBy('schedule_from')
            ->values();

        return response()->json([
            'count'                 => $notifications->count(),
            'unread_messages_count' => $this->unreadMessagesCount(),
            'notifications'         => $notifications,
        ]);
    }

    /**
     * Mark a due notification as done.
     */
    public function markNotificationAsDone(string $id): JsonResponse
    {
        DB::transaction(function () use ($id) {
            if (preg_match('/^(?:activity|meeting)-(before|start)-(\d+)$/', $id, $matches)) {
                $this->markActivityNotificationRead((int) $matches[2], $matches[1]);

                return;
            }

            $userId = auth()->guard('user')->id();

            $lead = $this->leadRepository
                ->getModel()
                ->newQuery()
                ->where('leads.id', (int) str_replace('followup-', '', $id))
                ->where('leads.user_id', $userId)
                ->lockForUpdate()
                ->firstOrFail();

            $this->completeFollowupNotification($lead);
        });

        return response()->json([
            'message' => trans('admin::app.activities.notifications.marked-done'),
        ]);
    }

    /**
     * Mark all due follow-up notifications as done.
     */
    public function markAllNotificationsAsDone(): JsonResponse
    {
        DB::transaction(function () {
            $this->dueFollowupNotificationQuery()
                ->lockForUpdate()
                ->get()
                ->each(fn ($lead) => $this->completeFollowupNotification($lead));

            $this->dueActivityNotifications()
                ->each(function ($notification) {
                    if (! preg_match('/^(?:activity|meeting)-(before|start)-(\d+)$/', $notification['id'], $matches)) {
                        return;
                    }

                    $this->markActivityNotificationRead((int) $matches[2], $matches[1]);
                });
        });

        return response()->json([
            'message' => trans('admin::app.activities.notifications.marked-all-done'),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(): RedirectResponse|JsonResponse
    {
        $this->ensureCurrentUserParticipant();

        if ($this->isCallingRoleStageMeetingRequest()) {
            $this->validate(request(), [
                'assigned_user_id' => [
                    'required',
                    'integer',
                    function ($attribute, $value, $fail) {
                        if (! $this->isActiveMeetingOwnerId((int) $value)) {
                            $fail('Please select a valid Admin or Lead user.');
                        }
                    },
                ],
            ]);
        }

        $this->validate(request(), [
            'type'          => 'required',
            'comment'       => ['required_if:type,note', 'required_if:stage_meeting,1'],
            'schedule_from' => 'required_unless:type,note,file',
            'schedule_to'   => 'required_unless:type,note,file|nullable|after:schedule_from',
            'location'      => 'required_if:stage_meeting,1',
            'file'          => 'required_if:type,file',
        ], [
            'schedule_to.after' => 'Schedule To must be later than Schedule From.',
        ]);

        if (request('stage_meeting') && ! $this->hasActivityParticipants(request('participants', []))) {
            return response()->json([
                'message' => 'Please select at least one participant.',
                'errors'  => [
                    'participants' => ['Please select at least one participant.'],
                ],
            ], 422);
        }

        if (request('type') === 'meeting') {
            /**
             * Check if meeting is overlapping with other meetings.
             */
            $isOverlapping = $this->activityRepository->isDurationOverlapping(
                request()->input('schedule_from'),
                request()->input('schedule_to'),
                request()->input('participants'),
                request()->input('id')
            );

            if ($isOverlapping) {
                if (request()->ajax()) {
                    return response()->json([
                        'message' => trans('admin::app.activities.overlapping-error'),
                    ], 400);
                }

                session()->flash('success', trans('admin::app.activities.overlapping-error'));

                return redirect()->back();
            }
        }

        Event::dispatch('activity.create.before');

        $data = $this->normalizeActivityStatusData(request()->all());

        $data = $this->prepareMeetingActivityData($data);
        $activityOwnerId = $this->activityOwnerIdForCreate();

        $activity = $this->activityRepository->create(array_merge($data, [
            'user_id' => $activityOwnerId,
        ]));

        if (isset($data['lead_id'])) {
            $activity->leads()->sync(
                ! empty($data['lead_id'])
                    ? [$data['lead_id']]
                    : []
            );
        }

        Event::dispatch('activity.create.after', $activity);

        if (request()->ajax()) {
            return response()->json([
                'data'    => new ActivityResource($activity),
                'message' => trans('admin::app.activities.create-success'),
            ]);
        }

        session()->flash('success', trans('admin::app.activities.create-success'));

        return redirect()->back();
    }

    /**
     * Base query for pending follow-up notifications shown in the header.
     * Includes follow-ups due within the next 15 minutes (and already due/overdue).
     * Always scoped to the authenticated user — not CRM view_permission.
     */
    protected function dueFollowupNotificationQuery()
    {
        $userId = auth()->guard('user')->id();

        return $this->leadRepository
            ->getModel()
            ->newQuery()
            ->select('leads.*')
            ->whereNotNull('leads.next_followup_date')
            ->where('leads.next_followup_date', '<=', Carbon::now()->addMinutes(15))
            ->where('leads.user_id', $userId);
    }

    /**
     * Activity reminders due in the current 15-minute notification window.
     * Covers scheduled CRM activities (call, meeting) for SDRs and other users.
     */
    protected function dueActivityNotifications()
    {
        $now = Carbon::now();
        $windowStart = $now->copy()->subMinutes(15);
        $windowEnd = $now->copy()->addMinutes(15);
        $currentUserId = auth()->guard('user')->id();

        $query = DB::table('activities')
            ->select(
                'activities.id',
                'activities.type',
                'activities.title',
                'activities.comment',
                'activities.location',
                'activities.user_id',
                'activities.schedule_from',
                'activities.schedule_to',
                'leads.title as lead_title',
                'persons.name as person_name',
            )
            ->leftJoin('lead_activities', 'lead_activities.activity_id', '=', 'activities.id')
            ->leftJoin('leads', 'leads.id', '=', 'lead_activities.lead_id')
            ->leftJoin('persons', 'persons.id', '=', 'leads.person_id')
            ->whereIn('activities.type', ['call', 'meeting'])
            ->where('activities.is_done', 0)
            ->whereNotNull('activities.schedule_from')
            ->whereBetween('activities.schedule_from', [$windowStart, $windowEnd])
            ->groupBy(
                'activities.id',
                'activities.type',
                'activities.title',
                'activities.comment',
                'activities.location',
                'activities.user_id',
                'activities.schedule_from',
                'activities.schedule_to',
                'leads.title',
                'persons.name',
            );

        $this->applyActivityNotificationAccessScope($query);

        $activities = $query
            ->orderBy('activities.schedule_from')
            ->get();

        if ($activities->isEmpty()) {
            return collect();
        }

        $readReminders = DB::table('activity_notification_reads')
            ->where('user_id', $currentUserId)
            ->whereIn('activity_id', $activities->pluck('id')->all())
            ->get(['activity_id', 'reminder_type'])
            ->groupBy('activity_id')
            ->map(fn ($rows) => $rows->pluck('reminder_type')->all());

        return $activities
            ->map(function ($activity) use ($now, $readReminders) {
                $startsAt = Carbon::parse($activity->schedule_from);
                $reminderType = $startsAt->greaterThan($now) ? 'before' : 'start';

                if (in_array($reminderType, $readReminders[$activity->id] ?? [], true)) {
                    return null;
                }

                $typeLabel = ucfirst($activity->type ?: 'activity');

                return [
                    'id'            => "activity-{$reminderType}-{$activity->id}",
                    'title'         => $reminderType === 'before'
                        ? "{$typeLabel} starts in 15 min: ".$activity->title
                        : "{$typeLabel} now: ".$activity->title,
                    'type'          => $activity->type ?: 'activity',
                    'comment'       => $activity->comment,
                    'schedule_from' => $startsAt->toIso8601String(),
                    'schedule_to'   => $activity->schedule_to ? Carbon::parse($activity->schedule_to)->toIso8601String() : null,
                    'lead_title'    => $activity->lead_title,
                    'person_name'   => $activity->person_name,
                    'edit_url'      => $this->canModifyActivity($activity) ? route('admin.activities.edit', $activity->id) : null,
                ];
            })
            ->filter()
            ->values();
    }

    protected function markActivityNotificationRead(int $activityId, string $reminderType): void
    {
        $query = DB::table('activities')
            ->where('activities.id', $activityId)
            ->whereIn('activities.type', ['call', 'meeting']);

        $this->applyActivityNotificationAccessScope($query);

        if (! $query->exists()) {
            abort(404);
        }

        DB::table('activity_notification_reads')->updateOrInsert(
            [
                'activity_id'    => $activityId,
                'user_id'        => auth()->guard('user')->id(),
                'reminder_type'  => $reminderType,
            ],
            [
                'read_at'    => Carbon::now(),
                'updated_at' => Carbon::now(),
                'created_at' => Carbon::now(),
            ]
        );
    }

    /**
     * Limit bell reminders to the authenticated user only
     * (activity owner or participant — not CRM global/group view permission).
     */
    protected function applyActivityNotificationAccessScope($query): void
    {
        $userId = auth()->guard('user')->id();

        $query->where(function ($query) use ($userId) {
            $query->where('activities.user_id', $userId)
                ->orWhereExists(function ($participantQuery) use ($userId) {
                    $participantQuery
                        ->select(DB::raw(1))
                        ->from('activity_participants')
                        ->whereColumn('activity_participants.activity_id', 'activities.id')
                        ->where('activity_participants.user_id', $userId);
                });
        });
    }

    /**
     * Complete a follow-up notification and schedule the next one if configured.
     */
    protected function completeFollowupNotification($lead): void
    {
        if (
            ! $lead->next_followup_date
            || Carbon::parse($lead->next_followup_date)->isFuture()
        ) {
            return;
        }

        Event::dispatch('lead.update.before', $lead->id);

        $completedAt = Carbon::now();

        $lead->newQuery()
            ->whereKey($lead->getKey())
            ->update([
                'followup_count'     => ($lead->followup_count ?? 0) + 1,
                'last_followup_date' => $completedAt,
            ]);

        $lead->refresh();

        $this->followupScheduleService->applyNextFollowup($lead);

        $lead->refresh();

        Event::dispatch('lead.update.after', $lead);
    }

    /**
     * Count unread inbox messages for the bell unread indicator.
     * Scoped to emails on the authenticated user's leads — not global CRM view permission.
     */
    protected function unreadMessagesCount(): int
    {
        $userId = auth()->guard('user')->id();

        return $this->emailRepository
            ->getModel()
            ->newQuery()
            ->where('is_read', 0)
            ->whereNull('parent_id')
            ->where('folders', 'like', '%"inbox"%')
            ->whereHas('lead', function ($leadQuery) use ($userId) {
                $leadQuery->where('user_id', $userId);
            })
            ->count();
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(int $id): View
    {
        $activity = $this->activityRepository->findOrFail($id);

        abort_unless($this->canModifyActivity($activity), 403);

        $leadId = old('lead_id') ?? optional($activity->leads()->first())->id;

        $lookUpEntityData = $this->attributeRepository->getLookUpEntity('leads', $leadId);

        return view('admin::activities.edit', compact('activity', 'lookUpEntityData'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update($id): RedirectResponse|JsonResponse
    {
        $existingActivity = $this->activityRepository->findOrFail($id);

        abort_unless($this->canModifyActivity($existingActivity), 403);

        if (request('activity_status') === 'meeting_scheduled') {
            $this->prepareMeetingScheduleRequest($existingActivity);
        }

        $this->ensureCurrentUserParticipant();

        $this->validateActivityStatusRequest();

        $moveLeadToMeetingStage = request('activity_status') === 'meeting_scheduled';
        $markLinkedLeadsEnded = request('activity_status') === 'done';

        Event::dispatch('activity.update.before', $id);

        $data = $this->normalizeActivityStatusData(request()->all());

        $activity = $this->activityRepository->update($data, $id);

        /**
         * We will not use `empty` directly here because `lead_id` can be a blank string
         * from the activity form. However, on the activity view page, we are only updating the
         * `is_done` field, so `lead_id` will not be present in that case.
         */
        if (isset($data['lead_id'])) {
            $activity->leads()->sync(
                ! empty($data['lead_id'])
                    ? [$data['lead_id']]
                    : []
            );
        }

        if ($moveLeadToMeetingStage) {
            $this->moveLinkedLeadsToMeetingStage($activity->refresh());
        }

        if ($markLinkedLeadsEnded) {
            $this->markLinkedLeadsEnded($activity->refresh(), trim((string) $activity->comment));
        }

        Event::dispatch('activity.update.after', $activity);

        if (request()->ajax()) {
            return response()->json([
                'data'    => new ActivityResource($activity),
                'message' => trans('admin::app.activities.update-success'),
            ]);
        }

        session()->flash('success', trans('admin::app.activities.update-success'));

        return redirect()->route('admin.activities.index');
    }

    /**
     * Mass Update the specified resources.
     */
    public function massUpdate(MassUpdateRequest $massUpdateRequest): JsonResponse
    {
        $activities = $this->activityRepository->findWhereIn('id', $massUpdateRequest->input('indices'));

        foreach ($activities as $activity) {
            if (! $this->canModifyActivity($activity)) {
                abort(403);
            }

            Event::dispatch('activity.update.before', $activity->id);

            $activity = $this->activityRepository->update([
                'is_done'                => $massUpdateRequest->input('value'),
                'call_status'            => $massUpdateRequest->input('value') ? 'done' : 'scheduled',
                'call_status_updated_at' => now(),
            ], $activity->id);

            Event::dispatch('activity.update.after', $activity);
        }

        return response()->json([
            'message' => trans('admin::app.activities.mass-update-success'),
        ]);
    }

    /**
     * Normalize the edit form status dropdown into the legacy done flag.
     */
    protected function normalizeActivityStatusData(array $data): array
    {
        $status = $data['activity_status'] ?? null;
        $endLeadComment = trim($data['end_lead_comment'] ?? '');

        unset($data['activity_status'], $data['end_lead_comment']);

        if ($status === 'meeting_scheduled') {
            $data['type'] = 'meeting';
            $status = 'scheduled';
        }

        if ($status === 'done') {
            $data['comment'] = $endLeadComment !== ''
                ? $endLeadComment
                : trim($data['comment'] ?? '');
        }

        if (! in_array($status, ['scheduled', 'not_answered', 'done'], true)) {
            if (($data['type'] ?? null) === 'note') {
                $status = 'done';
            } elseif ((int) ($data['is_done'] ?? 0) === 1) {
                $status = 'done';
            } else {
                $status = $data['call_status'] ?? 'scheduled';
            }
        }

        $data['call_status'] = $status;
        $data['is_done'] = $status === 'done' ? 1 : 0;
        $data['call_status_updated_at'] = now();

        return $data;
    }

    /**
     * Validate status-specific fields from the activity edit form.
     */
    protected function validateActivityStatusRequest(): void
    {
        $status = request('activity_status');

        if ($status === 'done') {
            request()->validate([
                'comment'          => ['required_without:end_lead_comment', 'string', 'max:1000'],
                'end_lead_comment' => ['nullable', 'string', 'max:1000'],
            ], [
                'comment.required_without' => 'Please add a comment before ending the lead.',
            ]);
        }

        if ($status === 'meeting_scheduled') {
            request()->validate([
                'schedule_from' => ['required'],
                'schedule_to'   => ['required', 'after:schedule_from'],
                'lead_id'       => ['required'],
                'comment'       => ['required', 'string', 'max:1000'],
                'location'      => ['required', 'string', 'max:255'],
            ], [
                'comment.required'      => 'Please add meeting details before scheduling the meeting.',
                'schedule_to.after'     => 'Schedule To must be later than Schedule From.',
            ]);

            if (! $this->hasActivityParticipants(request('participants', []))) {
                throw ValidationException::withMessages([
                    'participants' => ['Please select at least one participant.'],
                ]);
            }
        }
    }

    /**
     * Keep meeting conversion reliable when the edit screen submits an empty lookup component.
     */
    protected function prepareMeetingScheduleRequest($activity): void
    {
        $participants = (array) request('participants', []);

        if (! $this->hasActivityParticipants($participants)) {
            $participantUserId = $activity->user_id ?: auth()->guard('user')->id();

            if ($participantUserId) {
                $participants['users'] = [$participantUserId];
            }
        }

        if (! request()->filled('lead_id')) {
            $leadId = $activity->leads()->value('leads.id');

            if ($leadId) {
                request()->merge(['lead_id' => $leadId]);
            }
        }

        request()->merge([
            'participants' => $participants,
            'type'         => 'meeting',
        ]);

        $this->ensureCurrentUserParticipant();
    }

    /**
     * Move linked leads into the Meeting stage after a call becomes a meeting.
     */
    protected function moveLinkedLeadsToMeetingStage($activity): void
    {
        $activity->loadMissing(['leads.pipeline.stages', 'leads.stage']);

        foreach ($activity->leads as $lead) {
            $meetingStage = $lead->pipeline?->stages
                ->firstWhere('code', 'meeting');

            if (
                ! $meetingStage
                || (int) $lead->lead_pipeline_stage_id === (int) $meetingStage->id
            ) {
                continue;
            }

            Event::dispatch('lead.update.before', $lead->id);

            $updatedLead = $this->leadRepository->update([
                'entity_type'            => 'leads',
                'lead_pipeline_stage_id' => $meetingStage->id,
            ], $lead->id, ['lead_pipeline_stage_id']);

            Event::dispatch('lead.update.after', $updatedLead);
        }
    }

    /**
     * Move linked leads out of SDR queues for admin review after an SDR ends them.
     */
    protected function markLinkedLeadsEnded($activity, string $comment): void
    {
        if ($comment === '') {
            return;
        }

        $activity->loadMissing('leads');

        foreach ($activity->leads as $lead) {
            Event::dispatch('lead.update.before', $lead->id);

            $updatedLead = $this->leadRepository->update([
                'entity_type'                  => 'leads',
                'lead_disqualification_reason' => 'ended',
                'lead_disqualification_comment'=> $comment,
                'lead_disqualified_at'         => Carbon::now(),
                'next_followup_date'           => null,
                'followup_notes'               => $comment,
            ], $lead->id);

            Event::dispatch('lead.update.after', $updatedLead);
        }
    }

    /**
     * Fill backend-only meeting fields for stage movement flow.
     */
    protected function prepareMeetingActivityData(array $data): array
    {
        if (($data['type'] ?? null) !== 'meeting' || ! empty($data['title'])) {
            return $data;
        }

        $lead = ! empty($data['lead_id'])
            ? $this->leadRepository->find($data['lead_id'])
            : null;

        $data['title'] = 'Meeting'.($lead?->title ? ' - '.$lead->title : '');

        return $data;
    }

    /**
     * Check if at least one user or person participant was selected.
     */
    protected function hasActivityParticipants(array $participants): bool
    {
        foreach (['users', 'persons'] as $type) {
            foreach ($participants[$type] ?? [] as $participantId) {
                if (! empty($participantId)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Creator must always be part of a meeting so it appears on their side too.
     */
    protected function ensureCurrentUserParticipant(): void
    {
        if (! in_array(request('type'), ['meeting'], true) && ! request('stage_meeting')) {
            return;
        }

        if ($this->isCallingRoleStageMeetingRequest()) {
            return;
        }

        $participants = (array) request('participants', []);
        $participants['users'] = array_values(array_unique(array_filter([
            ...(array) ($participants['users'] ?? []),
            auth()->guard('user')->id(),
        ])));

        request()->merge(['participants' => $participants]);
    }

    protected function isCallingRoleStageMeetingRequest(): bool
    {
        $sourceAccessService = app(\Webkul\Lead\Services\SourceAccessService::class);

        return request('stage_meeting')
            && (
                $sourceAccessService->isSdrUser()
                || $sourceAccessService->isLgeUser()
            );
    }

    protected function activityOwnerIdForCreate(): int
    {
        if ($this->isCallingRoleStageMeetingRequest()) {
            return (int) request('assigned_user_id');
        }

        return (int) auth()->guard('user')->id();
    }

    protected function isActiveMeetingOwnerId(int $userId): bool
    {
        return DB::table('users')
            ->join('roles', 'users.role_id', '=', 'roles.id')
            ->where('users.id', $userId)
            ->where('users.status', 1)
            ->where(function ($query) {
                $query->where('roles.permission_type', 'all')
                    ->orWhereIn(DB::raw('LOWER(roles.name)'), [
                        'lead',
                        'lead clouser',
                        'lead closer',
                        'lead closure',
                    ]);
            })
            ->exists();
    }

    protected function canModifyActivity($activity): bool
    {
        $user = auth()->guard('user')->user();

        if (! $user) {
            return false;
        }

        if ($user->role?->permission_type === 'all') {
            return true;
        }

        return (int) $activity->user_id === (int) $user->id;
    }

    /**
     * Download file from storage.
     */
    public function download(int $id): StreamedResponse
    {
        try {
            $file = $this->fileRepository->findOrFail($id);

            return Storage::download($file->path);
        } catch (\Exception $exception) {
            abort(404);
        }
    }

    /*
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $activity = $this->activityRepository->findOrFail($id);

        abort_unless($this->canModifyActivity($activity), 403);

        try {
            Event::dispatch('activity.delete.before', $id);

            $activity?->delete($id);

            Event::dispatch('activity.delete.after', $id);

            return response()->json([
                'message' => trans('admin::app.activities.destroy-success'),
            ], 200);
        } catch (\Exception $exception) {
            return response()->json([
                'message' => trans('admin::app.activities.destroy-failed'),
            ], 400);
        }
    }

    /**
     * Mass Delete the specified resources.
     */
    public function massDestroy(MassDestroyRequest $massDestroyRequest): JsonResponse
    {
        $activities = $this->activityRepository->findWhereIn('id', $massDestroyRequest->input('indices'));

        try {
            foreach ($activities as $activity) {
                if (! $this->canModifyActivity($activity)) {
                    abort(403);
                }

                Event::dispatch('activity.delete.before', $activity->id);

                $this->activityRepository->delete($activity->id);

                Event::dispatch('activity.delete.after', $activity->id);
            }

            return response()->json([
                'message' => trans('admin::app.activities.mass-destroy-success'),
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                'message' => trans('admin::app.activities.mass-delete-failed'),
            ], 400);
        }
    }
}
