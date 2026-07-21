<?php

namespace Webkul\Contact\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Webkul\Contact\Contracts\Team as TeamContract;
use Webkul\User\Models\UserProxy;

class Team extends Model implements TeamContract
{
    protected $fillable = [
        'name',
        'description',
        'user_id',
    ];

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(OrganizationProxy::modelClass(), 'organization_team', 'team_id', 'organization_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass());
    }
}
