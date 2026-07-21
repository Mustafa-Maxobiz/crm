<?php

namespace Webkul\Lead\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Webkul\Lead\Contracts\Lead as LeadContract;
use Webkul\User\Contracts\User as UserContract;

class SourceAccessService
{
    /**
     * Root source IDs the user may use. Null means all sources.
     *
     * @return array<int>|null
     */
    public function getEffectiveRootSourceIds(?UserContract $user = null): ?array
    {
        if ($this->isAdmin($user)) {
            return null;
        }

        $user = $this->resolveUser($user);

        if (! $user) {
            return [];
        }

        $user->loadMissing(['sources', 'role.sources']);

        $userSourceIds = $user->sources->pluck('id')->all();
        $roleSourceIds = $user->role?->sources->pluck('id')->all() ?? [];

        if (! empty($userSourceIds)) {
            if (! empty($roleSourceIds)) {
                return array_values(array_intersect($userSourceIds, $roleSourceIds));
            }

            return array_values(array_unique($userSourceIds));
        }

        if (! empty($roleSourceIds)) {
            return $roleSourceIds;
        }

        return null;
    }

    /**
     * Organization IDs the user may use. Null means all companies.
     *
     * @return array<int>|null
     */
    public function getEffectiveOrganizationIds(?UserContract $user = null): ?array
    {
        if ($this->isAdmin($user)) {
            return null;
        }

        $user = $this->resolveUser($user);

        if (! $user) {
            return [];
        }

        $user->loadMissing(['organizations', 'role.organizations']);

        $userOrganizationIds = $user->organizations->pluck('id')->all();
        $roleOrganizationIds = $user->role?->organizations->pluck('id')->all() ?? [];

        if (! empty($userOrganizationIds)) {
            if (! empty($roleOrganizationIds)) {
                return array_values(array_intersect($userOrganizationIds, $roleOrganizationIds));
            }

            return array_values(array_unique($userOrganizationIds));
        }

        if (! empty($roleOrganizationIds)) {
            return $roleOrganizationIds;
        }

        return null;
    }

    /**
     * Root + sub-source IDs for filtering leads and validating assignments.
     *
     * @return array<int>|null
     */
    public function getExpandedSourceIds(?UserContract $user = null): ?array
    {
        $rootIds = $this->getEffectiveRootSourceIds($user);

        if ($rootIds === null) {
            return null;
        }

        if (empty($rootIds)) {
            return [];
        }

        $childIds = DB::table('lead_source_parents')
            ->whereIn('parent_source_id', $rootIds)
            ->pluck('source_id')
            ->all();

        return array_values(array_unique(array_merge($rootIds, $childIds)));
    }

    public function isAdmin(?UserContract $user = null): bool
    {
        $user = $this->resolveUser($user);

        return $user && $user->role?->permission_type === 'all';
    }

    public function canAccessSourceId(int $sourceId, ?UserContract $user = null): bool
    {
        $allowed = $this->getExpandedSourceIds($user);

        if ($allowed === null) {
            return true;
        }

        return in_array($sourceId, $allowed, true);
    }

    public function canAccessOrganizationId(int $organizationId, ?UserContract $user = null): bool
    {
        $allowed = $this->getEffectiveOrganizationIds($user);

        if ($allowed === null) {
            return true;
        }

        return in_array($organizationId, $allowed, true);
    }

    public function canAccessLead(LeadContract $lead, ?UserContract $user = null): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        if (! $this->leadMatchesSourceScope($lead, $user)) {
            return false;
        }

        return $this->leadMatchesOrganizationScope($lead, $user);
    }

    public function applyLeadQueryScope(Builder $query): Builder
    {
        $query = $this->applySourceQueryScope($query);

        return $this->applyOrganizationQueryScope($query);
    }

    public function applyLeadTableScope(QueryBuilder $query): QueryBuilder
    {
        $query = $this->applySourceTableScope($query);

        return $this->applyOrganizationTableScope($query);
    }

    /**
     * @param  array<int|string>  $sourceIds
     * @return array<int>
     */
    public function filterUserSourceIdsForRole(int $roleId, array $sourceIds): array
    {
        return $this->filterUserIdsForRole($roleId, $sourceIds, 'role_source', 'lead_source_id');
    }

    /**
     * @param  array<int|string>  $organizationIds
     * @return array<int>
     */
    public function filterUserOrganizationIdsForRole(int $roleId, array $organizationIds): array
    {
        return $this->filterUserIdsForRole($roleId, $organizationIds, 'role_organization', 'organization_id');
    }

    /**
     * @param  array<int|string>  $sourceIds
     */
    public function userSourcesValidForRole(int $roleId, array $sourceIds): bool
    {
        return $this->userIdsValidForRole($roleId, $sourceIds, 'role_source', 'lead_source_id');
    }

    /**
     * @param  array<int|string>  $organizationIds
     */
    public function userOrganizationsValidForRole(int $roleId, array $organizationIds): bool
    {
        return $this->userIdsValidForRole($roleId, $organizationIds, 'role_organization', 'organization_id');
    }

    protected function leadMatchesSourceScope(LeadContract $lead, ?UserContract $user = null): bool
    {
        $allowed = $this->getExpandedSourceIds($user);

        if ($allowed === null) {
            return true;
        }

        if (empty($allowed)) {
            return false;
        }

        $leadAttributes = $lead->getAttributes();

        $leadSourceId = $leadAttributes['lead_source_id'] ?? null;
        $leadSubSourceId = $leadAttributes['lead_sub_source_id'] ?? null;

        if ($leadSourceId && in_array((int) $leadSourceId, $allowed, true)) {
            return true;
        }

        if ($leadSubSourceId && in_array((int) $leadSubSourceId, $allowed, true)) {
            return true;
        }

        return ! $leadSourceId && ! $leadSubSourceId;
    }

    protected function leadMatchesOrganizationScope(LeadContract $lead, ?UserContract $user = null): bool
    {
        $allowed = $this->getEffectiveOrganizationIds($user);

        if ($allowed === null) {
            return true;
        }

        if (empty($allowed)) {
            return false;
        }

        if (! ($lead->getAttributes()['person_id'] ?? null)) {
            return false;
        }

        $lead->loadMissing('person');

        $organizationId = $lead->person?->getAttributes()['organization_id'] ?? null;

        if (! $organizationId) {
            return false;
        }

        return in_array((int) $organizationId, $allowed, true);
    }

    protected function applySourceQueryScope(Builder $query): Builder
    {
        $allowed = $this->getExpandedSourceIds();

        if ($allowed === null) {
            return $query;
        }

        if (empty($allowed)) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where(function ($scopeQuery) use ($allowed) {
            $scopeQuery->whereIn('lead_source_id', $allowed)
                ->orWhereIn('lead_sub_source_id', $allowed)
                ->orWhere(function ($emptySourceQuery) {
                    $emptySourceQuery->whereNull('lead_source_id')
                        ->whereNull('lead_sub_source_id');
                });
        });
    }

    protected function applyOrganizationQueryScope(Builder $query): Builder
    {
        $allowed = $this->getEffectiveOrganizationIds();

        if ($allowed === null) {
            return $query;
        }

        if (empty($allowed)) {
            return $query->whereRaw('0 = 1');
        }

        return $query->whereHas('person', fn ($personQuery) => $personQuery->whereIn('organization_id', $allowed));
    }

    protected function applySourceTableScope(QueryBuilder $query): QueryBuilder
    {
        $allowed = $this->getExpandedSourceIds();

        if ($allowed === null) {
            return $query;
        }

        if (empty($allowed)) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where(function ($scopeQuery) use ($allowed) {
            $scopeQuery->whereIn('leads.lead_source_id', $allowed)
                ->orWhereIn('leads.lead_sub_source_id', $allowed)
                ->orWhere(function ($emptySourceQuery) {
                    $emptySourceQuery->whereNull('leads.lead_source_id')
                        ->whereNull('leads.lead_sub_source_id');
                });
        });
    }

    protected function applyOrganizationTableScope(QueryBuilder $query): QueryBuilder
    {
        $allowed = $this->getEffectiveOrganizationIds();

        if ($allowed === null) {
            return $query;
        }

        if (empty($allowed)) {
            return $query->whereRaw('0 = 1');
        }

        return $query->whereIn('persons.organization_id', $allowed);
    }

  /**
   * @param  array<int|string>  $ids
   * @return array<int>
   */
    protected function filterUserIdsForRole(int $roleId, array $ids, string $table, string $column): array
    {
        $ids = collect($ids)
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($ids)) {
            return [];
        }

        $roleIds = DB::table($table)
            ->where('role_id', $roleId)
            ->pluck($column)
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($roleIds)) {
            return $ids;
        }

        return array_values(array_intersect($ids, $roleIds));
    }

    /**
     * @param  array<int|string>  $ids
     */
    protected function userIdsValidForRole(int $roleId, array $ids, string $table, string $column): bool
    {
        $ids = collect($ids)
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($ids)) {
            return true;
        }

        $roleIds = DB::table($table)
            ->where('role_id', $roleId)
            ->pluck($column)
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($roleIds)) {
            return true;
        }

        return empty(array_diff($ids, $roleIds));
    }

    protected function resolveUser(?UserContract $user = null): ?UserContract
    {
        return $user ?? auth()->guard('user')->user();
    }
}
