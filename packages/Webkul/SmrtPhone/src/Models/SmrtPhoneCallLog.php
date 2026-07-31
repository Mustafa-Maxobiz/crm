<?php

namespace Webkul\SmrtPhone\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Activity\Models\ActivityProxy;
use Webkul\Contact\Models\PersonProxy;
use Webkul\Lead\Models\LeadProxy;
use Webkul\SmrtPhone\Contracts\SmrtPhoneCallLog as SmrtPhoneCallLogContract;

class SmrtPhoneCallLog extends Model implements SmrtPhoneCallLogContract
{
    public const CALL_EVENTS = [
        'callIncomingAnswered',
        'callInitiated',
        'callCompleted',
        'callStatusUpdated',
        'callOutcome',
        'callNotesUpdated',
        'callInitiatedSmrtDialer',
        'callCompletedSmrtDialer',
        'callStatusUpdatedSmrtDialer',
        'callNotesUpdatedSmrtDialer',
        'aiTools',
    ];

    protected $table = 'smrtphone_call_logs';

    protected $fillable = [
        'external_call_id',
        'event',
        'direction',
        'from_number',
        'to_number',
        'contact_phone',
        'contact_name',
        'caller_id_name',
        'user_name',
        'device',
        'call_status',
        'call_outcome',
        'call_notes',
        'recording_url',
        'ai_summary',
        'ai_transcript',
        'ai_keywords',
        'is_dialer',
        'person_id',
        'lead_id',
        'activity_id',
        'called_at',
        'raw_payload',
    ];

    protected $casts = [
        'is_dialer'     => 'boolean',
        'ai_transcript' => 'array',
        'ai_keywords'   => 'array',
        'raw_payload'   => 'array',
        'called_at'     => 'datetime',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(PersonProxy::modelClass());
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(LeadProxy::modelClass());
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(ActivityProxy::modelClass());
    }
}
