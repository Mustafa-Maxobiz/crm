<?php

namespace Webkul\Lead\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Webkul\User\Models\UserProxy;

class LinkedInProfile extends Model
{
    protected $table = 'linkedin_profiles';

    protected $fillable = [
        'name',
        'profile_url',
        'profile_url_normalized',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            UserProxy::modelClass(),
            'linkedin_profile_user',
            'linkedin_profile_id',
            'user_id',
        )->withTimestamps();
    }
}
