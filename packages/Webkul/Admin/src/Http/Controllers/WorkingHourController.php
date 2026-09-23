<?php

namespace Webkul\Admin\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Webkul\Admin\Models\WorkingHour;

class WorkingHourController extends Controller
{
    /**
     * Role names that may have working hours managed here.
     */
    protected array $adminRoleNames = [
        'admin',
        'administrator',
        'super admin',
    ];

    /**
     * Closure role spellings used across this project.
     */
    protected array $closureRoleNames = [
        'lead',
        'lead closer',
        'lead clouser',
        'lead closure',
        'lead_clouser',
        'closure',
        'closer',
    ];

    /**
     * Display working hours for eligible users.
     */
    public function index(): View
    {
        $users = $this->workingHourUsers();

        $workingHours = WorkingHour::query()
            ->whereIn('user_id', $users->pluck('id'))
            ->get()
            ->keyBy('user_id');

        return view('admin::settings.working-hours.index', compact('users', 'workingHours'));
    }

    /**
     * Store or update working hours for an eligible user.
     */
    public function store(): RedirectResponse
    {
        $eligibleUserIds = $this->workingHourUsers()->pluck('id')->all();

        $validator = validator(request()->all(), [
            'user_id'    => ['required', 'integer', 'exists:users,id', Rule::in($eligibleUserIds)],
            'start_time' => ['required', 'regex:/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/'],
            'end_time'   => ['required', 'regex:/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/'],
        ], [
            'user_id.in'        => 'Working hours can only be set for Admin and Lead Closer users.',
            'start_time.regex'  => 'The start time must use HH:MM format.',
            'end_time.regex'    => 'The end time must use HH:MM format.',
        ]);

        $validator->after(function ($validator) {
            $startTime = request('start_time');
            $endTime = request('end_time');

            if (! $startTime || ! $endTime) {
                return;
            }

            if (strtotime($endTime) <= strtotime($startTime)) {
                $validator->errors()->add('end_time', 'The end time must be after the start time.');
            }
        });

        $data = $validator->validate();

        WorkingHour::updateOrCreate(
            ['user_id' => (int) $data['user_id']],
            [
                'start_time' => $this->normalizeTimeForDatabase($data['start_time']),
                'end_time'   => $this->normalizeTimeForDatabase($data['end_time']),
            ]
        );

        session()->flash('success', 'Working hours saved successfully.');

        return redirect()->route('admin.settings.working_hours.index');
    }

    /**
     * Fetch active Admin and Lead Closer users with role names without N+1 queries.
     */
    protected function workingHourUsers()
    {
        $allowedRoleNames = collect(array_merge($this->adminRoleNames, $this->closureRoleNames))
            ->map(fn ($role) => strtolower($role))
            ->unique()
            ->values()
            ->all();

        $query = DB::table('users')
            ->leftJoin('roles as primary_roles', 'users.role_id', '=', 'primary_roles.id')
            ->where('users.status', 1)
            ->where(function ($query) use ($allowedRoleNames) {
                $query
                    ->where('primary_roles.permission_type', 'all')
                    ->orWhereIn(DB::raw('LOWER(primary_roles.name)'), $allowedRoleNames);

                if (Schema::hasTable('user_roles')) {
                    $query->orWhereExists(function ($subQuery) use ($allowedRoleNames) {
                        $subQuery
                            ->select(DB::raw(1))
                            ->from('user_roles')
                            ->join('roles', 'user_roles.role_id', '=', 'roles.id')
                            ->whereColumn('user_roles.user_id', 'users.id')
                            ->where(function ($roleQuery) use ($allowedRoleNames) {
                                $roleQuery
                                    ->where('roles.permission_type', 'all')
                                    ->orWhereIn(DB::raw('LOWER(roles.name)'), $allowedRoleNames);
                            });
                    });
                }
            })
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'primary_roles.name as primary_role_name',
                'primary_roles.permission_type as primary_permission_type',
            ])
            ->orderBy('users.name')
            ->get();

        if (! Schema::hasTable('user_roles') || $query->isEmpty()) {
            return $query->map(function ($user) {
                $user->role_name = $this->displayRoleName($user->primary_role_name, $user->primary_permission_type);

                return $user;
            });
        }

        $roleNamesByUser = DB::table('user_roles')
            ->join('roles', 'user_roles.role_id', '=', 'roles.id')
            ->whereIn('user_roles.user_id', $query->pluck('id'))
            ->where(function ($roleQuery) use ($allowedRoleNames) {
                $roleQuery
                    ->where('roles.permission_type', 'all')
                    ->orWhereIn(DB::raw('LOWER(roles.name)'), $allowedRoleNames);
            })
            ->select('user_roles.user_id', 'roles.name', 'roles.permission_type')
            ->get()
            ->groupBy('user_id');

        return $query->map(function ($user) use ($roleNamesByUser) {
            $roles = collect();

            if ($roleName = $this->displayRoleName($user->primary_role_name, $user->primary_permission_type)) {
                $roles->push($roleName);
            }

            foreach ($roleNamesByUser->get($user->id, collect()) as $role) {
                if ($roleName = $this->displayRoleName($role->name, $role->permission_type)) {
                    $roles->push($roleName);
                }
            }

            $user->role_name = $roles->unique()->implode(', ');

            return $user;
        });
    }

    /**
     * Normalize Admin all-permission roles to a readable role label.
     */
    protected function displayRoleName(?string $roleName, ?string $permissionType): ?string
    {
        if ($permissionType === 'all') {
            return $roleName ?: 'Administrator';
        }

        return $roleName;
    }

    /**
     * Store time in HH:MM:SS format.
     */
    protected function normalizeTimeForDatabase(string $time): string
    {
        return strlen($time) === 5 ? $time.':00' : $time;
    }
}
