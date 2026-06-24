<?php

namespace Webkul\MetaLead\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Webkul\Lead\Models\LeadProxy;
use Webkul\MetaLead\Contracts\MetaLead as MetaLeadContract;
use Webkul\User\Models\UserProxy;

class MetaLead extends Model implements MetaLeadContract
{
    public const STATUS_NEW = 'new';

    public const STATUS_CONTACTED = 'contacted';

    public const STATUS_CONVERTED = 'converted';

    public const STATUS_LOST = 'lost';

    public const STATUSES = [
        self::STATUS_NEW,
        self::STATUS_CONTACTED,
        self::STATUS_CONVERTED,
        self::STATUS_LOST,
    ];

    protected $fillable = [
        'leadgen_id',
        'lead_id',
        'full_name',
        'phone',
        'email',
        'campaign_name',
        'form_name',
        'status',
        'is_duplicate',
        'duplicate_of_id',
        'raw_payload',
        'received_at',
    ];

    protected $casts = [
        'is_duplicate' => 'boolean',
        'raw_payload'  => 'array',
        'received_at'  => 'datetime',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(LeadProxy::modelClass());
    }

    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(UserProxy::modelClass(), 'meta_lead_user');
    }
}
