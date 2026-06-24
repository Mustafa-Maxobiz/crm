<?php

namespace Webkul\Admin\Http\Controllers\Settings;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Event;
use Illuminate\View\View;
use Webkul\Admin\DataGrids\Settings\RoleDataGrid;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Contact\Repositories\OrganizationRepository;
use Webkul\Lead\Repositories\SourceRepository;
use Webkul\User\Repositories\RoleRepository;

class RoleController extends Controller
{
    public function __construct(
        protected RoleRepository $roleRepository,
        protected SourceRepository $sourceRepository,
        protected OrganizationRepository $organizationRepository,
    ) {}

    public function index(): View|JsonResponse
    {
        if (request()->ajax()) {
            return datagrid(RoleDataGrid::class)->process();
        }

        return view('admin::settings.roles.index');
    }

    public function create(): View
    {
        return view('admin::settings.roles.create', $this->getAssignmentFormData());
    }

    public function store(): RedirectResponse
    {
        $this->validate(request(), [
            'name'            => 'required',
            'permission_type' => 'required|in:all,custom',
            'description'     => 'required',
        ]);

        if (request('permission_type') == 'custom') {
            $this->validate(request(), [
                'permissions' => 'required',
            ]);
        }

        Event::dispatch('settings.role.create.before');

        $data = request()->only([
            'name',
            'description',
            'permission_type',
            'permissions',
        ]);

        $role = $this->roleRepository->create($data);

        $this->syncRoleAssignments($role);

        Event::dispatch('settings.role.create.after', $role);

        session()->flash('success', trans('admin::app.settings.roles.index.create-success'));

        return redirect()->route('admin.settings.roles.index');
    }

    public function edit(int $id): View
    {
        $role = $this->roleRepository->with(['sources', 'organizations'])->findOrFail($id);

        return view('admin::settings.roles.edit', array_merge($this->getAssignmentFormData(), [
            'role'                    => $role,
            'assignedSourceIds'       => $role->sources->pluck('id')->all(),
            'assignedOrganizationIds' => $role->organizations->pluck('id')->all(),
        ]));
    }

    public function update(int $id): RedirectResponse
    {
        $this->validate(request(), [
            'name'            => 'required',
            'permission_type' => 'required|in:all,custom',
            'description'     => 'required',
            'permissions'     => 'required_if:permission_type,custom',
        ]);

        Event::dispatch('settings.role.update.before', $id);

        $data = array_merge(request()->only([
            'name',
            'description',
            'permission_type',
        ]), [
            'permissions' => request()->has('permissions') ? request('permissions') : [],
        ]);

        $role = $this->roleRepository->update($data, $id);

        $this->syncRoleAssignments($role);

        Event::dispatch('settings.role.update.after', $role);

        session()->flash('success', trans('admin::app.settings.roles.index.update-success'));

        return redirect()->back();
    }

    public function destroy(int $id): JsonResponse
    {
        $response = [
            'responseCode' => 400,
        ];

        $role = $this->roleRepository->findOrFail($id);

        if ($role->users && $role->users->count() >= 1) {
            $response['message'] = trans('admin::app.settings.roles.index.being-used');

            session()->flash('error', $response['message']);
        } elseif ($this->roleRepository->count() == 1) {
            $response['message'] = trans('admin::app.settings.roles.index.last-delete-error');

            session()->flash('error', $response['message']);
        } else {
            try {
                Event::dispatch('settings.role.delete.before', $id);

                if (auth()->guard('user')->user()->role_id == $id) {
                    $response['message'] = trans('admin::app.settings.roles.index.current-role-delete-error');
                } else {
                    $this->roleRepository->delete($id);

                    Event::dispatch('settings.role.delete.after', $id);

                    $message = trans('admin::app.settings.roles.index.delete-success');

                    $response = [
                        'responseCode' => 200,
                        'message'      => $message,
                    ];

                    session()->flash('success', $message);
                }
            } catch (\Exception $exception) {
                $message = $exception->getMessage();

                $response['message'] = $message;

                session()->flash('error', $message);
            }
        }

        return response()->json($response, $response['responseCode']);
    }

    protected function getAssignmentFormData(): array
    {
        return [
            'sources'       => $this->sourceRepository->getModel()->roots()->orderBy('sort_order')->get(['id', 'name']),
            'organizations' => $this->organizationRepository->getModel()->orderBy('name')->get(['id', 'name']),
        ];
    }

    protected function syncRoleAssignments($role): void
    {
        $role->sources()->sync(request()->input('source_ids', []));
        $role->organizations()->sync(request()->input('organization_ids', []));
    }
}
