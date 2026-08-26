<?php

namespace Webkul\Admin\Http\Controllers\Settings;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Webkul\Admin\DataGrids\Settings\LeadAttributeOptionDataGrid;
use Webkul\Admin\DataGrids\Settings\ServiceDataGrid;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Attribute\Repositories\AttributeOptionRepository;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Lead\Repositories\ServiceRepository;
use Webkul\Lead\Services\MeetingHandoffService;

class LeadAttributeOptionController extends Controller
{
    /**
     * Supported settings resources keyed by route segment.
     */
    protected array $resources = [
        'industries' => [
            'attribute_code' => 'industry',
            'permission'     => 'settings.lead.industries',
            'lang'           => 'admin::app.settings.industries',
            'breadcrumb'     => 'settings.industries',
            'storage'        => 'attribute_options',
        ],
        'services_offered' => [
            'attribute_code' => null,
            'permission'     => 'settings.lead.services_offered',
            'lang'           => 'admin::app.settings.services_offered',
            'breadcrumb'     => 'settings.services_offered',
            'storage'        => 'services',
        ],
    ];

    public function __construct(
        protected AttributeRepository $attributeRepository,
        protected AttributeOptionRepository $attributeOptionRepository,
        protected ServiceRepository $serviceRepository,
        protected MeetingHandoffService $meetingHandoffService,
    ) {}

    /**
     * Display a listing of the options.
     */
    public function index(): View|JsonResponse
    {
        $resource = $this->resourceConfig();

        if (request()->ajax()) {
            if ($this->usesServicesTable()) {
                return datagrid(ServiceDataGrid::class)
                    ->setPermissionPrefix($resource['permission'])
                    ->setRoutePrefix($this->routePrefix())
                    ->process();
            }

            $attribute = $this->resolveAttribute($resource['attribute_code']);

            return datagrid(LeadAttributeOptionDataGrid::class)
                ->setAttributeId((int) $attribute->id)
                ->setPermissionPrefix($resource['permission'])
                ->setRoutePrefix($this->routePrefix())
                ->process();
        }

        return view('admin::settings.lead-attribute-options.index', [
            'resourceKey'         => $this->resourceKey(),
            'routePrefix'         => $this->routePrefix(),
            'permission'          => $resource['permission'],
            'lang'                => $resource['lang'],
            'breadcrumb'          => $resource['breadcrumb'],
            'attribute'           => $this->usesServicesTable()
                ? null
                : $this->resolveAttribute($resource['attribute_code']),
            'updateRouteTemplate' => str_replace(
                '999999999',
                '__ID__',
                route($this->routePrefix().'.update', ['id' => 999999999])
            ),
            'assignableMeetingOwners' => $this->usesServicesTable()
                ? $this->meetingHandoffService->getAllActiveMeetingOwners()
                : [],
            'showServiceOwnerAssignment' => $this->usesServicesTable(),
        ]);
    }

    /**
     * Store a newly created option.
     */
    public function store(): JsonResponse
    {
        $resource = $this->resourceConfig();

        if ($this->usesServicesTable()) {
            return $this->storeService($resource);
        }

        $attribute = $this->resolveAttribute($resource['attribute_code']);

        $this->validate(request(), [
            'name' => [
                'required',
                'max:255',
                Rule::unique('attribute_options', 'name')->where(
                    fn ($query) => $query->where('attribute_id', $attribute->id)
                ),
            ],
            'sort_order' => ['nullable', 'integer', 'min:1'],
        ]);

        Event::dispatch('settings.'.$this->resourceKey().'.create.before');

        $sortOrder = request()->filled('sort_order')
            ? (int) request('sort_order')
            : ((int) DB::table('attribute_options')
                ->where('attribute_id', $attribute->id)
                ->max('sort_order') + 1);

        $option = DB::transaction(function () use ($attribute, $sortOrder) {
            $this->swapAttributeOptionSortOrder((int) $attribute->id, $sortOrder);

            return $this->attributeOptionRepository->create([
                'attribute_id' => $attribute->id,
                'name'         => request('name'),
                'sort_order'   => $sortOrder,
            ]);
        });

        Event::dispatch('settings.'.$this->resourceKey().'.create.after', $option);

        return new JsonResponse([
            'data'    => $option,
            'message' => trans($resource['lang'].'.index.create-success'),
        ]);
    }

    /**
     * Show the form for editing the specified option.
     */
    public function edit(int $id): JsonResponse
    {
        if ($this->usesServicesTable()) {
            $service = $this->serviceRepository
                ->getModel()
                ->newQuery()
                ->with('users')
                ->findOrFail($id);

            return new JsonResponse([
                'data' => [
                    'id'         => $service->id,
                    'name'       => $service->name,
                    'sort_order' => $service->sort_order,
                    'is_show'    => (bool) $service->is_show,
                    'user_ids'   => $service->users->pluck('id')->map(fn ($uid) => (int) $uid)->values(),
                ],
            ]);
        }

        $option = $this->findAttributeOptionOrFail($id);

        return new JsonResponse([
            'data' => $option,
        ]);
    }

    /**
     * Update the specified option.
     */
    public function update(int $id): JsonResponse
    {
        $resource = $this->resourceConfig();

        if ($this->usesServicesTable()) {
            return $this->updateService($id, $resource);
        }

        $option = $this->findAttributeOptionOrFail($id);

        $this->validate(request(), [
            'name' => [
                'required',
                'max:255',
                Rule::unique('attribute_options', 'name')
                    ->where(fn ($query) => $query->where('attribute_id', $option->attribute_id))
                    ->ignore($option->id),
            ],
            'sort_order' => ['nullable', 'integer', 'min:1'],
        ]);

        Event::dispatch('settings.'.$this->resourceKey().'.update.before', $id);

        $newSortOrder = request()->filled('sort_order')
            ? (int) request('sort_order')
            : (int) $option->sort_order;

        $oldSortOrder = (int) $option->sort_order;

        DB::transaction(function () use ($option, $id, $newSortOrder, $oldSortOrder) {
            if ($newSortOrder !== $oldSortOrder) {
                $this->swapAttributeOptionSortOrder(
                    (int) $option->attribute_id,
                    $newSortOrder,
                    $id,
                    $oldSortOrder
                );
            }

            $this->attributeOptionRepository->update([
                'name'       => request('name'),
                'sort_order' => $newSortOrder,
            ], $id);
        });

        $option = $this->attributeOptionRepository->find($id);

        Event::dispatch('settings.'.$this->resourceKey().'.update.after', $option);

        return new JsonResponse([
            'data'    => $option,
            'message' => trans($resource['lang'].'.index.update-success'),
        ]);
    }

    /**
     * Remove the specified option.
     */
    public function destroy(int $id): JsonResponse
    {
        $resource = $this->resourceConfig();

        if ($this->usesServicesTable()) {
            try {
                Event::dispatch('settings.'.$this->resourceKey().'.delete.before', $id);

                $this->serviceRepository->delete($id);

                Event::dispatch('settings.'.$this->resourceKey().'.delete.after', $id);

                return new JsonResponse([
                    'message' => trans($resource['lang'].'.index.delete-success'),
                ]);
            } catch (\Exception $exception) {
                return new JsonResponse([
                    'message' => trans($resource['lang'].'.index.delete-failed'),
                ], 400);
            }
        }

        $this->findAttributeOptionOrFail($id);

        try {
            Event::dispatch('settings.'.$this->resourceKey().'.delete.before', $id);

            $this->attributeOptionRepository->delete($id);

            Event::dispatch('settings.'.$this->resourceKey().'.delete.after', $id);

            return new JsonResponse([
                'message' => trans($resource['lang'].'.index.delete-success'),
            ]);
        } catch (\Exception $exception) {
            return new JsonResponse([
                'message' => trans($resource['lang'].'.index.delete-failed'),
            ], 400);
        }
    }

    protected function storeService(array $resource): JsonResponse
    {
        $this->validate(request(), [
            'name' => [
                'required',
                'max:255',
                Rule::unique('services', 'name'),
            ],
            'sort_order' => ['nullable', 'integer', 'min:1'],
            'is_show'    => ['nullable', 'boolean'],
            'user_ids'   => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ]);

        $userIds = $this->validatedServiceUserIds();

        Event::dispatch('settings.'.$this->resourceKey().'.create.before');

        $sortOrder = request()->filled('sort_order')
            ? (int) request('sort_order')
            : ((int) DB::table('services')->max('sort_order') + 1);

        $isShow = (bool) request('is_show', false);

        $service = DB::transaction(function () use ($sortOrder, $userIds, $isShow) {
            $this->swapServiceSortOrder($sortOrder);

            $service = $this->serviceRepository->create([
                'name'       => request('name'),
                'sort_order' => $sortOrder,
                'is_show'    => $isShow,
            ]);

            $service->users()->sync($userIds);

            return $service;
        });

        Event::dispatch('settings.'.$this->resourceKey().'.create.after', $service);

        return new JsonResponse([
            'data'    => $service,
            'message' => trans($resource['lang'].'.index.create-success'),
        ]);
    }

    protected function updateService(int $id, array $resource): JsonResponse
    {
        $service = $this->serviceRepository->findOrFail($id);

        $this->validate(request(), [
            'name' => [
                'required',
                'max:255',
                Rule::unique('services', 'name')->ignore($service->id),
            ],
            'sort_order' => ['nullable', 'integer', 'min:1'],
            'is_show'    => ['nullable', 'boolean'],
            'user_ids'   => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ]);

        $userIds = $this->validatedServiceUserIds();

        Event::dispatch('settings.'.$this->resourceKey().'.update.before', $id);

        $newSortOrder = request()->filled('sort_order')
            ? (int) request('sort_order')
            : (int) $service->sort_order;

        $oldSortOrder = (int) $service->sort_order;
        $isShow = (bool) request('is_show', $service->is_show);

        DB::transaction(function () use ($service, $id, $newSortOrder, $oldSortOrder, $userIds, $isShow) {
            if ($newSortOrder !== $oldSortOrder) {
                $this->swapServiceSortOrder($newSortOrder, $id, $oldSortOrder);
            }

            $this->serviceRepository->update([
                'name'       => request('name'),
                'sort_order' => $newSortOrder,
                'is_show'    => $isShow,
            ], $id);

            $this->serviceRepository->find($id)->users()->sync($userIds);
        });

        $service = $this->serviceRepository->find($id);

        Event::dispatch('settings.'.$this->resourceKey().'.update.after', $service);

        return new JsonResponse([
            'data'    => $service,
            'message' => trans($resource['lang'].'.index.update-success'),
        ]);
    }

    /**
     * @return array<int>
     */
    protected function validatedServiceUserIds(): array
    {
        $userIds = collect(request('user_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        foreach ($userIds as $userId) {
            if (! $this->meetingHandoffService->isActiveMeetingOwnerId($userId)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'user_ids' => ['Each selected user must be an active Admin or Lead Closer user.'],
                ]);
            }
        }

        return $userIds;
    }

    public function toggleVisibility(int $id): JsonResponse
    {
        $service = $this->serviceRepository->findOrFail($id);

        $service->update(['is_show' => ! $service->is_show]);

        return new JsonResponse([
            'is_show' => (bool) $service->is_show,
            'message' => $service->is_show ? 'Service is now visible in dropdowns.' : 'Service is now hidden from dropdowns.',
        ]);
    }

    protected function usesServicesTable(): bool
    {
        return ($this->resourceConfig()['storage'] ?? '') === 'services';
    }

    protected function resourceKey(): string
    {
        $routeName = (string) request()->route()?->getName();

        if (str_contains($routeName, 'services_offered')) {
            return 'services_offered';
        }

        return 'industries';
    }

    protected function routePrefix(): string
    {
        return 'admin.settings.'.$this->resourceKey();
    }

    protected function resourceConfig(): array
    {
        return $this->resources[$this->resourceKey()];
    }

    protected function resolveAttribute(string $code)
    {
        $attribute = $this->attributeRepository->findOneWhere([
            'code'        => $code,
            'entity_type' => 'leads',
        ]);

        abort_unless($attribute, 404, 'Attribute not found.');

        return $attribute;
    }

    protected function findAttributeOptionOrFail(int $id)
    {
        $resource = $this->resourceConfig();
        $attribute = $this->resolveAttribute($resource['attribute_code']);

        $option = $this->attributeOptionRepository->findOrFail($id);

        abort_unless((int) $option->attribute_id === (int) $attribute->id, 404);

        return $option;
    }

    protected function swapAttributeOptionSortOrder(
        int $attributeId,
        int $targetSortOrder,
        ?int $ignoreOptionId = null,
        ?int $fallbackSortOrder = null
    ): void {
        $query = DB::table('attribute_options')
            ->where('attribute_id', $attributeId)
            ->where('sort_order', $targetSortOrder);

        if ($ignoreOptionId) {
            $query->where('id', '!=', $ignoreOptionId);
        }

        $occupantId = $query->value('id');

        if (! $occupantId) {
            return;
        }

        $replacementSortOrder = $fallbackSortOrder;

        if ($replacementSortOrder === null) {
            $replacementSortOrder = ((int) DB::table('attribute_options')
                ->where('attribute_id', $attributeId)
                ->max('sort_order')) + 1;
        }

        DB::table('attribute_options')
            ->where('id', $occupantId)
            ->update(['sort_order' => $replacementSortOrder]);
    }

    protected function swapServiceSortOrder(
        int $targetSortOrder,
        ?int $ignoreServiceId = null,
        ?int $fallbackSortOrder = null
    ): void {
        $query = DB::table('services')->where('sort_order', $targetSortOrder);

        if ($ignoreServiceId) {
            $query->where('id', '!=', $ignoreServiceId);
        }

        $occupantId = $query->value('id');

        if (! $occupantId) {
            return;
        }

        $replacementSortOrder = $fallbackSortOrder;

        if ($replacementSortOrder === null) {
            $replacementSortOrder = ((int) DB::table('services')->max('sort_order')) + 1;
        }

        DB::table('services')
            ->where('id', $occupantId)
            ->update(['sort_order' => $replacementSortOrder]);
    }
}
