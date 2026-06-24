<?php

namespace Webkul\MetaLead\Services;

use Illuminate\Database\Eloquent\Builder;
use Webkul\MetaLead\Contracts\MetaLead as MetaLeadContract;

class MetaLeadAccessService
{
    public function isAdmin(): bool
    {
        $user = auth()->guard('user')->user();

        return $user && $user->role?->permission_type === 'all';
    }

    public function canView(MetaLeadContract $metaLead): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $userId = auth()->guard('user')->id();

        if (! $userId) {
            return false;
        }

        return $metaLead->users()->where('users.id', $userId)->exists();
    }

    public function applyVisibilityScope(Builder $query): Builder
    {
        if ($this->isAdmin()) {
            return $query;
        }

        $userId = auth()->guard('user')->id();

        return $query->whereHas('users', fn ($userQuery) => $userQuery->where('users.id', $userId));
    }
}
