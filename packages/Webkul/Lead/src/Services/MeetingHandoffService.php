<?php

namespace Webkul\Lead\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Lead\Contracts\Lead as LeadContract;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\User\Contracts\User as UserContract;

class MeetingHandoffService
{
    public function __construct(
        protected SourceAccessService $sourceAccessService,
        protected LeadRepository $leadRepository,
        protected ActivityRepository $activityRepository,
    ) {}

    /**
     * Lead was handed off: original worker tracked on lead_owner_id, assignee changed.
     */
    public static function isHandoffLeadForUser(LeadContract $lead, UserContract $user): bool
    {
        return (int) ($lead->lead_owner_id ?? 0) === (int) $user->id
            && (int) ($lead->user_id ?? 0) !== (int) $user->id
            && (int) ($lead->user_id ?? 0) !== 0;
    }

    public function canCurrentUserEditStage(LeadContract $lead, ?UserContract $user = null): bool
    {
        $user = $user ?? auth()->guard('user')->user();

        if (! $user) {
            return false;
        }

        if ($user->role?->permission_type === 'all') {
            return true;
        }

        if ((int) $lead->user_id === (int) $user->id) {
            return true;
        }

        if (self::isHandoffLeadForUser($lead, $user)) {
            return false;
        }

        if (! $this->isCallingRoleUser($user)) {
            return false;
        }

        return $this->sourceAccessService->canViewLead($lead, $user);
    }

    public function canInitiateMeetingHandoff(LeadContract $lead, ?UserContract $user = null): bool
    {
        $user = $user ?? auth()->guard('user')->user();

        if (! $user || ! $this->isCallingRoleUser($user)) {
            return false;
        }

        if (self::isHandoffLeadForUser($lead, $user)) {
            return false;
        }

        return $this->sourceAccessService->canViewLead($lead, $user);
    }

    /**
     * Atomically create a meeting and move the lead to Meeting while handing off ownership.
     *
     * Authorization is evaluated against the pre-handoff lead state inside the transaction.
     */
    public function completeHandoff(
        UserContract $actor,
        int $leadId,
        int $stageId,
        int $assignedUserId,
        array $activityData,
    ): Lead {
        return DB::transaction(function () use ($actor, $leadId, $stageId, $assignedUserId, $activityData) {
            $lead = Lead::query()
                ->with(['pipeline.stages', 'stage'])
                ->lockForUpdate()
                ->findOrFail($leadId);

            $this->assertCanCompleteHandoff($lead, $actor, $stageId, $assignedUserId);

            Event::dispatch('activity.create.before');

            $activity = $this->activityRepository->create(array_merge($activityData, [
                'type'    => 'meeting',
                'user_id' => $assignedUserId,
            ]));

            if (! empty($activityData['lead_id'])) {
                $activity->leads()->sync([(int) $activityData['lead_id']]);
            }

            Event::dispatch('activity.create.after', $activity);

            $stage = $lead->pipeline->stages->firstWhere('id', $stageId);

            Event::dispatch('lead.update.before', $leadId);

            $payload = [
                'entity_type'            => 'leads',
                'lead_pipeline_stage_id' => $stage->id,
                'user_id'                => $assignedUserId,
            ];

            $attributes = ['lead_pipeline_stage_id', 'user_id'];

            if (empty($lead->lead_owner_id)) {
                $payload['lead_owner_id'] = $actor->id;
                $attributes[] = 'lead_owner_id';
            }

            $lead = $this->leadRepository->update(
                $payload,
                $leadId,
                array_values(array_unique($attributes)),
            );

            Event::dispatch('lead.update.after', $lead);

            return $lead->fresh(['pipeline.stages', 'stage']);
        });
    }

    public function isActiveMeetingOwnerId(int $userId): bool
    {
        return $this->eligibleOwnerBaseQuery()
            ->where('users.id', $userId)
            ->exists();
    }

    /**
     * @return array<int, array{id: int, name: string, email: string|null, role_name: string|null}>
     */
    public function getAllActiveMeetingOwners(): array
    {
        return $this->mapEligibleOwnerRows(
            $this->eligibleOwnerBaseQuery()
                ->orderBy('users.name')
                ->get(['users.id', 'users.name', 'users.email', 'roles.name as role_name'])
        );
    }

    /**
     * Eligible handoff owners for a lead. Falls back to all active owners when the lead has no services.
     *
     * @return array<int, array{id: int, name: string, email: string|null, role_name: string|null}>
     */
    public function getEligibleMeetingOwnersForLead(?LeadContract $lead = null): array
    {
        if (! $lead) {
            return $this->getAllActiveMeetingOwners();
        }

        $serviceIds = $this->getLeadServiceIds($lead);

        if (empty($serviceIds)) {
            return $this->getAllActiveMeetingOwners();
        }

        return $this->mapEligibleOwnerRows(
            $this->eligibleOwnerBaseQuery()
                ->join('service_user', 'service_user.user_id', '=', 'users.id')
                ->whereIn('service_user.service_id', $serviceIds)
                ->distinct()
                ->orderBy('users.name')
                ->get(['users.id', 'users.name', 'users.email', 'roles.name as role_name'])
        );
    }

    public function isEligibleMeetingOwnerForLead(LeadContract $lead, int $userId): bool
    {
        if (! $this->isActiveMeetingOwnerId($userId)) {
            return false;
        }

        $serviceIds = $this->getLeadServiceIds($lead);

        if (empty($serviceIds)) {
            return true;
        }

        return DB::table('service_user')
            ->where('user_id', $userId)
            ->whereIn('service_id', $serviceIds)
            ->exists();
    }

    /**
     * @return array<int>
     */
    public function getLeadServiceIds(LeadContract $lead): array
    {
        if ($lead->relationLoaded('services')) {
            return $lead->services
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->values()
                ->all();
        }

        return DB::table('lead_service')
            ->where('lead_id', $lead->id)
            ->pluck('service_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();
    }

    protected function eligibleOwnerBaseQuery(): Builder
    {
        return DB::table('users')
            ->join('roles', 'users.role_id', '=', 'roles.id')
            ->where('users.status', 1)
            ->where(function ($query) {
                $query->where('roles.permission_type', 'all')
                    ->orWhereIn(DB::raw('LOWER(roles.name)'), [
                        'lead',
                        'lead clouser',
                        'lead closer',
                        'lead closure',
                    ]);
            });
    }

    /**
     * @param  iterable<object>  $rows
     * @return array<int, array{id: int, name: string, email: string|null, role_name: string|null}>
     */
    protected function mapEligibleOwnerRows(iterable $rows): array
    {
        return collect($rows)
            ->map(fn ($user) => [
                'id'        => (int) $user->id,
                'name'      => $user->name,
                'email'     => $user->email,
                'role_name' => $user->role_name,
            ])
            ->values()
            ->all();
    }

    protected function assertCanCompleteHandoff(
        LeadContract $lead,
        UserContract $actor,
        int $stageId,
        int $assignedUserId,
    ): void {
        if (! $this->sourceAccessService->canViewLead($lead, $actor)) {
            throw new AuthorizationException(trans('admin::app.leads.source-access-denied'));
        }

        if (! $this->isCallingRoleUser($actor)) {
            throw new AuthorizationException(trans('admin::app.leads.source-access-denied'));
        }

        if (self::isHandoffLeadForUser($lead, $actor)) {
            throw new AuthorizationException(
                'You can view this lead, but stage changes are locked after meeting assignment.'
            );
        }

        if (! $this->canInitiateMeetingHandoff($lead, $actor)) {
            throw new AuthorizationException(
                'You cannot schedule a meeting handoff for this lead. It may be assigned to another user or no longer in your working queue.'
            );
        }

        $stage = $lead->pipeline?->stages?->firstWhere('id', $stageId);

        if (! $stage || $stage->code !== 'meeting') {
            throw ValidationException::withMessages([
                'lead_pipeline_stage_id' => ['The selected stage must be Meeting for this handoff.'],
            ]);
        }

        if ($this->stageIsBeyondMeeting($lead, $stage)) {
            throw new AuthorizationException('You can move SDR/LGE leads up to Meeting only.');
        }

        if (! $this->isEligibleMeetingOwnerForLead($lead, $assignedUserId)) {
            $message = empty($this->getLeadServiceIds($lead))
                ? 'Please select a valid Admin or Lead user.'
                : 'The selected owner is not assigned to handle this lead\'s services.';

            throw ValidationException::withMessages([
                'assigned_user_id' => [$message],
            ]);
        }
    }

    protected function stageIsBeyondMeeting(LeadContract $lead, $targetStage): bool
    {
        $meetingStage = $lead->pipeline?->stages?->firstWhere('code', 'meeting');

        if (! $meetingStage) {
            return false;
        }

        return (int) $targetStage->sort_order > (int) $meetingStage->sort_order;
    }

    protected function isCallingRoleUser(UserContract $user): bool
    {
        return $this->sourceAccessService->isSdrUser($user)
            || $this->sourceAccessService->isLgeUser($user);
    }
}
