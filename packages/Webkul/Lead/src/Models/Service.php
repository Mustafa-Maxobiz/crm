<?php

namespace Webkul\Lead\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Webkul\Lead\Contracts\Service as ServiceContract;
use Webkul\User\Models\UserProxy;

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
        'is_show',
    ];

    protected $attributes = [
        'is_show' => false,
    ];

    protected $casts = [
        'is_show' => 'boolean',
    ];

    /**
     * The leads that belong to the service.
     */
    public function leads(): BelongsToMany
    {
        return $this->belongsToMany(LeadProxy::modelClass(), 'lead_service');
    }

    /**
     * Active Admin / Lead Closer users eligible to receive handoffs for this service.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            UserProxy::modelClass(),
            'service_user',
            'service_id',
            'user_id',
        )->withTimestamps();
    }
}
