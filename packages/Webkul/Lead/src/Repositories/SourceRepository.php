<?php

namespace Webkul\Lead\Repositories;

use Webkul\Core\Eloquent\Repository;

class SourceRepository extends Repository
{
    /**
     * Specify Model class name
     *
     * @return mixed
     */
    public function model()
    {
        return 'Webkul\Lead\Contracts\Source';
    }

    public function getRootDropdownOptions(): array
    {
        $query = $this->getModel()->newQuery()->roots();

        $rootIds = app(\Webkul\Lead\Services\SourceAccessService::class)->getEffectiveRootSourceIds();

        if ($rootIds !== null) {
            $query->whereIn('id', $rootIds);
        }

        return $query->get(['name as label', 'id as value'])->toArray();
    }

    public function isLinkedInSource(int $sourceId): bool
    {
        $name = $this->getModel()->newQuery()->whereKey($sourceId)->value('name');

        return strtolower(trim((string) $name)) === 'linkedin';
    }

    public function getAllowedLinkedInSourceIds(): array
    {
        return collect($this->getRootDropdownOptions())
            ->filter(fn ($source) => strtolower(trim($source['label'])) === 'linkedin')
            ->pluck('value')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
