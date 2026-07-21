<?php

namespace Webkul\Admin\Http\Controllers\Contact;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\View\View;
use Webkul\Admin\DataGrids\Contact\TeamDataGrid;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Requests\MassDestroyRequest;
use Webkul\Contact\Repositories\OrganizationRepository;
use Webkul\Contact\Repositories\TeamRepository;
use Webkul\User\Repositories\UserRepository;

class TeamController extends Controller
{
    public function __construct(
        protected TeamRepository $teamRepository,
        protected OrganizationRepository $organizationRepository,
        protected UserRepository $userRepository,
    ) {}

    public function index(): View|JsonResponse
    {
        if (request()->ajax()) {
            return datagrid(TeamDataGrid::class)->process();
        }

        return view('admin::contacts.teams.index');
    }

    public function create(): View
    {
        return view('admin::contacts.teams.create', [
            'organizations'     => $this->getAccessibleOrganizations(),
            'users'             => $this->userRepository->all(['id', 'name']),
            'organization_ids'  => request()->integer('organization_id') ? [request()->integer('organization_id')] : [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateTeam($request);
        $organizationIds = $data['organization_ids'];

        unset($data['organization_ids']);

        Event::dispatch('contacts.team.create.before');

        $team = DB::transaction(function () use ($data, $organizationIds) {
            $team = $this->teamRepository->create($data);

            $team->organizations()->sync($organizationIds);

            return $team;
        });

        Event::dispatch('contacts.team.create.after', $team);

        session()->flash('success', trans('admin::app.contacts.teams.index.create-success'));

        return redirect()->route('admin.contacts.teams.index');
    }

    public function edit(int $id): View
    {
        $team = $this->teamRepository->findOrFail($id);

        return view('admin::contacts.teams.edit', [
            'team'          => $team,
            'organizations' => $this->getAccessibleOrganizations(),
            'users'         => $this->userRepository->all(['id', 'name']),
            'organization_ids' => $team->organizations()->pluck('organizations.id')->all(),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $data = $this->validateTeam($request, $id);
        $organizationIds = $data['organization_ids'];

        unset($data['organization_ids']);

        Event::dispatch('contacts.team.update.before', $id);

        $team = DB::transaction(function () use ($data, $id, $organizationIds) {
            $team = $this->teamRepository->update($data, $id);

            $team->organizations()->sync($organizationIds);

            return $team;
        });

        Event::dispatch('contacts.team.update.after', $team);

        session()->flash('success', trans('admin::app.contacts.teams.index.update-success'));

        return redirect()->route('admin.contacts.teams.index');
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            Event::dispatch('contacts.team.delete.before', $id);

            $this->teamRepository->delete($id);

            Event::dispatch('contacts.team.delete.after', $id);

            return response()->json([
                'message' => trans('admin::app.contacts.teams.index.delete-success'),
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                'message' => trans('admin::app.contacts.teams.index.delete-failed'),
            ], 400);
        }
    }

    public function massDestroy(MassDestroyRequest $massDestroyRequest): JsonResponse
    {
        $teams = $this->teamRepository->findWhereIn('id', $massDestroyRequest->input('indices'));

        foreach ($teams as $team) {
            Event::dispatch('contacts.team.delete.before', $team);

            $this->teamRepository->delete($team->id);

            Event::dispatch('contacts.team.delete.after', $team);
        }

        return response()->json([
            'message' => trans('admin::app.contacts.teams.index.delete-success'),
        ]);
    }

    private function validateTeam(Request $request, ?int $teamId = null): array
    {
        $data = $request->validate([
            'name'                 => ['required', 'string', 'max:100'],
            'description'          => ['nullable', 'string', 'max:1000'],
            'organization_ids'     => ['required', 'array', 'min:1'],
            'organization_ids.*'   => ['required', 'integer', 'exists:organizations,id'],
            'user_id'              => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $data['organization_ids'] = array_values(array_unique(array_map('intval', $data['organization_ids'])));

        $allowedOrganizationIds = collect($this->organizationRepository->getDropdownOptions())
            ->pluck('value')
            ->map(fn ($value) => (int) $value)
            ->all();

        if (array_diff($data['organization_ids'], $allowedOrganizationIds)) {
            abort(403, trans('admin::app.leads.company-access-denied'));
        }

        $existsQuery = $this->teamRepository->getModel()->newQuery()
            ->where('name', $data['name'])
            ->whereHas('organizations', fn ($query) => $query->whereIn('organizations.id', $data['organization_ids']));

        if ($teamId) {
            $existsQuery->where('id', '<>', $teamId);
        }

        if ($existsQuery->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'name' => [trans('admin::app.contacts.teams.unique-name')],
            ]);
        }

        $data['user_id'] = $data['user_id'] ?: null;
        $data['description'] = $data['description'] ?: null;

        return $data;
    }

    private function getAccessibleOrganizations()
    {
        $ids = collect($this->organizationRepository->getDropdownOptions())
            ->pluck('value')
            ->all();

        return $this->organizationRepository->findWhereIn('id', $ids);
    }
}
