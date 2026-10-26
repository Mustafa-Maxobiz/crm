<?php

namespace Webkul\Admin\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Webkul\Admin\Models\WorkingHour;

class WorkingHourController extends Controller
{
    /**
     * Display working hours for all active users.
     */
    public function index(): View
    {
        $users = $this->paginateUsers($this->workingHourUsers());

        $workingHours = WorkingHour::query()
            ->whereIn('user_id', $users->getCollection()->pluck('id'))
            ->get()
            ->keyBy('user_id');

        return view('admin::settings.working-hours.index', compact('users', 'workingHours'));
    }

    /**
     * Store or update working hours for any active user.
     */
    public function store(): RedirectResponse
    {
        $activeUserIds = $this->workingHourUsers()->pluck('id')->all();

        $validator = validator(request()->all(), [
            'user_id'    => ['required', 'integer', 'exists:users,id', \Illuminate\Validation\Rule::in($activeUserIds)],
            'start_time' => ['required', 'regex:/^(?:[01]\\d|2[0-3]):[0-5]\\d(?::[0-5]\\d)?$/'],
            'end_time'   => ['required', 'regex:/^(?:[01]\\d|2[0-3]):[0-5]\\d(?::[0-5]\\d)?$/'],
        ], [
            'user_id.in'        => 'Working hours can only be set for active users.',
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
     * Fetch every active user and all of their assigned role names.
     */
    protected function workingHourUsers()
    {
        $users = DB::table('users')
            ->leftJoin('roles as primary_roles', 'users.role_id', '=', 'primary_roles.id')
            ->where('users.status', 1)
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'primary_roles.name as primary_role_name',
                'primary_roles.permission_type as primary_permission_type',
            ])
            ->orderBy('users.name')
            ->get();

        if (! Schema::hasTable('user_roles') || $users->isEmpty()) {
            return $users->map(function ($user) {
                $user->role_name = $this->displayRoleName(
                    $user->primary_role_name,
                    $user->primary_permission_type
                );

                return $user;
            });
        }

        $roleNamesByUser = DB::table('user_roles')
            ->join('roles', 'user_roles.role_id', '=', 'roles.id')
            ->whereIn('user_roles.user_id', $users->pluck('id'))
            ->select('user_roles.user_id', 'roles.name', 'roles.permission_type')
            ->get()
            ->groupBy('user_id');

        return $users->map(function ($user) use ($roleNamesByUser) {
            $roles = collect();

            if ($roleName = $this->displayRoleName(
                $user->primary_role_name,
                $user->primary_permission_type
            )) {
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

    protected function paginateUsers($users, int $perPage = 10): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $users->forPage($page, $perPage)->values(),
            $users->count(),
            $perPage,
            $page,
            [
                'path'  => request()->url(),
                'query' => request()->query(),
            ]
        );
    }

    protected function displayRoleName(?string $roleName, ?string $permissionType): ?string
    {
        if ($permissionType === 'all') {
            return $roleName ?: 'Administrator';
        }

        return $roleName;
    }

    protected function normalizeTimeForDatabase(string $time): string
    {
        return strlen($time) === 5 ? $time.':00' : $time;
    }
}
