<?php

namespace Webkul\Admin\Http\Controllers\Settings;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Lead\Services\FollowupRuleService;
use Webkul\Lead\Services\FollowupScheduleService;

class FollowupScheduleController extends Controller
{
    public const CONFIG_PREFIX = 'general.settings.followup_schedule.';

    public function __construct(
        protected FollowupScheduleService $followupScheduleService,
        protected FollowupRuleService $followupRuleService,
    ) {}

    public function index(): View
    {
        return view('admin::settings.followup-schedule.index', [
            'enabled'     => $this->followupScheduleService->isEnabled(),
            'units'       => FollowupScheduleService::UNITS,
            'behaviors'   => FollowupRuleService::EXHAUSTION_BEHAVIORS,
            'rules'       => $this->rulesPayload(),
            'roles'       => $this->roleOptions(),
            'users'       => $this->userOptions(),
            'sources'     => DB::table('lead_sources')->orderBy('name')->get(['id', 'name']),
            'tags'        => DB::table('tags')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Keep the existing global enable switch.
     */
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'enabled' => ['nullable', 'boolean'],
        ]);

        Event::dispatch('core.configuration.save.before');

        DB::table('core_config')->updateOrInsert(
            ['code' => self::CONFIG_PREFIX.'enabled'],
            [
                'value'      => $request->boolean('enabled') ? '1' : '0',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        Event::dispatch('core.configuration.save.after');

        session()->flash('success', trans('admin::app.settings.followup-schedule.index.update-success'));

        return redirect()->route('admin.settings.followup_schedule.index');
    }

    public function storeRule(Request $request): JsonResponse
    {
        $data = $this->validateRule($request);

        $id = DB::transaction(function () use ($data) {
            $id = DB::table('followup_rule_groups')->insertGetId([
                'name'                => $data['name'],
                'is_active'           => $data['is_active'],
                'is_default'          => false,
                'max_days'            => $this->calculateMaxDays($data['steps']),
                'exhaustion_behavior' => $data['exhaustion_behavior'],
                'created_by'          => auth()->guard('user')->id(),
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);

            $this->syncRuleRelations($id, $data);

            return $id;
        });

        return response()->json([
            'message' => 'Follow-up rule created successfully.',
            'rule'    => $this->rulePayload($id),
        ]);
    }

    public function updateRule(Request $request, int $id): JsonResponse
    {
        $rule = DB::table('followup_rule_groups')->where('id', $id)->first();

        abort_if(! $rule, 404);

        $data = $this->validateRule($request);

        if ($rule->is_default) {
            $data['role_ids'] = [];
            $data['user_ids'] = [];
            $data['source_ids'] = [];
            $data['tag_ids'] = [];
        }

        DB::transaction(function () use ($id, $data) {
            DB::table('followup_rule_groups')
                ->where('id', $id)
                ->update([
                    'name'                => $data['name'],
                    'is_active'           => $data['is_active'],
                    'max_days'            => $this->calculateMaxDays($data['steps']),
                    'exhaustion_behavior' => $data['exhaustion_behavior'],
                    'updated_at'          => now(),
                ]);

            $this->syncRuleRelations($id, $data);
        });

        return response()->json([
            'message' => 'Follow-up rule updated successfully.',
            'rule'    => $this->rulePayload($id),
        ]);
    }

    public function destroyRule(int $id): JsonResponse
    {
        $rule = DB::table('followup_rule_groups')->where('id', $id)->first();

        abort_if(! $rule, 404);

        if ($rule->is_default) {
            return response()->json([
                'message' => 'The Default Follow-up Rule cannot be deleted.',
            ], 422);
        }

        DB::table('followup_rule_groups')->where('id', $id)->delete();

        return response()->json([
            'message' => 'Follow-up rule deleted successfully.',
        ]);
    }

    protected function validateRule(Request $request): array
    {
        $data = $request->validate([
            'name'                    => ['required', 'string', 'max:255'],
            'is_active'               => ['required', 'boolean'],
            'exhaustion_behavior'     => ['required', Rule::in(FollowupRuleService::EXHAUSTION_BEHAVIORS)],
            'use_roles'               => ['nullable', 'boolean'],
            'use_users'               => ['nullable', 'boolean'],
            'use_sources'             => ['nullable', 'boolean'],
            'use_tags'                => ['nullable', 'boolean'],
            'role_ids'                => ['nullable', 'array'],
            'role_ids.*'              => ['integer', 'exists:roles,id'],
            'user_ids'                => ['nullable', 'array'],
            'user_ids.*'              => ['integer', 'exists:users,id'],
            'source_ids'              => ['nullable', 'array'],
            'source_ids.*'            => ['integer', 'exists:lead_sources,id'],
            'tag_ids'                 => ['nullable', 'array'],
            'tag_ids.*'               => ['integer', 'exists:tags,id'],
            'steps'                   => ['required', 'array', 'min:1'],
            'steps.*.value'           => ['required', 'integer', 'min:1'],
            'steps.*.unit'            => ['required', Rule::in(FollowupScheduleService::UNITS)],
            'steps.*.frequency'       => ['required', 'integer', 'min:1'],
        ]);

        $data['is_active'] = (bool) $data['is_active'];
        $data['use_roles'] = (bool) ($data['use_roles'] ?? false);
        $data['use_users'] = (bool) ($data['use_users'] ?? false);
        $data['use_sources'] = (bool) ($data['use_sources'] ?? false);
        $data['use_tags'] = (bool) ($data['use_tags'] ?? false);

        $data['role_ids'] = $data['use_roles']
            ? array_values(array_unique(array_map('intval', $data['role_ids'] ?? [])))
            : [];
        $data['user_ids'] = $data['use_users']
            ? array_values(array_unique(array_map('intval', $data['user_ids'] ?? [])))
            : [];
        $data['source_ids'] = $data['use_sources']
            ? array_values(array_unique(array_map('intval', $data['source_ids'] ?? [])))
            : [];
        $data['tag_ids'] = $data['use_tags']
            ? array_values(array_unique(array_map('intval', $data['tag_ids'] ?? [])))
            : [];

        foreach ([
            'Roles' => ['enabled' => $data['use_roles'], 'ids' => $data['role_ids'], 'field' => 'role_ids'],
            'Users' => ['enabled' => $data['use_users'], 'ids' => $data['user_ids'], 'field' => 'user_ids'],
            'Sources' => ['enabled' => $data['use_sources'], 'ids' => $data['source_ids'], 'field' => 'source_ids'],
            'Tags' => ['enabled' => $data['use_tags'], 'ids' => $data['tag_ids'], 'field' => 'tag_ids'],
        ] as $label => $filter) {
            if ($filter['enabled'] && empty($filter['ids'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    $filter['field'] => ["Select at least one {$label} option or turn off the {$label} filter."],
                ]);
            }
        }

        $data['steps'] = $this->followupScheduleService->normalizeSteps($data['steps']);

        $this->validateFilterDependencies($data);

        return $data;
    }

    protected function syncRuleRelations(int $id, array $data): void
    {
        $pivotMap = [
            'followup_rule_roles'   => ['role_id', $data['role_ids']],
            'followup_rule_users'   => ['user_id', $data['user_ids']],
            'followup_rule_sources' => ['lead_source_id', $data['source_ids']],
            'followup_rule_tags'    => ['tag_id', $data['tag_ids']],
        ];

        foreach ($pivotMap as $table => [$column, $ids]) {
            DB::table($table)->where('followup_rule_group_id', $id)->delete();

            foreach ($ids as $relatedId) {
                DB::table($table)->insert([
                    'followup_rule_group_id' => $id,
                    $column                  => $relatedId,
                ]);
            }
        }

        DB::table('followup_rule_schedules')->where('followup_rule_group_id', $id)->delete();

        foreach ($data['steps'] as $index => $step) {
            DB::table('followup_rule_schedules')->insert([
                'followup_rule_group_id' => $id,
                'sequence'               => $index + 1,
                'delay_value'            => $step['value'],
                'delay_unit'             => $step['unit'],
                'frequency'              => $step['frequency'],
                'created_at'             => now(),
                'updated_at'             => now(),
            ]);
        }
    }

    protected function roleOptions(): array
    {
        $sourceMap = DB::table('role_source')
            ->get(['role_id', 'lead_source_id'])
            ->groupBy('role_id')
            ->map(fn ($rows) => $rows->pluck('lead_source_id')->map(fn ($id) => (int) $id)->values()->all());

        return DB::table('roles')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($role) => [
                'id'         => (int) $role->id,
                'name'       => $role->name,
                'source_ids' => $sourceMap->get($role->id, []),
            ])
            ->values()
            ->all();
    }

    protected function userOptions(): array
    {
        $roleMap = DB::table('user_roles')
            ->get(['user_id', 'role_id'])
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->pluck('role_id')->map(fn ($id) => (int) $id)->values()->all());

        $sourceMap = DB::table('user_source')
            ->get(['user_id', 'lead_source_id'])
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->pluck('lead_source_id')->map(fn ($id) => (int) $id)->values()->all());

        return DB::table('users')
            ->where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role_id'])
            ->map(function ($user) use ($roleMap, $sourceMap) {
                $roleIds = $roleMap->get($user->id, []);

                if ($user->role_id && ! in_array((int) $user->role_id, $roleIds, true)) {
                    $roleIds[] = (int) $user->role_id;
                }

                return [
                    'id'         => (int) $user->id,
                    'name'       => $user->name,
                    'email'      => $user->email,
                    'role_ids'   => array_values(array_unique($roleIds)),
                    'source_ids' => $sourceMap->get($user->id, []),
                ];
            })
            ->values()
            ->all();
    }

    protected function validateFilterDependencies(array $data): void
    {
        if ($data['use_roles'] && $data['use_users']) {
            $allowedUserIds = DB::table('users')
                ->leftJoin('user_roles', 'users.id', '=', 'user_roles.user_id')
                ->where(function ($query) use ($data) {
                    $query
                        ->whereIn('user_roles.role_id', $data['role_ids'])
                        ->orWhereIn('users.role_id', $data['role_ids']);
                })
                ->pluck('users.id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->all();

            $invalidUserIds = array_diff($data['user_ids'], $allowedUserIds);

            if (! empty($invalidUserIds)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'user_ids' => ['Selected users must belong to one of the selected roles.'],
                ]);
            }
        }

        if ($data['use_users'] && $data['use_sources']) {
            $allowedSourceIds = $this->sourceIdsForUsers(
                $data['user_ids'],
                $data['use_roles'] ? $data['role_ids'] : []
            );

            $invalidSourceIds = array_diff($data['source_ids'], $allowedSourceIds);

            if (! empty($invalidSourceIds)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'source_ids' => ['Selected sources must be available to the selected users.'],
                ]);
            }
        }
    }

    /**
     * @param  array<int>  $userIds
     * @param  array<int>  $roleFilterIds
     * @return array<int>
     */
    protected function sourceIdsForUsers(array $userIds, array $roleFilterIds = []): array
    {
        if (empty($userIds)) {
            return [];
        }

        $allSourceIds = DB::table('lead_sources')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $userSourceMap = DB::table('user_source')
            ->whereIn('user_id', $userIds)
            ->get(['user_id', 'lead_source_id'])
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->pluck('lead_source_id')->map(fn ($id) => (int) $id)->all());

        $userRoleRows = DB::table('users')
            ->leftJoin('user_roles', 'users.id', '=', 'user_roles.user_id')
            ->whereIn('users.id', $userIds)
            ->get(['users.id as user_id', 'users.role_id as primary_role_id', 'user_roles.role_id as pivot_role_id']);

        $roleIds = $userRoleRows
            ->flatMap(fn ($row) => array_filter([
                $row->pivot_role_id ? (int) $row->pivot_role_id : null,
                $row->primary_role_id ? (int) $row->primary_role_id : null,
            ]))
            ->unique()
            ->values()
            ->all();

        $roleSourceMap = DB::table('role_source')
            ->whereIn('role_id', $roleIds)
            ->get(['role_id', 'lead_source_id'])
            ->groupBy('role_id')
            ->map(fn ($rows) => $rows->pluck('lead_source_id')->map(fn ($id) => (int) $id)->all());

        $allowed = collect();

        foreach ($userIds as $userId) {
            $userSourceIds = $userSourceMap->get($userId, []);

            $matchingRoleIds = $userRoleRows
                ->where('user_id', $userId)
                ->flatMap(fn ($row) => array_filter([
                    $row->pivot_role_id ? (int) $row->pivot_role_id : null,
                    $row->primary_role_id ? (int) $row->primary_role_id : null,
                ]))
                ->unique()
                ->values()
                ->all();

            if (! empty($roleFilterIds)) {
                $matchingRoleIds = array_values(array_intersect($matchingRoleIds, $roleFilterIds));
            }

            if (empty($matchingRoleIds)) {
                $effective = ! empty($userSourceIds) ? $userSourceIds : $allSourceIds;
                $allowed->push(...$effective);

                continue;
            }

            foreach ($matchingRoleIds as $roleId) {
                $roleSourceIds = $roleSourceMap->get($roleId, []);

                if (! empty($userSourceIds) && ! empty($roleSourceIds)) {
                    $effective = array_values(array_intersect($userSourceIds, $roleSourceIds));
                } elseif (! empty($userSourceIds)) {
                    $effective = $userSourceIds;
                } elseif (! empty($roleSourceIds)) {
                    $effective = $roleSourceIds;
                } else {
                    $effective = $allSourceIds;
                }

                $allowed->push(...$effective);
            }
        }

        return $allowed
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    protected function calculateMaxDays(array $steps): int
    {
        return $this->followupScheduleService->calculateMaxDays($steps);
    }

    protected function rulesPayload(): array
    {
        return DB::table('followup_rule_groups')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->pluck('id')
            ->map(fn ($id) => $this->rulePayload((int) $id))
            ->filter()
            ->values()
            ->all();
    }

    protected function rulePayload(int $id): ?array
    {
        $rule = DB::table('followup_rule_groups')->where('id', $id)->first();

        if (! $rule) {
            return null;
        }

        return [
            'id'                  => (int) $rule->id,
            'name'                => $rule->name,
            'is_active'           => (bool) $rule->is_active,
            'is_default'          => (bool) $rule->is_default,
            'max_days'            => (int) $rule->max_days,
            'exhaustion_behavior' => $rule->exhaustion_behavior,
            'role_ids'            => DB::table('followup_rule_roles')->where('followup_rule_group_id', $id)->pluck('role_id')->map(fn ($v) => (int) $v)->all(),
            'user_ids'            => DB::table('followup_rule_users')->where('followup_rule_group_id', $id)->pluck('user_id')->map(fn ($v) => (int) $v)->all(),
            'source_ids'          => DB::table('followup_rule_sources')->where('followup_rule_group_id', $id)->pluck('lead_source_id')->map(fn ($v) => (int) $v)->all(),
            'tag_ids'             => DB::table('followup_rule_tags')->where('followup_rule_group_id', $id)->pluck('tag_id')->map(fn ($v) => (int) $v)->all(),
            'steps'               => DB::table('followup_rule_schedules')
                ->where('followup_rule_group_id', $id)
                ->orderBy('sequence')
                ->get()
                ->map(fn ($step) => [
                    'value'     => (int) $step->delay_value,
                    'unit'      => $step->delay_unit,
                    'frequency' => (int) $step->frequency,
                ])
                ->all(),
        ];
    }
}
