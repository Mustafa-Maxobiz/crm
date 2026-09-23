<?php

namespace Webkul\Lead\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Webkul\Lead\Models\Lead;
use Webkul\User\Contracts\User as UserContract;
use Webkul\User\Services\ActiveRoleService;

class SalesOwnerTransferService
{
    public const TYPE_SALES_OWNER_TRANSFER = 'sales_owner_transfer';

    public function __construct(
        protected SourceAccessService $sourceAccessService,
        protected ActiveRoleService $activeRoleService,
    ) {}

    public function eligibleOwnersForLead(Lead $lead, ?UserContract $actor = null): Collection
    {
        $actor = $actor ?: auth()->guard('user')->user();

        if (! $actor || ! $this->sourceAccessService->canEditLead($lead, $actor)) {
            return collect();
        }

        $allowedRoleNames = $this->allowedTargetRoleNames($actor);

        if ($allowedRoleNames !== null && empty($allowedRoleNames)) {
            return collect();
        }

        return $this->usersWithRoles(
            $allowedRoleNames,
            (int) $lead->user_id,
            true
        );
    }

    public function transfer(Lead $lead, int $newOwnerId, ?UserContract $actor = null): Lead
    {
        $actor = $actor ?: auth()->guard('user')->user();

        if (! $actor || ! $this->sourceAccessService->canEditLead($lead, $actor)) {
            throw new AuthorizationException('You do not have permission to change the sales owner of this lead.');
        }

        if ((int) $lead->user_id === $newOwnerId) {
            throw ValidationException::withMessages([
                'user_id' => ['Please select a different sales owner.'],
            ]);
        }

        $target = $this->eligibleOwnersForLead($lead, $actor)->firstWhere('id', $newOwnerId);

        if (! $target) {
            throw ValidationException::withMessages([
                'user_id' => ['The selected user is not an eligible sales owner for this transfer.'],
            ]);
        }

        return DB::transaction(function () use ($lead, $newOwnerId, $target, $actor) {
            $lockedLead = Lead::query()->lockForUpdate()->findOrFail($lead->id);

            if (! $this->sourceAccessService->canEditLead($lockedLead, $actor)) {
                throw new AuthorizationException('You do not have permission to change the sales owner of this lead.');
            }

            if ((int) $lockedLead->user_id === $newOwnerId) {
                return $lockedLead;
            }

            $oldOwnerId = $lockedLead->user_id ? (int) $lockedLead->user_id : null;
            $oldOwnerLabel = $this->ownerLabel($oldOwnerId);
            $newOwnerLabel = $this->formatOwnerLabel($target);

            $lockedLead->forceFill([
                'user_id'       => $newOwnerId,
                'lead_owner_id' => $newOwnerId,
            ])->saveQuietly();

            Lead::storeSystemActivityWithLabels(
                $lockedLead,
                'Sales Owner',
                $oldOwnerId,
                $oldOwnerLabel,
                $newOwnerId,
                $newOwnerLabel
            );

            if ($oldOwnerId) {
                $now = now();

                $payload = [
                    'lead_id'      => $lockedLead->id,
                    'from_user_id' => $oldOwnerId,
                    'to_user_id'   => $newOwnerId,
                    'forward_type' => self::TYPE_SALES_OWNER_TRANSFER,
                    'forwarded_at' => $now,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ];

                if (Schema::hasColumn('lead_forwards', 'forwarded_by')) {
                    $payload['forwarded_by'] = $actor->id;
                }

                DB::table('lead_forwards')->insert($payload);
            }

            return $lockedLead->refresh();
        });
    }

    public function ownerLabel(?int $userId): string
    {
        $user = $this->ownerOption($userId);

        return $user ? $this->formatOwnerLabel($user) : '--';
    }

    public function ownerOption(?int $userId): ?object
    {
        if (! $userId) {
            return null;
        }

        return $this->usersWithRoles(null, null, false)->firstWhere('id', $userId);
    }

    public function ownerRoleName(?int $userId): string
    {
        if (! $userId) {
            return '';
        }

        return (string) ($this->usersWithRoles(null, null, false)->firstWhere('id', $userId)?->role_name ?? '');
    }

    public function isClosureUserId(int $userId): bool
    {
        if (! $userId) {
            return false;
        }

        $roleNames = $this->closureRoleNames();

        if (Schema::hasTable('user_roles')) {
            $hasPivotRole = DB::table('users')
                ->join('user_roles', 'users.id', '=', 'user_roles.user_id')
                ->join('roles', 'user_roles.role_id', '=', 'roles.id')
                ->where('users.id', $userId)
                ->whereIn(DB::raw('LOWER(TRIM(roles.name))'), $roleNames)
                ->exists();

            if ($hasPivotRole) {
                return true;
            }
        }

        return DB::table('users')
            ->join('roles', 'users.role_id', '=', 'roles.id')
            ->where('users.id', $userId)
            ->whereIn(DB::raw('LOWER(TRIM(roles.name))'), $roleNames)
            ->exists();
    }

    public function formatOwnerLabel(object $user): string
    {
        $roleName = trim((string) ($user->role_name ?? ''));

        return trim((string) $user->name).($roleName !== '' ? ' — '.$roleName : '');
    }

    /**
     * @return array<int, string>|null Null means unrestricted active users with a role.
     */
    protected function allowedTargetRoleNames(UserContract $actor): ?array
    {
        return null;
    }

    protected function hasUnrestrictedLeadManagement(UserContract $actor): bool
    {
        if ($this->sourceAccessService->isAdmin($actor)) {
            return true;
        }

        if (
            $this->sourceAccessService->isBdeUser($actor)
            || $this->sourceAccessService->isSdrUser($actor)
            || $this->sourceAccessService->isLgeUser($actor)
            || $this->sourceAccessService->isLeadCloserUser($actor)
        ) {
            return false;
        }

        return function_exists('bouncer') && bouncer()->hasPermission('leads.edit');
    }

    /**
     * @return array<int, string>
     */
    protected function closureRoleNames(): array
    {
        return [
            'lead',
            'lead closer',
            'lead clouser',
            'lead closure',
            'closure',
            'closer',
        ];
    }

    /**
     * @param  array<int, string>|null  $allowedRoleNames
     */
    protected function usersWithRoles(
        ?array $allowedRoleNames,
        ?int $excludeUserId = null,
        bool $activeOnly = true,
    ): Collection
    {
        if (Schema::hasTable('user_roles')) {
            $pivotUsers = $this->applyUserRoleQueryFilters(
                DB::table('users')
                ->join('user_roles', 'users.id', '=', 'user_roles.user_id')
                    ->join('roles', 'user_roles.role_id', '=', 'roles.id')
                    ->leftJoin('working_hours', 'working_hours.user_id', '=', 'users.id'),
                $allowedRoleNames,
                $excludeUserId,
                $activeOnly
            )->get([
                'users.id',
                'users.name',
                'users.email',
                'roles.id as role_id',
                'roles.name as role_name',
                'working_hours.start_time as working_hour_start_time',
                'working_hours.end_time as working_hour_end_time',
            ]);

            $primaryRoleUsers = $this->applyUserRoleQueryFilters(
                DB::table('users')
                    ->join('roles', 'users.role_id', '=', 'roles.id')
                    ->leftJoin('working_hours', 'working_hours.user_id', '=', 'users.id'),
                $allowedRoleNames,
                $excludeUserId,
                $activeOnly
            )->get([
                'users.id',
                'users.name',
                'users.email',
                'roles.id as role_id',
                'roles.name as role_name',
                'working_hours.start_time as working_hour_start_time',
                'working_hours.end_time as working_hour_end_time',
            ]);

            return $pivotUsers
                ->merge($primaryRoleUsers)
                ->sortBy(fn ($user) => strtolower($user->name.' '.$user->role_name))
                ->unique('id')
                ->values();
        } else {
            $query = $this->applyUserRoleQueryFilters(
                DB::table('users')
                    ->join('roles', 'users.role_id', '=', 'roles.id')
                    ->leftJoin('working_hours', 'working_hours.user_id', '=', 'users.id'),
                $allowedRoleNames,
                $excludeUserId,
                $activeOnly
            );

            return $query
                ->orderBy('users.name')
                ->orderBy('roles.name')
                ->get([
                    'users.id',
                    'users.name',
                    'users.email',
                    'roles.id as role_id',
                    'roles.name as role_name',
                    'working_hours.start_time as working_hour_start_time',
                    'working_hours.end_time as working_hour_end_time',
                ])
                ->unique('id')
                ->values();
        }
    }

    protected function applyUserRoleQueryFilters(
        \Illuminate\Database\Query\Builder $query,
        ?array $allowedRoleNames,
        ?int $excludeUserId,
        bool $activeOnly,
    ): \Illuminate\Database\Query\Builder {
        if ($activeOnly) {
            $query->where('users.status', 1);
        }

        if ($excludeUserId) {
            $query->where('users.id', '!=', $excludeUserId);
        }

        if ($allowedRoleNames !== null) {
            $query->whereIn(DB::raw('LOWER(TRIM(roles.name))'), $allowedRoleNames);
        }

        return $query;
    }

    protected function activeRoleName(UserContract $actor): string
    {
        return strtolower(trim((string) $this->activeRoleService->getActiveRole($actor)?->name));
    }

    /**
     * @return array<int, string>
     */
    protected function bdeRoleNames(): array
    {
        return ['bd', 'bde', 'bd executive', 'bd exective', 'bde executive', 'bde exective', 'business development executive'];
    }

    protected function isBdeRoleName(string $roleName): bool
    {
        return in_array($roleName, $this->bdeRoleNames(), true);
    }

}
