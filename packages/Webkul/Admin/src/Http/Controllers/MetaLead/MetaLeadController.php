<?php

namespace Webkul\Admin\Http\Controllers\MetaLead;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Webkul\Admin\DataGrids\MetaLead\MetaLeadDataGrid;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Requests\MassDestroyRequest;
use Webkul\Admin\Http\Requests\MassUpdateRequest;
use Webkul\MetaLead\Models\MetaLead;
use Webkul\MetaLead\Repositories\MetaLeadRepository;
use Webkul\MetaLead\Services\MetaLeadAccessService;
use Webkul\User\Repositories\UserRepository;

class MetaLeadController extends Controller
{
    public function __construct(
        protected MetaLeadRepository $metaLeadRepository,
        protected MetaLeadAccessService $accessService,
        protected UserRepository $userRepository,
    ) {}

    public function index(): View|JsonResponse
    {
        if (request()->ajax()) {
            return datagrid(MetaLeadDataGrid::class)->process();
        }

        return view('admin::meta-leads.index');
    }

    public function massUpdate(MassUpdateRequest $request): JsonResponse
    {
        $status = $request->input('value');

        if (! in_array($status, MetaLead::STATUSES)) {
            return response()->json(['message' => 'Invalid status'], 422);
        }

        foreach ($request->input('indices') as $id) {
            $metaLead = $this->metaLeadRepository->findOrFail($id);

            if (! $this->accessService->canView($metaLead)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $this->metaLeadRepository->update(['status' => $status], $id);
        }

        return response()->json([
            'message' => trans('admin::app.meta-leads.index.update-status-success'),
        ]);
    }

    public function view(int $id): View
    {
        $metaLead = $this->metaLeadRepository->with('users')->findOrFail($id);

        $this->authorizeMetaLeadAccess($metaLead);

        $users = collect();
        $assignedUserIds = $metaLead->users->pluck('id')->all();

        if ($this->accessService->isAdmin()) {
            $users = $this->userRepository
                ->getModel()
                ->where('status', 1)
                ->orderBy('name')
                ->get(['id', 'name', 'email']);
        }

        return view('admin::meta-leads.view', [
            'metaLead'        => $metaLead,
            'users'           => $users,
            'assignedUserIds' => $assignedUserIds,
            'canAssignUsers'  => $this->accessService->isAdmin(),
        ]);
    }

    public function updateStatus(int $id): RedirectResponse
    {
        $this->authorizeMetaLeadAccess($id);

        $status = request('status');

        if (! in_array($status, MetaLead::STATUSES)) {
            session()->flash('error', trans('admin::app.meta-leads.index.update-status-error'));

            return redirect()->back();
        }

        $this->metaLeadRepository->update(['status' => $status], $id);

        session()->flash('success', trans('admin::app.meta-leads.index.update-status-success'));

        return redirect()->route('admin.meta_leads.view', $id);
    }

    public function updateUsers(int $id): RedirectResponse
    {
        if (! $this->accessService->isAdmin()) {
            abort(403);
        }

        $this->metaLeadRepository->findOrFail($id);

        $this->metaLeadRepository->syncAssignedUsers($id, request()->input('user_ids', []));

        session()->flash('success', trans('admin::app.meta-leads.view.assign-users-success'));

        return redirect()->route('admin.meta_leads.view', $id);
    }

    public function destroy(int $id): JsonResponse|RedirectResponse
    {
        $this->authorizeMetaLeadAccess($id);

        try {
            $this->metaLeadRepository->delete($id);

            if (request()->ajax()) {
                return response()->json([
                    'message' => trans('admin::app.meta-leads.index.delete-success'),
                ]);
            }

            session()->flash('success', trans('admin::app.meta-leads.index.delete-success'));

            return redirect()->route('admin.meta_leads.index');
        } catch (\Exception) {
            if (request()->ajax()) {
                return response()->json([
                    'message' => trans('admin::app.meta-leads.index.delete-failed'),
                ], 400);
            }

            session()->flash('error', trans('admin::app.meta-leads.index.delete-failed'));

            return redirect()->back();
        }
    }

    public function massDestroy(MassDestroyRequest $request): JsonResponse
    {
        try {
            foreach ($request->input('indices') as $id) {
                $metaLead = $this->metaLeadRepository->findOrFail($id);

                if (! $this->accessService->canView($metaLead)) {
                    return response()->json(['message' => 'Unauthorized'], 403);
                }

                $this->metaLeadRepository->delete($id);
            }

            return response()->json([
                'message' => trans('admin::app.meta-leads.index.delete-success'),
            ]);
        } catch (\Exception) {
            return response()->json([
                'message' => trans('admin::app.meta-leads.index.delete-failed'),
            ], 400);
        }
    }

    protected function authorizeMetaLeadAccess(int|MetaLead $metaLead): MetaLead
    {
        if (is_int($metaLead)) {
            $metaLead = $this->metaLeadRepository->findOrFail($metaLead);
        }

        if (! $this->accessService->canView($metaLead)) {
            abort(403);
        }

        return $metaLead;
    }
}
