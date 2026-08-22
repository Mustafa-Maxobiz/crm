<?php

namespace Webkul\Lead\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Webkul\Lead\Models\LinkedInProfile;
use Webkul\User\Contracts\User as UserContract;

class LinkedInProfileAccessService
{
    public function __construct(
        protected SourceAccessService $sourceAccessService,
    ) {}

    public function isAdmin(?UserContract $user = null): bool
    {
        return $this->sourceAccessService->isAdmin($user);
    }

    /**
     * @return Collection<int, object{id: int, name: string, profile_url: string, is_active: bool}>
     */
    public function getAssignedProfiles(?UserContract $user = null, bool $activeOnly = true): Collection
    {
        $user = $this->resolveUser($user);

        if (! $user) {
            return collect();
        }

        if ($this->isAdmin($user)) {
            $query = DB::table('linkedin_profiles')->orderBy('name');

            if ($activeOnly) {
                $query->where('is_active', true);
            }

            return $query->get(['id', 'name', 'profile_url', 'is_active']);
        }

        $query = DB::table('linkedin_profiles')
            ->join('linkedin_profile_user', 'linkedin_profiles.id', '=', 'linkedin_profile_user.linkedin_profile_id')
            ->where('linkedin_profile_user.user_id', $user->id)
            ->orderBy('linkedin_profiles.name');

        if ($activeOnly) {
            $query->where('linkedin_profiles.is_active', true);
        }

        return $query->get([
            'linkedin_profiles.id',
            'linkedin_profiles.name',
            'linkedin_profiles.profile_url',
            'linkedin_profiles.is_active',
        ]);
    }

    /**
     * @return array<int>
     */
    public function getAssignedProfileIds(?UserContract $user = null, bool $activeOnly = true): array
    {
        return $this->getAssignedProfiles($user, $activeOnly)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Dropdown options for filters/forms.
     *
     * @param  array<int>  $includeInactiveUsedIds
     * @return array<int, array{label: string, value: int}>
     */
    public function getFilterOptions(?UserContract $user = null, array $includeInactiveUsedIds = []): array
    {
        $profiles = $this->getAssignedProfiles($user, true);

        if ($this->isAdmin($user)) {
            $profiles = DB::table('linkedin_profiles')
                ->orderBy('name')
                ->get(['id', 'name', 'profile_url', 'is_active']);
        }

        $includeInactiveUsedIds = collect($includeInactiveUsedIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->all();

        if (! empty($includeInactiveUsedIds)) {
            $missing = array_diff(
                $includeInactiveUsedIds,
                $profiles->pluck('id')->map(fn ($id) => (int) $id)->all()
            );

            if (! empty($missing)) {
                $extra = DB::table('linkedin_profiles')
                    ->whereIn('id', $missing)
                    ->orderBy('name')
                    ->get(['id', 'name', 'profile_url', 'is_active']);

                $profiles = $profiles->concat($extra);
            }
        }

        return $profiles
            ->unique('id')
            ->map(fn ($profile) => [
                'label' => $profile->name.($profile->is_active ? '' : ' (Inactive)'),
                'value' => (int) $profile->id,
            ])
            ->values()
            ->all();
    }

    /**
     * Filter dropdown options including inactive profiles referenced by the user's leads.
     *
     * @return array<int, array{label: string, value: int}>
     */
    public function getFilterOptionsWithHistoricalLeads(?UserContract $user = null): array
    {
        return $this->getFilterOptions($user, $this->getHistoricalProfileIdsForLeads($user));
    }

    /**
     * Filter dropdown options including inactive profiles referenced by the user's entries.
     *
     * @return array<int, array{label: string, value: int}>
     */
    public function getFilterOptionsWithHistoricalEntries(?UserContract $user = null): array
    {
        return $this->getFilterOptions($user, $this->getHistoricalProfileIdsForEntries($user));
    }

    /**
     * Distinct LinkedIn working profile IDs used on leads visible to the user.
     *
     * @return array<int>
     */
    public function getHistoricalProfileIdsForLeads(?UserContract $user = null): array
    {
        $user = $this->resolveUser($user);

        if (! $user || $this->isAdmin($user)) {
            return [];
        }

        return DB::table('leads')
            ->whereNotNull('linkedin_profile_id')
            ->whereNull('deleted_at')
            ->where(function ($scope) use ($user) {
                $scope
                    ->where('lead_owner_id', $user->id)
                    ->orWhere('user_id', $user->id);
            })
            ->distinct()
            ->pluck('linkedin_profile_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Distinct LinkedIn working profile IDs used on the user's LinkedIn entries.
     *
     * @return array<int>
     */
    public function getHistoricalProfileIdsForEntries(?UserContract $user = null): array
    {
        $user = $this->resolveUser($user);

        if (! $user) {
            return [];
        }

        $query = DB::table('linkedin_entry')
            ->whereNotNull('linkedin_profile_id');

        if (! $this->isAdmin($user)) {
            $query->where('user_id', $user->id);
        }

        return $query
            ->distinct()
            ->pluck('linkedin_profile_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();
    }

    public function canUseProfile(int $profileId, ?UserContract $user = null, ?int $ownerUserId = null): bool
    {
        $profile = DB::table('linkedin_profiles')->where('id', $profileId)->first();

        if (! $profile || ! $profile->is_active) {
            return false;
        }

        $user = $this->resolveUser($user);

        if (! $user) {
            return false;
        }

        if ($this->isAdmin($user)) {
            if ($ownerUserId) {
                return $this->isProfileAssignedToUser($profileId, $ownerUserId);
            }

            return true;
        }

        return $this->isProfileAssignedToUser($profileId, (int) $user->id);
    }

    public function isProfileAssignedToUser(int $profileId, int $userId): bool
    {
        return DB::table('linkedin_profile_user')
            ->where('linkedin_profile_id', $profileId)
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * @throws ValidationException
     */
    public function assertCanUseProfile(int $profileId, ?UserContract $user = null, ?int $ownerUserId = null): void
    {
        if ($profileId <= 0) {
            throw ValidationException::withMessages([
                'linkedin_profile_id' => ['Please select a LinkedIn working profile.'],
            ]);
        }

        $profile = DB::table('linkedin_profiles')->where('id', $profileId)->first();

        if (! $profile) {
            throw ValidationException::withMessages([
                'linkedin_profile_id' => ['The selected LinkedIn working profile does not exist.'],
            ]);
        }

        if (! $profile->is_active) {
            throw ValidationException::withMessages([
                'linkedin_profile_id' => ['The selected LinkedIn working profile is inactive.'],
            ]);
        }

        $targetUserId = $ownerUserId ?: (int) ($this->resolveUser($user)?->id ?? 0);

        if ($targetUserId <= 0) {
            throw ValidationException::withMessages([
                'linkedin_profile_id' => ['Unable to validate LinkedIn working profile assignment.'],
            ]);
        }

        if ($this->isAdmin($user) && ! $ownerUserId) {
            return;
        }

        if (! $this->isProfileAssignedToUser($profileId, $targetUserId)) {
            throw ValidationException::withMessages([
                'linkedin_profile_id' => ['The selected LinkedIn working profile is not assigned to this user.'],
            ]);
        }
    }

    public function applyAccessibleProfileTableScope(QueryBuilder $query, ?UserContract $user = null, string $table = 'leads'): QueryBuilder
    {
        if ($this->isAdmin($user)) {
            return $query;
        }

        $profileIds = $this->getAssignedProfileIds($user, false);

        if (empty($profileIds)) {
            return $query->whereNull("{$table}.linkedin_profile_id");
        }

        return $query->where(function ($scope) use ($table, $profileIds) {
            $scope
                ->whereIn("{$table}.linkedin_profile_id", $profileIds)
                ->orWhereNull("{$table}.linkedin_profile_id");
        });
    }

    public function syncProfileUsers(LinkedInProfile $profile, array $userIds): void
    {
        $userIds = collect($userIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $profile->users()->sync($userIds);
    }

    public function normalizeProfileUrl(string $url): string
    {
        return LinkedInUrlNormalizer::normalize($url);
    }

    public function normalizeProfileUrlForCompare(string $url): string
    {
        return LinkedInUrlNormalizer::normalizeForCompare($url);
    }

    public function profileUrlExists(string $normalizedUrl, ?int $ignoreId = null): bool
    {
        $query = DB::table('linkedin_profiles')
            ->where('profile_url_normalized', $normalizedUrl);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    protected function resolveUser(?UserContract $user = null): ?UserContract
    {
        return $user ?? auth()->guard('user')->user();
    }
}
