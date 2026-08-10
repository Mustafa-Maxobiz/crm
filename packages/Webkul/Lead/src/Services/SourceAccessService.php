<?php

namespace Webkul\Lead\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Webkul\Lead\Contracts\Lead as LeadContract;
use Webkul\User\Contracts\User as UserContract;

class SourceAccessService
{
    protected array $effectiveSourceIdsCache = [];

    protected array $effectiveRootSourceIdsCache = [];

    protected array $effectiveOrganizationIdsCache = [];

    protected array $expandedSourceIdsCache = [];

    protected ?array $newStageIdsCache = null;

    protected array $accessibleStageIdsCache = [];

    protected array $sharedStageIdsCache = [];

    /**
     * Source IDs assigned directly to the user/role. Null means all sources.
     *
     * @return array<int>|null
     */
    public function getEffectiveSourceIds(?UserContract $user = null): ?array
    {
        $user = $this->resolveUser($user);
        $cacheKey = $this->userCacheKey($user);

        if (array_key_exists($cacheKey, $this->effectiveSourceIdsCache)) {
            return $this->effectiveSourceIdsCache[$cacheKey];
        }

        if ($this->isAdmin($user)) {
            return $this->effectiveSourceIdsCache[$cacheKey] = null;
        }

        if (! $user) {
            return $this->effectiveSourceIdsCache[$cacheKey] = [];
        }

        $user->loadMissing(['sources', 'role.sources']);

        $userSourceIds = $user->sources->pluck('id')->map(fn ($id) => (int) $id)->all();
        $roleSourceIds = $user->role?->sources->pluck('id')->map(fn ($id) => (int) $id)->all() ?? [];

        if (! empty($userSourceIds)) {
            if (! empty($roleSourceIds)) {
                return $this->effectiveSourceIdsCache[$cacheKey] = array_values(array_intersect($userSourceIds, $roleSourceIds));
            }

            return $this->effectiveSourceIdsCache[$cacheKey] = array_values(array_unique($userSourceIds));
        }

        if (! empty($roleSourceIds)) {
            return $this->effectiveSourceIdsCache[$cacheKey] = $roleSourceIds;
        }

        return $this->effectiveSourceIdsCache[$cacheKey] = null;
    }

    /**
     * Root source IDs that should be visible in forms. If a user is assigned a
     * sub-source directly, its parent source must still be visible so the
     * sub-source dropdown can be reached.
     *
     * @return array<int>|null
     */
    public function getEffectiveRootSourceIds(?UserContract $user = null): ?array
    {
        $user = $this->resolveUser($user);
        $cacheKey = $this->userCacheKey($user);

        if (array_key_exists($cacheKey, $this->effectiveRootSourceIdsCache)) {
            return $this->effectiveRootSourceIdsCache[$cacheKey];
        }

        $sourceIds = $this->getEffectiveSourceIds($user);

        if ($sourceIds === null) {
            return $this->effectiveRootSourceIdsCache[$cacheKey] = null;
        }

        if (empty($sourceIds)) {
            return $this->effectiveRootSourceIdsCache[$cacheKey] = [];
        }

        $childIds = DB::table('lead_source_parents')
            ->whereIn('source_id', $sourceIds)
            ->pluck('source_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $legacyChildIds = DB::table('lead_sources')
            ->whereIn('id', $sourceIds)
            ->whereNotNull('parent_id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $parentIds = DB::table('lead_source_parents')
            ->whereIn('source_id', $sourceIds)
            ->pluck('parent_source_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $legacyParentIds = DB::table('lead_sources')
            ->whereIn('id', $sourceIds)
            ->whereNotNull('parent_id')
            ->pluck('parent_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $rootIds = array_diff($sourceIds, array_merge($childIds, $legacyChildIds));

        return $this->effectiveRootSourceIdsCache[$cacheKey] = array_values(array_unique(array_merge($rootIds, $parentIds, $legacyParentIds)));
    }

    /**
     * Organization IDs the user may use. Null means all companies.
     *
     * @return array<int>|null
     */
    public function getEffectiveOrganizationIds(?UserContract $user = null): ?array
    {
        $user = $this->resolveUser($user);
        $cacheKey = $this->userCacheKey($user);

        if (array_key_exists($cacheKey, $this->effectiveOrganizationIdsCache)) {
            return $this->effectiveOrganizationIdsCache[$cacheKey];
        }

        if ($this->isAdmin($user)) {
            return $this->effectiveOrganizationIdsCache[$cacheKey] = null;
        }

        if (! $user) {
            return $this->effectiveOrganizationIdsCache[$cacheKey] = [];
        }

        $user->loadMissing(['organizations', 'role.organizations']);

        $userOrganizationIds = $user->organizations->pluck('id')->all();
        $roleOrganizationIds = $user->role?->organizations->pluck('id')->all() ?? [];

        if (! empty($userOrganizationIds)) {
            if (! empty($roleOrganizationIds)) {
                return $this->effectiveOrganizationIdsCache[$cacheKey] = array_values(array_intersect($userOrganizationIds, $roleOrganizationIds));
            }

            return $this->effectiveOrganizationIdsCache[$cacheKey] = array_values(array_unique($userOrganizationIds));
        }

        if (! empty($roleOrganizationIds)) {
            return $this->effectiveOrganizationIdsCache[$cacheKey] = $roleOrganizationIds;
        }

        return $this->effectiveOrganizationIdsCache[$cacheKey] = null;
    }

    /**
     * Root + sub-source IDs for filtering leads and validating assignments.
     *
     * @return array<int>|null
     */
    public function getExpandedSourceIds(?UserContract $user = null): ?array
    {
        $user = $this->resolveUser($user);
        $cacheKey = $this->userCacheKey($user);

        if (array_key_exists($cacheKey, $this->expandedSourceIdsCache)) {
            return $this->expandedSourceIdsCache[$cacheKey];
        }

        $sourceIds = $this->getEffectiveSourceIds($user);

        if ($sourceIds === null) {
            return $this->expandedSourceIdsCache[$cacheKey] = null;
        }

        if (empty($sourceIds)) {
            return $this->expandedSourceIdsCache[$cacheKey] = [];
        }

        $childIds = DB::table('lead_source_parents')
            ->whereIn('parent_source_id', $sourceIds)
            ->pluck('source_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $legacyChildIds = DB::table('lead_sources')
            ->whereIn('parent_id', $sourceIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $this->expandedSourceIdsCache[$cacheKey] = array_values(array_unique(array_merge($sourceIds, $childIds, $legacyChildIds)));
    }

    public function isAdmin(?UserContract $user = null): bool
    {
        $user = $this->resolveUser($user);

        return $user && $user->role?->permission_type === 'all';
    }

    public function isSdrUser(?UserContract $user = null): bool
    {
        $user = $this->resolveUser($user);

        return $this->isSdrStyleRoleName($user?->role?->name);
    }

    /**
     * SDR and LGE share the same calling dashboard / New-stage pool behavior.
     */
    public function isSdrStyleRoleName(?string $roleName): bool
    {
        return in_array(strtolower(trim((string) $roleName)), ['sdr', 'lge'], true);
    }

    /**
     * Pipeline stage IDs with code `new` (legacy shared SDR pool fallback).
     *
     * @return array<int>
     */
    public function getNewStageIds(): array
    {
        if ($this->newStageIdsCache !== null) {
            return $this->newStageIdsCache;
        }

        return $this->newStageIdsCache = DB::table('lead_pipeline_stages')
            ->where('code', 'new')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Stage IDs this role may use. Null means all stages (no pivot rows).
     *
     * @return array<int>|null
     */
    public function getAccessibleStageIds(?UserContract $user = null): ?array
    {
        $user = $this->resolveUser($user);
        $cacheKey = $this->userCacheKey($user);

        if (array_key_exists($cacheKey, $this->accessibleStageIdsCache)) {
            return $this->accessibleStageIdsCache[$cacheKey];
        }

        if ($this->isAdmin($user)) {
            return $this->accessibleStageIdsCache[$cacheKey] = null;
        }

        if (! $user) {
            return $this->accessibleStageIdsCache[$cacheKey] = [];
        }

        $user->loadMissing(['role.pipelineStages']);

        $stageIds = $user->role?->pipelineStages
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all() ?? [];

        if (empty($stageIds)) {
            return $this->accessibleStageIdsCache[$cacheKey] = null;
        }

        return $this->accessibleStageIdsCache[$cacheKey] = $stageIds;
    }

    /**
     * Shared-pool stage IDs for the user/role.
     * Empty pivot shared flags fall back to New stages for SDR/LGE roles.
     *
     * @return array<int>
     */
    public function getSharedStageIds(?UserContract $user = null): array
    {
        $user = $this->resolveUser($user);
        $cacheKey = $this->userCacheKey($user);

        if (array_key_exists($cacheKey, $this->sharedStageIdsCache)) {
            return $this->sharedStageIdsCache[$cacheKey];
        }

        if ($this->isAdmin($user) || ! $user) {
            return $this->sharedStageIdsCache[$cacheKey] = [];
        }

        $user->loadMissing(['role.pipelineStages']);

        $sharedIds = $user->role?->pipelineStages
            ->filter(fn ($stage) => (bool) ($stage->pivot->is_shared ?? false))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all() ?? [];

        if (! empty($sharedIds)) {
            return $this->sharedStageIdsCache[$cacheKey] = $sharedIds;
        }

        if ($this->isSdrUser($user)) {
            return $this->sharedStageIdsCache[$cacheKey] = $this->getNewStageIds();
        }

        return $this->sharedStageIdsCache[$cacheKey] = [];
    }

    public function canAccessStageId(?int $stageId, ?UserContract $user = null): bool
    {
        $accessible = $this->getAccessibleStageIds($user);

        if ($accessible === null) {
            return true;
        }

        if (! $stageId) {
            return false;
        }

        return in_array((int) $stageId, $accessible, true);
    }

    /**
     * Filter a pipeline stage collection to stages the current user may use.
     *
     * @param  iterable<\Webkul\Lead\Contracts\Stage>  $stages
     * @return \Illuminate\Support\Collection
     */
    public function filterAccessibleStages(iterable $stages, ?UserContract $user = null)
    {
        $accessible = $this->getAccessibleStageIds($user);

        $collection = collect($stages);

        if ($accessible === null) {
            return $collection->values();
        }

        return $collection
            ->filter(fn ($stage) => in_array((int) $stage->id, $accessible, true))
            ->values();
    }

    public function leadIsInNewStage(LeadContract $lead): bool
    {
        if ($lead->relationLoaded('stage') && $lead->stage) {
            return $lead->stage->code === 'new';
        }

        if (! $lead->lead_pipeline_stage_id) {
            return false;
        }

        return in_array((int) $lead->lead_pipeline_stage_id, $this->getNewStageIds(), true);
    }

    public function leadIsInSharedStage(LeadContract $lead, ?UserContract $user = null): bool
    {
        if (! $lead->lead_pipeline_stage_id) {
            return false;
        }

        return in_array(
            (int) $lead->lead_pipeline_stage_id,
            $this->getSharedStageIds($user),
            true
        );
    }

    /**
     * SDR/LGE: own leads, or any lead in a shared stage. Admin: all.
     * Main (non-SDR): own leads only (sales person + admin).
     */
    public function canAccessLeadByOwner(LeadContract $lead, ?UserContract $user = null): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        $user = $this->resolveUser($user);

        if (! $user) {
            return false;
        }

        if ($this->isSdrUser($user)) {
            if ((int) $lead->user_id === (int) $user->id) {
                return true;
            }

            return $this->leadIsInSharedStage($lead, $user);
        }

        return (int) $lead->user_id === (int) $user->id;
    }

    /**
     * Apply owner visibility for lead listings.
     * SDR/LGE share configured shared stages (default: New); other stages are owner-only.
     * Main non-admin users see only their own leads. Admins are unrestricted.
     */
    public function applyLeadOwnerVisibilityScope(Builder $query, string $table = 'leads'): Builder
    {
        if ($this->isAdmin()) {
            return $query;
        }

        if ($this->isSdrUser()) {
            $userId = auth()->guard('user')->id();
            $sharedStageIds = $this->getSharedStageIds();

            return $query->where(function ($ownerQuery) use ($userId, $sharedStageIds, $table) {
                $ownerQuery->where("{$table}.user_id", $userId);

                if (! empty($sharedStageIds)) {
                    $ownerQuery->orWhereIn("{$table}.lead_pipeline_stage_id", $sharedStageIds);
                }
            });
        }

        return $query->where("{$table}.user_id", auth()->guard('user')->id());
    }

    /**
     * Query-builder variant for datagrids / raw joins on `leads`.
     */
    public function applyLeadOwnerVisibilityTableScope(QueryBuilder $query): QueryBuilder
    {
        if ($this->isAdmin()) {
            return $query;
        }

        if ($this->isSdrUser()) {
            $userId = auth()->guard('user')->id();
            $sharedStageIds = $this->getSharedStageIds();

            return $query->where(function ($ownerQuery) use ($userId, $sharedStageIds) {
                $ownerQuery->where('leads.user_id', $userId);

                if (! empty($sharedStageIds)) {
                    $ownerQuery->orWhereIn('leads.lead_pipeline_stage_id', $sharedStageIds);
                }
            });
        }

        return $query->where('leads.user_id', auth()->guard('user')->id());
    }

    /**
     * Restrict listings to stages assigned to the role (when pivot is configured).
     */
    public function applyAccessibleStageScope(Builder $query, string $table = 'leads'): Builder
    {
        $accessible = $this->getAccessibleStageIds();

        if ($accessible === null) {
            return $query;
        }

        if (empty($accessible)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn("{$table}.lead_pipeline_stage_id", $accessible);
    }

    public function applyAccessibleStageTableScope(QueryBuilder $query): QueryBuilder
    {
        $accessible = $this->getAccessibleStageIds();

        if ($accessible === null) {
            return $query;
        }

        if (empty($accessible)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('leads.lead_pipeline_stage_id', $accessible);
    }

    public function canAccessSourceId(int $sourceId, ?UserContract $user = null): bool
    {
        $allowed = $this->getExpandedSourceIds($user);

        if ($allowed === null) {
            return true;
        }

        return in_array($sourceId, $allowed, true);
    }

    public function canUseLeadSourceSelection(int $sourceId, ?int $subSourceId = null, ?UserContract $user = null): bool
    {
        if ($this->canAccessSourceId($sourceId, $user)) {
            return true;
        }

        if (! $subSourceId || ! $this->canAccessSourceId($subSourceId, $user)) {
            return false;
        }

        return DB::table('lead_source_parents')
            ->where('parent_source_id', $sourceId)
            ->where('source_id', $subSourceId)
            ->exists()
            || DB::table('lead_sources')
                ->where('id', $subSourceId)
                ->where('parent_id', $sourceId)
                ->exists();
    }

    /**
     * IDs of sub-sources visible under the selected parent source.
     *
     * @return array<int>|null
     */
    public function getAccessibleSubSourceIdsForParent(int $sourceId, ?UserContract $user = null): ?array
    {
        $childIds = $this->getSubSourceIdsForParent($sourceId);

        $allowed = $this->getExpandedSourceIds($user);

        if ($allowed === null) {
            return $childIds;
        }

        return array_values(array_intersect($childIds, $allowed));
    }

    public function canViewSubSourcesForParent(int $sourceId, ?UserContract $user = null): bool
    {
        if ($this->canAccessSourceId($sourceId, $user)) {
            return true;
        }

        return ! empty($this->getAccessibleSubSourceIdsForParent($sourceId, $user));
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

        if ($lead->getAttributes()['lead_disqualification_reason'] ?? null) {
            return false;
        }

        if (! $this->canAccessStageId(
            $lead->lead_pipeline_stage_id ? (int) $lead->lead_pipeline_stage_id : null,
            $user
        )) {
            return false;
        }

        if (! $this->leadMatchesSourceScope($lead, $user)) {
            return false;
        }

        if (! $this->leadMatchesOrganizationScope($lead, $user)) {
            return false;
        }

        return $this->canAccessLeadByOwner($lead, $user);
    }

    public function applyLeadQueryScope(Builder $query): Builder
    {
        $query = $this->applyDisqualificationQueryScope($query);

        $query = $this->applyAccessibleStageScope($query);

        $query = $this->applySourceQueryScope($query);

        return $this->applyOrganizationQueryScope($query);
    }

    public function applyLeadTableScope(QueryBuilder $query): QueryBuilder
    {
        $query = $this->applyDisqualificationTableScope($query);

        $query = $this->applyAccessibleStageTableScope($query);

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

        $organizationId = $lead->getAttributes()['organization_id']
            ?? $lead->person?->getAttributes()['organization_id']
            ?? null;

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
                ->orWhereIn('lead_sub_source_id', $allowed);
        });
    }

    protected function applyDisqualificationQueryScope(Builder $query): Builder
    {
        if ($this->isAdmin()) {
            return $query;
        }

        return $query->whereNull('leads.lead_disqualification_reason');
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

        return $query->where(function ($scopeQuery) use ($allowed) {
            $scopeQuery
                ->whereIn('organization_id', $allowed)
                ->orWhereHas('person', fn ($personQuery) => $personQuery->whereIn('organization_id', $allowed));
        });
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
                ->orWhereIn('leads.lead_sub_source_id', $allowed);
        });
    }

    protected function applyDisqualificationTableScope(QueryBuilder $query): QueryBuilder
    {
        if ($this->isAdmin()) {
            return $query;
        }

        return $query->whereNull('leads.lead_disqualification_reason');
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

        return $query->where(function ($scopeQuery) use ($allowed) {
            $scopeQuery
                ->whereIn('leads.organization_id', $allowed)
                ->orWhereIn('persons.organization_id', $allowed);
        });
    }

    /**
     * @return array<int>
     */
    protected function getSubSourceIdsForParent(int $sourceId): array
    {
        $pivotChildIds = DB::table('lead_source_parents')
            ->where('parent_source_id', $sourceId)
            ->pluck('source_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $legacyChildIds = DB::table('lead_sources')
            ->where('parent_id', $sourceId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_unique(array_merge($pivotChildIds, $legacyChildIds)));
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

    protected function userCacheKey(?UserContract $user = null): string
    {
        return $user?->id
            ? 'user:'.$user->id
            : 'guest';
    }
}
