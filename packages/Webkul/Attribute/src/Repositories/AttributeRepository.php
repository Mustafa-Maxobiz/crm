<?php

namespace Webkul\Attribute\Repositories;

use Illuminate\Container\Container;
use Illuminate\Support\Str;
use Webkul\Lead\Services\SourceAccessService;
use Webkul\Core\Eloquent\Repository;

class AttributeRepository extends Repository
{
    /**
     * Create a new repository instance.
     *
     * @return void
     */
    public function __construct(
        protected AttributeOptionRepository $attributeOptionRepository,
        Container $container
    ) {
        parent::__construct($container);
    }

    /**
     * Specify Model class name
     *
     * @return mixed
     */
    public function model()
    {
        return 'Webkul\Attribute\Contracts\Attribute';
    }

    /**
     * @return \Webkul\Attribute\Contracts\Attribute
     */
    public function create(array $data)
    {
        $options = isset($data['options']) ? $data['options'] : [];

        $attribute = $this->model->create($data);

        if (in_array($attribute->type, ['select', 'multiselect', 'checkbox']) && count($options)) {
            $sortOrder = 1;

            foreach ($options as $optionInputs) {
                $this->attributeOptionRepository->create(array_merge([
                    'attribute_id' => $attribute->id,
                    'sort_order'   => $sortOrder++,
                ], $optionInputs));
            }
        }

        return $attribute;
    }

    /**
     * @param  int  $id
     * @param  string  $attribute
     * @return \Webkul\Attribute\Contracts\Attribute
     */
    public function update(array $data, $id, $attribute = 'id')
    {
        $attribute = $this->find($id);

        $attribute->update($data);

        if (! in_array($attribute->type, ['select', 'multiselect', 'checkbox'])) {
            return $attribute;
        }

        if (! isset($data['options'])) {
            return $attribute;
        }

        foreach ($data['options'] as $optionId => $optionInputs) {
            $isNew = $optionInputs['isNew'] == 'true';

            if ($isNew) {
                $this->attributeOptionRepository->create(array_merge([
                    'attribute_id' => $attribute->id,
                ], $optionInputs));
            } else {
                $isDelete = $optionInputs['isDelete'] == 'true';

                if ($isDelete) {
                    $this->attributeOptionRepository->delete($optionId);
                } else {
                    $this->attributeOptionRepository->update($optionInputs, $optionId);
                }
            }
        }

        return $attribute;
    }

    /**
     * @param  string  $code
     * @return \Webkul\Attribute\Contracts\Attribute
     */
    public function getAttributeByCode($code)
    {
        static $attributes = [];

        if (array_key_exists($code, $attributes)) {
            return $attributes[$code];
        }

        return $attributes[$code] = $this->findOneByField('code', $code);
    }

    /**
     * @param  int  $lookup
     * @param  string  $query
     * @param  array  $columns
     * @return array
     */
    public function getLookUpOptions($lookup, $query = '', $columns = [])
    {
        $lookup = config('attribute_lookups.'.$lookup);

        if (! count($columns)) {
            $columns = [($lookup['value_column'] ?? 'id').' as id', ($lookup['label_column'] ?? 'name').' as name'];
        }

        if (Str::contains($lookup['repository'], 'UserRepository')) {
            $userRepository = app($lookup['repository'])->where('status', 1);

            $currentUser = auth()->guard('user')->user();

            if ($currentUser?->view_permission === 'group') {
                $query = urldecode($query);

                $userIds = bouncer()->getAuthorizedUserIds();

                return $userRepository
                    ->when(! empty($userIds), fn ($queryBuilder) => $queryBuilder->whereIn('users.id', $userIds))
                    ->when(! empty($query), fn ($queryBuilder) => $queryBuilder->where('users.name', 'like', "%{$query}%"))
                    ->get();
            } elseif ($currentUser?->view_permission === 'individual') {
                return $userRepository->where('users.id', $currentUser->id);
            }

            return $userRepository->where('users.name', 'like', '%'.urldecode($query).'%')->get();
        }

        // Handle scope if specified (e.g., 'roots' / 'children' for sources)
        $repository = app($lookup['repository']);

        if (isset($lookup['scope'])) {
            $queryBuilder = $repository->getModel()->newQuery()->{$lookup['scope']}();

            if (Str::contains($lookup['repository'], 'SourceRepository')) {
                if ($lookup['scope'] === 'roots') {
                    $this->applyRootSourceScopeToQuery($queryBuilder);
                } else {
                    $this->applyExpandedSourceScopeToQuery($queryBuilder);
                }
            }

            return $queryBuilder
                ->where($lookup['label_column'] ?? 'name', 'like', '%'.urldecode($query).'%')
                ->select($columns)
                ->get();
        }

        if (Str::contains($lookup['repository'], 'SourceRepository')) {
            $queryBuilder = $repository->getModel()->newQuery();

            $this->applyExpandedSourceScopeToQuery($queryBuilder);

            return $queryBuilder
                ->where($lookup['label_column'] ?? 'name', 'like', '%'.urldecode($query).'%')
                ->select($columns)
                ->get();
        }

        if (Str::contains($lookup['repository'], 'OrganizationRepository')) {
            $queryBuilder = $repository->getModel()->newQuery();

            $this->applyOrganizationScopeToQuery($queryBuilder);

            return $queryBuilder
                ->where($lookup['label_column'] ?? 'name', 'like', '%'.urldecode($query).'%')
                ->select($columns)
                ->get();
        }

        return $repository->findWhere([
            [$lookup['label_column'] ?? 'name', 'like', '%'.urldecode($query).'%'],
        ], $columns);
    }

    /**
     * @param  string  $lookup
     * @param  int|array  $entityId
     * @param  array  $columns
     * @return mixed
     */
    public function getLookUpEntity($lookup, $entityId = null, $columns = [])
    {
        if (! $entityId) {
            return;
        }

        $lookup = config('attribute_lookups.'.$lookup);

        if (! count($columns)) {
            $columns = [($lookup['value_column'] ?? 'id').' as id', ($lookup['label_column'] ?? 'name').' as name'];
        }

        if (is_array($entityId)) {
            return app($lookup['repository'])->findWhereIn(
                'id',
                $entityId,
                $columns
            );
        } else {
            return app($lookup['repository'])->find($entityId, $columns);
        }
    }

    protected function applyRootSourceScopeToQuery($queryBuilder): void
    {
        $rootIds = app(SourceAccessService::class)->getEffectiveRootSourceIds();

        if ($rootIds !== null) {
            $queryBuilder->whereIn('lead_sources.id', $rootIds);
        }
    }

    protected function applyExpandedSourceScopeToQuery($queryBuilder): void
    {
        $sourceIds = app(SourceAccessService::class)->getExpandedSourceIds();

        if ($sourceIds !== null) {
            $queryBuilder->whereIn('lead_sources.id', $sourceIds);
        }
    }

    /**
     * @deprecated Use applyRootSourceScopeToQuery / applyExpandedSourceScopeToQuery.
     */
    protected function applySourceScopeToQuery($queryBuilder): void
    {
        $this->applyRootSourceScopeToQuery($queryBuilder);
    }

    protected function applyOrganizationScopeToQuery($queryBuilder): void
    {
        $organizationIds = app(SourceAccessService::class)->getEffectiveOrganizationIds();

        if ($organizationIds !== null) {
            $queryBuilder->whereIn('organizations.id', $organizationIds);
        }
    }
}
