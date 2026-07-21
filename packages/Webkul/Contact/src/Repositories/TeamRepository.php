<?php

namespace Webkul\Contact\Repositories;

use Webkul\Contact\Contracts\Team;
use Webkul\Core\Eloquent\Repository;

class TeamRepository extends Repository
{
    protected $fieldSearchable = [
        'name',
        'description',
        'organizations.name',
        'user_id',
        'user.name',
    ];

    public function model(): string
    {
        return Team::class;
    }

    public function getDropdownOptions(?int $organizationId = null): array
    {
        $query = $this->getModel()->newQuery()->orderBy('name');

        if ($organizationId) {
            $query->whereHas('organizations', fn ($query) => $query->where('organizations.id', $organizationId));
        }

        $organizationIds = app(\Webkul\Lead\Services\SourceAccessService::class)->getEffectiveOrganizationIds();

        if ($organizationIds !== null) {
            $query->whereHas('organizations', fn ($query) => $query->whereIn('organizations.id', $organizationIds));
        }

        return $query->get(['name as label', 'id as value'])->toArray();
    }
}
