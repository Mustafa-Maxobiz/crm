<?php

namespace Webkul\Lead\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Webkul\Lead\Models\Lead;
use Webkul\Tag\Repositories\TagRepository;

class LeadForwardService
{
    public const TYPE_COLD_LEAD = 'cold_lead';

    protected ?int $warmLeadTagId = null;

    protected ?int $coldLeadTagId = null;

    public function __construct(
        protected TagRepository $tagRepository,
    ) {}

    public function coldLeadTagId(): ?int
    {
        if ($this->coldLeadTagId !== null) {
            return $this->coldLeadTagId;
        }

        $tag = $this->tagRepository
            ->getModel()
            ->newQuery()
            ->whereRaw('LOWER(TRIM(name)) = ?', ['cold lead'])
            ->first(['id']);

        return $this->coldLeadTagId = $tag ? (int) $tag->id : null;
    }

    public function warmLeadTagId(): ?int
    {
        if ($this->warmLeadTagId !== null) {
            return $this->warmLeadTagId;
        }

        $tag = $this->tagRepository
            ->getModel()
            ->newQuery()
            ->whereRaw('LOWER(TRIM(name)) = ?', ['warm lead'])
            ->first(['id']);

        return $this->warmLeadTagId = $tag ? (int) $tag->id : null;
    }

    /**
     * @return array<int, int>
     */
    public function classificationTagIds(): array
    {
        return collect([$this->warmLeadTagId(), $this->coldLeadTagId()])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public function isColdLeadTagSelected(mixed $tags): bool
    {
        $coldLeadTagId = $this->coldLeadTagId();

        if (! $coldLeadTagId) {
            return false;
        }

        return collect(is_array($tags) ? $tags : [$tags])
            ->filter(fn ($value) => filled($value))
            ->contains(function ($value) use ($coldLeadTagId) {
                if (is_numeric($value)) {
                    return (int) $value === $coldLeadTagId;
                }

                return strtolower(trim((string) $value)) === 'cold lead';
            });
    }

    public function isWarmLeadTagSelected(mixed $tags): bool
    {
        $warmLeadTagId = $this->warmLeadTagId();

        if (! $warmLeadTagId) {
            return false;
        }

        return collect(is_array($tags) ? $tags : [$tags])
            ->filter(fn ($value) => filled($value))
            ->contains(function ($value) use ($warmLeadTagId) {
                if (is_numeric($value)) {
                    return (int) $value === $warmLeadTagId;
                }

                return strtolower(trim((string) $value)) === 'warm lead';
            });
    }

    public function hasClassificationTagSelected(mixed $tags): bool
    {
        return $this->isWarmLeadTagSelected($tags) || $this->isColdLeadTagSelected($tags);
    }

    /**
     * Keep only one Warm/Cold classification. If both are present, the last
     * selected classification wins unless a preferred tag is provided.
     *
     * @param  array<int, int>  $tagIds
     * @return array<int, int>
     */
    public function normalizeClassificationTagIds(array $tagIds, ?int $preferredTagId = null): array
    {
        $tagIds = collect($tagIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();

        $warmLeadTagId = $this->warmLeadTagId();
        $coldLeadTagId = $this->coldLeadTagId();
        $classificationIds = array_values(array_filter([$warmLeadTagId, $coldLeadTagId]));

        if (empty($classificationIds)) {
            return array_values(array_unique($tagIds));
        }

        $selectedClassificationId = null;

        foreach ($tagIds as $tagId) {
            if (in_array($tagId, $classificationIds, true)) {
                $selectedClassificationId = $tagId;
            }
        }

        $submittedClassificationIds = array_values(array_intersect($tagIds, $classificationIds));

        if (count(array_unique($submittedClassificationIds)) > 1 && ! $preferredTagId) {
            throw ValidationException::withMessages([
                'tags' => ['Select either Warm Lead or Cold Lead, not both.'],
            ]);
        }

        if ($preferredTagId && in_array($preferredTagId, $classificationIds, true)) {
            $selectedClassificationId = $preferredTagId;
        }

        $normalized = [];

        foreach ($tagIds as $tagId) {
            if (in_array($tagId, $classificationIds, true) && $tagId !== $selectedClassificationId) {
                continue;
            }

            $normalized[] = $tagId;
        }

        return array_values(array_unique($normalized));
    }

    public function leadHasClassification(Lead $lead): bool
    {
        $classificationIds = $this->classificationTagIds();

        if (empty($classificationIds)) {
            return false;
        }

        $lead->loadMissing('tags');

        return $lead->tags
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->intersect($classificationIds)
            ->isNotEmpty();
    }

    /**
     * @return array<int, int>
     */
    public function syncClassificationTag(Lead $lead, int $tagId): array
    {
        $tagId = (int) $tagId;
        $classificationIds = $this->classificationTagIds();

        if (! in_array($tagId, $classificationIds, true)) {
            $lead->tags()->syncWithoutDetaching([$tagId]);

            return [];
        }

        $detachedTagIds = array_values(array_diff($classificationIds, [$tagId]));

        if (! empty($detachedTagIds)) {
            $lead->tags()->detach($detachedTagIds);
        }

        $lead->tags()->syncWithoutDetaching([$tagId]);

        return $detachedTagIds;
    }

    public function activeSdrUsers()
    {
        return DB::table('users')
            ->leftJoin('roles', 'users.role_id', '=', 'roles.id')
            ->where('users.status', 1)
            ->whereRaw('LOWER(TRIM(roles.name)) = ?', ['sdr'])
            ->orderBy('users.name')
            ->get(['users.id', 'users.name', 'users.email']);
    }

    /**
     * @return array<int, int>
     */
    public function validateActiveSdrIds(array $ids, string $field = 'cold_lead_sdr_user_id'): array
    {
        $ids = collect($ids)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (empty($ids)) {
            throw ValidationException::withMessages([
                $field => ['The selected user is not an active SDR.'],
            ]);
        }

        $validIds = $this->activeSdrUsers()
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (count($validIds) !== count($ids)) {
            throw ValidationException::withMessages([
                $field => ['The selected user is not an active SDR.'],
            ]);
        }

        return $ids;
    }

    public function validateActiveSdrId(mixed $id, string $field = 'cold_lead_sdr_user_id'): int
    {
        return $this->validateActiveSdrIds([(int) $id], $field)[0];
    }

    public function forwardColdLeadToSdr(Lead $lead, int $fromUserId, int $toUserId, bool $validateSdr = true): Lead
    {
        $toUserId = $validateSdr
            ? $this->validateActiveSdrId($toUserId)
            : (int) $toUserId;

        return DB::transaction(function () use ($lead, $fromUserId, $toUserId) {
            $lead->forceFill([
                'user_id'       => $toUserId,
                'lead_owner_id' => $toUserId,
            ])->save();

            DB::table('lead_forwards')->insert([
                'lead_id'      => $lead->id,
                'from_user_id' => $fromUserId,
                'to_user_id'   => $toUserId,
                'forward_type' => self::TYPE_COLD_LEAD,
                'forwarded_at' => now(),
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            return $lead->refresh();
        });
    }

    public function switchToColdAndForward(Lead $lead, int $fromUserId, int $toUserId, bool $validateSdr = true): Lead
    {
        $toUserId = $validateSdr
            ? $this->validateActiveSdrId($toUserId, 'sdr_user_id')
            : (int) $toUserId;

        $coldLeadTagId = $this->coldLeadTagId();

        if (! $coldLeadTagId) {
            throw ValidationException::withMessages([
                'tag_id' => ['Cold Lead tag is not configured.'],
            ]);
        }

        return DB::transaction(function () use ($lead, $fromUserId, $toUserId, $coldLeadTagId) {
            $lead = Lead::query()->lockForUpdate()->findOrFail($lead->id);

            $this->syncClassificationTag($lead, $coldLeadTagId);

            return $this->forwardColdLeadToSdr($lead, $fromUserId, $toUserId, false);
        });
    }
}
