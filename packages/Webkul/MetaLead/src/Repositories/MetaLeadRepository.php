<?php

namespace Webkul\MetaLead\Repositories;

use Webkul\Core\Eloquent\Repository;
use Webkul\MetaLead\Contracts\MetaLead;

class MetaLeadRepository extends Repository
{
    public function model(): string
    {
        return MetaLead::class;
    }

    public function findByLeadgenId(string $leadgenId)
    {
        return $this->findOneByField('leadgen_id', $leadgenId);
    }

    public function syncAssignedUsers(int $metaLeadId, array $userIds): void
    {
        $metaLead = $this->findOrFail($metaLeadId);

        $userIds = collect($userIds)
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $metaLead->users()->sync($userIds);

        if ($metaLead->lead_id && ! empty($userIds)) {
            $metaLead->lead?->update(['user_id' => $userIds[0]]);
        }
    }
}
