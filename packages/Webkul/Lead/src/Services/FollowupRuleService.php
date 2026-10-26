<?php

namespace Webkul\Lead\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\Lead\Contracts\Lead;

class FollowupRuleService
{
    public const EXHAUSTION_BEHAVIORS = ['stop', 'repeat_last', 'restart'];

    /**
     * Resolve the winning follow-up rule.
     *
     * Values inside one category are OR conditions. Categories are AND
     * conditions. Empty categories mean "Any". The most specific matching
     * rule wins; ties are resolved by ID.
     */
    public function resolve(?Lead $lead): ?array
    {
        if (! Schema::hasTable('followup_rule_groups')) {
            return null;
        }

        $groups = DB::table('followup_rule_groups')
            ->where('is_active', 1)
            ->orderBy('id')
            ->get();

        if ($groups->isEmpty()) {
            return null;
        }

        $groupIds = $groups->pluck('id')->map(fn ($id) => (int) $id)->all();

        $roles = $this->pivotMap('followup_rule_roles', 'role_id', $groupIds);
        $users = $this->pivotMap('followup_rule_users', 'user_id', $groupIds);
        $sources = $this->pivotMap('followup_rule_sources', 'lead_source_id', $groupIds);
        $tags = $this->pivotMap('followup_rule_tags', 'tag_id', $groupIds);

        $leadUserId = $lead?->user_id ? (int) $lead->user_id : null;
        $leadSourceId = $lead?->lead_source_id ? (int) $lead->lead_source_id : null;
        $leadTagIds = $lead
            ? DB::table('lead_tags')->where('lead_id', $lead->getKey())->pluck('tag_id')->map(fn ($id) => (int) $id)->all()
            : [];
        $leadRoleIds = $leadUserId ? $this->userRoleIds($leadUserId) : [];

        $matched = $groups
            ->filter(function ($group) use ($roles, $users, $sources, $tags, $leadUserId, $leadSourceId, $leadTagIds, $leadRoleIds) {
                $id = (int) $group->id;

                if (! $this->categoryMatches($roles[$id] ?? [], $leadRoleIds)) {
                    return false;
                }

                if (! $this->categoryMatches($users[$id] ?? [], $leadUserId ? [$leadUserId] : [])) {
                    return false;
                }

                if (! $this->categoryMatches($sources[$id] ?? [], $leadSourceId ? [$leadSourceId] : [])) {
                    return false;
                }

                return $this->categoryMatches($tags[$id] ?? [], $leadTagIds);
            })
            ->sort(function ($a, $b) use ($roles, $users, $sources, $tags) {
                $aSpecificity = $this->specificity((int) $a->id, $roles, $users, $sources, $tags);
                $bSpecificity = $this->specificity((int) $b->id, $roles, $users, $sources, $tags);

                $specificityCompare = $bSpecificity <=> $aSpecificity;

                if ($specificityCompare !== 0) {
                    return $specificityCompare;
                }

                return ((int) $a->id) <=> ((int) $b->id);
            })
            ->values();

        $winner = $matched->first()
            ?: $groups->firstWhere('is_default', 1);

        if (! $winner) {
            return null;
        }

        return $this->hydrateRule($winner);
    }

    public function findActiveRule(int $id): ?array
    {
        if (! Schema::hasTable('followup_rule_groups')) {
            return null;
        }

        $group = DB::table('followup_rule_groups')
            ->where('id', $id)
            ->where('is_active', 1)
            ->first();

        return $group ? $this->hydrateRule($group) : null;
    }

    public function defaultRule(): ?array
    {
        if (! Schema::hasTable('followup_rule_groups')) {
            return null;
        }

        $group = DB::table('followup_rule_groups')
            ->where('is_active', 1)
            ->where('is_default', 1)
            ->orderBy('id')
            ->first();

        return $group ? $this->hydrateRule($group) : null;
    }

    public function hydrateRule(object $group): array
    {
        return [
            'id'                  => (int) $group->id,
            'name'                => (string) $group->name,
            'is_default'          => (bool) $group->is_default,
            'max_days'            => max(1, (int) $group->max_days),
            'exhaustion_behavior' => in_array($group->exhaustion_behavior, self::EXHAUSTION_BEHAVIORS, true)
                ? $group->exhaustion_behavior
                : 'repeat_last',
            'steps'               => DB::table('followup_rule_schedules')
                ->where('followup_rule_group_id', $group->id)
                ->orderBy('sequence')
                ->get()
                ->map(fn ($step) => [
                    'value'     => max(1, (int) $step->delay_value),
                    'unit'      => (string) $step->delay_unit,
                    'frequency' => max(1, (int) $step->frequency),
                ])
                ->all(),
        ];
    }

    protected function pivotMap(string $table, string $column, array $groupIds): array
    {
        if (empty($groupIds)) {
            return [];
        }

        return DB::table($table)
            ->whereIn('followup_rule_group_id', $groupIds)
            ->get(['followup_rule_group_id', $column])
            ->groupBy('followup_rule_group_id')
            ->map(fn (Collection $rows) => $rows->pluck($column)->map(fn ($id) => (int) $id)->values()->all())
            ->all();
    }

    protected function userRoleIds(int $userId): array
    {
        $ids = DB::table('user_roles')
            ->where('user_id', $userId)
            ->pluck('role_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $primary = DB::table('users')->where('id', $userId)->value('role_id');

        if ($primary) {
            $ids[] = (int) $primary;
        }

        return array_values(array_unique($ids));
    }

    protected function categoryMatches(array $ruleValues, array $leadValues): bool
    {
        if (empty($ruleValues)) {
            return true;
        }

        return ! empty(array_intersect($ruleValues, $leadValues));
    }

    protected function specificity(int $groupId, array ...$maps): int
    {
        return collect($maps)
            ->filter(fn ($map) => ! empty($map[$groupId] ?? []))
            ->count();
    }
}
