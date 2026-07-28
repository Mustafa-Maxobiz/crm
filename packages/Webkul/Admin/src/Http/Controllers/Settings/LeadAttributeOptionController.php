<?php

namespace Webkul\Admin\Http\Controllers\Settings;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Webkul\Admin\DataGrids\Settings\LeadAttributeOptionDataGrid;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Attribute\Repositories\AttributeOptionRepository;
use Webkul\Attribute\Repositories\AttributeRepository;

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
        ],
        'services_offered' => [
            'attribute_code' => 'service_offered',
            'permission'     => 'settings.lead.services_offered',
            'lang'           => 'admin::app.settings.services_offered',
            'breadcrumb'     => 'settings.services_offered',
        ],
    ];

    public function __construct(
        protected AttributeRepository $attributeRepository,
        protected AttributeOptionRepository $attributeOptionRepository,
    ) {}

    /**
     * Display a listing of the options.
     */
    public function index(): View|JsonResponse
    {
        $resource = $this->resourceConfig();
        $attribute = $this->resolveAttribute($resource['attribute_code']);

        if (request()->ajax()) {
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
            'attribute'           => $attribute,
            'updateRouteTemplate' => str_replace(
                '999999999',
                '__ID__',
                route($this->routePrefix().'.update', ['id' => 999999999])
            ),
        ]);
    }

    /**
     * Store a newly created option.
     */
    public function store(): JsonResponse
    {
        $resource = $this->resourceConfig();
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
            $this->swapSortOrderOccupant((int) $attribute->id, $sortOrder);

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
        $option = $this->findOptionOrFail($id);

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
        $option = $this->findOptionOrFail($id);

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
                $this->swapSortOrderOccupant(
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
        $this->findOptionOrFail($id);

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

    protected function findOptionOrFail(int $id)
    {
        $resource = $this->resourceConfig();
        $attribute = $this->resolveAttribute($resource['attribute_code']);

        $option = $this->attributeOptionRepository->findOrFail($id);

        abort_unless((int) $option->attribute_id === (int) $attribute->id, 404);

        return $option;
    }

    /**
     * If another option already owns the target sort order, move it to the
     * previous order of the option being placed there (swap).
     */
    protected function swapSortOrderOccupant(
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
}
