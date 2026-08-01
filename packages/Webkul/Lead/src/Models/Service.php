<?php

namespace Webkul\Lead\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Webkul\Lead\Contracts\Service as ServiceContract;

class Service extends Model implements ServiceContract
{
    protected $table = 'services';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'sort_order',
    ];

    /**
     * The leads that belong to the service.
     */
    public function leads(): BelongsToMany
    {
        return $this->belongsToMany(LeadProxy::modelClass(), 'lead_service');
    }
}
