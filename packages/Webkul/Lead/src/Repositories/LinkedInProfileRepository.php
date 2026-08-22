<?php

namespace Webkul\Lead\Repositories;

use Webkul\Core\Eloquent\Repository;
use Webkul\Lead\Models\LinkedInProfile;

class LinkedInProfileRepository extends Repository
{
    public function model(): string
    {
        return LinkedInProfile::class;
    }
}
