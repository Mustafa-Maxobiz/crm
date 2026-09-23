<?php

namespace Webkul\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\User\Models\UserProxy;

class WorkingHour extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'working_hours';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'start_time',
        'end_time',
    ];

    /**
     * Get the user that owns these working hours.
     */
    public function user()
    {
        return $this->belongsTo(UserProxy::modelClass());
    }
}
