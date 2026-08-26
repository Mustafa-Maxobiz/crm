<?php

namespace Webkul\Admin\Http\Controllers\Settings;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Prettus\Repository\Criteria\RequestCriteria;
use Webkul\Admin\DataGrids\Settings\UserDataGrid;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Requests\MassDestroyRequest;
use Webkul\Admin\Http\Requests\MassUpdateRequest;
use Webkul\Admin\Http\Resources\UserResource;
use Webkul\Admin\Notifications\User\Create as UserCreatedNotification;
use Webkul\Contact\Repositories\OrganizationRepository;
use Webkul\Lead\Repositories\SourceRepository;
use Webkul\Lead\Services\SourceAccessService;
use Webkul\User\Repositories\GroupRepository;
use Webkul\User\Repositories\RoleRepository;
use Webkul\User\Repositories\UserRepository;

class UserController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected UserRepository $userRepository,
        protected GroupRepository $groupRepository,
        protected RoleRepository $roleRepository,
        protected SourceRepository $sourceRepository,
        protected OrganizationRepository $organizationRepository,
        protected SourceAccessService $sourceAccessService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(): View|JsonResponse
    {
        if (request()->ajax()) {
            return datagrid(UserDataGrid::class)->process();
        }

        $roles = $this->roleRepository
            ->with(['sources:id', 'organizations:id'])
            ->get(['id', 'name'])
            ->map(fn ($role) => [
                'id'                => $role->id,
                'name'              => $role->name,
                'source_ids'        => $role->sources->pluck('id')->values()->all(),
                'organization_ids'  => $role->organizations->pluck('id')->values()->all(),
            ]);

        $groups = $this->groupRepository->all();

        $sources = $this->sourceRepository->getModel()->roots()->orderBy('sort_order')->get(['id', 'name']);

        $organizations = $this->organizationRepository->getModel()->orderBy('name')->get(['id', 'name']);

        return view('admin::settings.users.index', compact('roles', 'groups', 'sources', 'organizations'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(): View|JsonResponse
    {
        $this->validate(request(), [
            'email'            => 'required|email|unique:users,email',
            'name'             => 'required',
            'password'         => 'nullable',
            'confirm_password' => 'nullable|required_with:password|same:password',
            'role_ids'         => 'required|array|min:1',
            'role_ids.*'       => 'integer|exists:roles,id',
            'role_id'          => 'nullable|integer|exists:roles,id',
            'status'           => 'boolean|in:0,1',
            'view_permission'  => 'string|in:global,group,individual',
        ]);

        $roleIds = $this->normalizedRoleIds(request()->input('role_ids', []), request()->input('role_id'));

        $assignmentValidation = $this->validateUserAssignments(
            $roleIds[0],
            request()->input('source_ids', []),
            request()->input('organization_ids', [])
        );

        if ($assignmentValidation instanceof JsonResponse) {
            return $assignmentValidation;
        }

        $data = request()->all();
        $data['role_id'] = $roleIds[0];

        if (
            isset($data['password'])
            && $data['password']
        ) {
            $data['password'] = bcrypt($data['password']);
        }

        Event::dispatch('settings.user.create.before');

        $admin = $this->userRepository->create($data);

        $admin->groups()->sync($data['groups'] ?? []);
        $admin->roles()->sync($roleIds);

        $admin->sources()->sync($assignmentValidation['source_ids']);
        $admin->organizations()->sync($assignmentValidation['organization_ids']);

        try {
            Mail::queue(new UserCreatedNotification($admin));
        } catch (\Exception $e) {
            report($e);
        }

        Event::dispatch('settings.user.create.after', $admin);

        return new JsonResponse([
            'data'    => $admin->load(['role', 'roles', 'groups', 'sources', 'organizations']),
            'message' => trans('admin::app.settings.users.index.create-success'),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(int $id): View|JsonResponse
    {
        $admin = $this->userRepository->with(['role', 'roles', 'groups', 'sources', 'organizations'])->findOrFail($id);

        $payload = $admin->toArray();
        $payload['role_ids'] = $admin->roles->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

        if (empty($payload['role_ids']) && $admin->role_id) {
            $payload['role_ids'] = [(int) $admin->role_id];
        }

        return new JsonResponse([
            'data' => $payload,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(int $id): JsonResponse
    {
        $this->validate(request(), [
            'email'            => 'required|email|unique:users,email,'.$id,
            'name'             => 'required|string',
            'password'         => 'nullable|string|min:6',
            'confirm_password' => 'nullable|required_with:password|same:password',
            'role_ids'         => 'required|array|min:1',
            'role_ids.*'       => 'integer|exists:roles,id',
            'role_id'          => 'nullable|integer|exists:roles,id',
            'status'           => 'nullable|boolean|in:0,1',
            'view_permission'  => 'required|string|in:global,group,individual',
        ]);

        $roleIds = $this->normalizedRoleIds(request()->input('role_ids', []), request()->input('role_id'));

        $assignmentValidation = $this->validateUserAssignments(
            $roleIds[0],
            request()->input('source_ids', []),
            request()->input('organization_ids', [])
        );

        if ($assignmentValidation instanceof JsonResponse) {
            return $assignmentValidation;
        }

        $data = request()->all();
        $data['role_id'] = $roleIds[0];

        if (empty($data['password'])) {
            $data = Arr::except($data, ['password', 'confirm_password']);
        } else {
            $data['password'] = bcrypt($data['password']);
        }

        $authUser = auth()->guard('user')->user();

        if ($authUser->id == $id) {
            $data['status'] = true;
        }

        Event::dispatch('settings.user.update.before', $id);

        $admin = $this->userRepository->update($data, $id);

        $admin->groups()->sync($data['groups'] ?? []);
        $admin->roles()->sync($roleIds);

        $admin->sources()->sync($assignmentValidation['source_ids']);
        $admin->organizations()->sync($assignmentValidation['organization_ids']);

        Event::dispatch('settings.user.update.after', $admin);

        return new JsonResponse([
            'data'    => $admin->load(['role', 'roles', 'groups', 'sources', 'organizations']),
            'message' => trans('admin::app.settings.users.index.update-success'),
        ]);
    }

    /**
     * Search user results.
     */
    public function search(): JsonResource
    {
        $repository = $this->userRepository
            ->pushCriteria(app(RequestCriteria::class));

        if (request()->boolean('active_only')) {
            $query = $this->userRepository->getModel()
                ->query()
                ->where('status', 1);

            if ($search = request()->input('search')) {
                $searchTerm = Str::of($search)->after(':')->trim()->toString();

                if ($searchTerm !== '') {
                    $query->where('name', 'like', '%'.$searchTerm.'%');
                }
            }

            if ($roleNames = request()->input('role_names')) {
                $roleNames = collect(explode(',', $roleNames))
                    ->map(fn ($roleName) => strtolower(trim($roleName)))
                    ->filter()
                    ->values()
                    ->all();

                if (! empty($roleNames)) {
                    $query->where(function ($query) use ($roleNames) {
                        $query->whereHas('roles', function ($query) use ($roleNames) {
                            $query->where(function ($query) use ($roleNames) {
                                if (in_array('administrator', $roleNames, true) || in_array('admin', $roleNames, true)) {
                                    $query->orWhere('permission_type', 'all');
                                }

                                $query->orWhereIn(DB::raw('LOWER(name)'), $roleNames);
                            });
                        })->orWhereHas('role', function ($query) use ($roleNames) {
                            $query->where(function ($query) use ($roleNames) {
                                if (in_array('administrator', $roleNames, true) || in_array('admin', $roleNames, true)) {
                                    $query->orWhere('permission_type', 'all');
                                }

                                $query->orWhereIn(DB::raw('LOWER(name)'), $roleNames);
                            });
                        });
                    });
                }
            }

            $repository = $query->orderBy('name')->get();

            return UserResource::collection($repository);
        }

        $users = $repository->all();

        return UserResource::collection($users);
    }

    /**
     * Destroy specified user.
     */
    public function destroy(int $id): JsonResponse
    {
        if ($this->userRepository->count() == 1) {
            return new JsonResponse([
                'message' => trans('admin::app.settings.users.index.last-delete-error'),
            ], 400);
        }

        try {
            Event::dispatch('user.admin.delete.before', $id);

            $this->userRepository->delete($id);

            Event::dispatch('user.admin.delete.after', $id);

            return new JsonResponse([
                'message' => trans('admin::app.settings.users.index.delete-success'),
            ], 200);
        } catch (\Exception $e) {
        }

        return new JsonResponse([
            'message' => trans('admin::app.settings.users.index.delete-failed'),
        ], 500);
    }

    /**
     * Mass Update the specified resources.
     */
    public function massUpdate(MassUpdateRequest $massDestroyRequest): JsonResponse
    {
        $count = 0;

        $users = $this->userRepository->findWhereIn('id', $massDestroyRequest->input('indices'));

        foreach ($users as $users) {
            if (auth()->guard('user')->user()->id == $users->id) {
                continue;
            }

            Event::dispatch('settings.user.update.before', $users->id);

            $this->userRepository->update([
                'status' => $massDestroyRequest->input('value'),
            ], $users->id);

            Event::dispatch('settings.user.update.after', $users->id);

            $count++;
        }

        if (! $count) {
            return response()->json([
                'message' => trans('admin::app.settings.users.index.mass-update-failed'),
            ], 400);
        }

        return response()->json([
            'message' => trans('admin::app.settings.users.index.mass-update-success'),
        ]);
    }

    /**
     * Mass Delete the specified resources.
     */
    public function massDestroy(MassDestroyRequest $massDestroyRequest): JsonResponse
    {
        $count = 0;

        $users = $this->userRepository->findWhereIn('id', $massDestroyRequest->input('indices'));

        foreach ($users as $user) {
            if (auth()->guard('user')->user()->id == $user->id) {
                continue;
            }

            Event::dispatch('settings.user.delete.before', $user->id);

            $this->userRepository->delete($user->id);

            Event::dispatch('settings.user.delete.after', $user->id);

            $count++;
        }

        if (! $count) {
            return response()->json([
                'message' => trans('admin::app.settings.users.index.mass-delete-failed'),
            ], 400);
        }

        return response()->json([
            'message' => trans('admin::app.settings.users.index.mass-delete-success'),
        ]);
    }

    /**
     * @param  array<int|string>  $sourceIds
     * @param  array<int|string>  $organizationIds
     * @return array{source_ids: array<int>, organization_ids: array<int>}|JsonResponse
     */
    protected function validateUserAssignments(int $roleId, array $sourceIds, array $organizationIds): array|JsonResponse
    {
        if (! $this->sourceAccessService->userSourcesValidForRole($roleId, $sourceIds)) {
            return new JsonResponse([
                'message' => trans('admin::app.settings.users.index.create.source-role-mismatch'),
                'errors'  => [
                    'source_ids' => [trans('admin::app.settings.users.index.create.source-role-mismatch')],
                ],
            ], 422);
        }

        if (! $this->sourceAccessService->userOrganizationsValidForRole($roleId, $organizationIds)) {
            return new JsonResponse([
                'message' => trans('admin::app.settings.users.index.create.company-role-mismatch'),
                'errors'  => [
                    'organization_ids' => [trans('admin::app.settings.users.index.create.company-role-mismatch')],
                ],
            ], 422);
        }

        return [
            'source_ids'        => $this->sourceAccessService->filterUserSourceIdsForRole($roleId, $sourceIds),
            'organization_ids'  => $this->sourceAccessService->filterUserOrganizationIdsForRole($roleId, $organizationIds),
        ];
    }

    /**
     * @param  array<int|string>  $roleIds
     * @return array<int>
     */
    protected function normalizedRoleIds(array $roleIds, mixed $fallbackRoleId = null): array
    {
        $normalized = collect($roleIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (empty($normalized) && (int) $fallbackRoleId > 0) {
            $normalized = [(int) $fallbackRoleId];
        }

        return $normalized;
    }
}
