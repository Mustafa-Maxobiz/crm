<?php

namespace Webkul\Lead\Repositories;

use Webkul\Core\Eloquent\Repository;

class ServiceRepository extends Repository
{
    /**
     * Specify Model class name.
     *
     * @return mixed
     */
    public function model()
    {
        return 'Webkul\Lead\Contracts\Service';
    }

    /**
     * Dropdown options for selects.
     */
    public function getDropdownOptions(): array
    {
        return $this->getModel()
            ->newQuery()
            ->where('is_show', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id as value', 'name as label'])
            ->map(fn ($row) => [
                'value' => (int) $row->value,
                'label' => $row->label,
            ])
            ->values()
            ->all();
    }
}
