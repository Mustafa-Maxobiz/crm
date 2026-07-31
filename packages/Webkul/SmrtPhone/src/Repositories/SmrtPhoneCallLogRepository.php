<?php

namespace Webkul\SmrtPhone\Repositories;

use Webkul\Core\Eloquent\Repository;
use Webkul\SmrtPhone\Contracts\SmrtPhoneCallLog;

class SmrtPhoneCallLogRepository extends Repository
{
    public function model(): string
    {
        return SmrtPhoneCallLog::class;
    }

    public function findByExternalCallId(string $externalCallId)
    {
        return $this->findOneByField('external_call_id', $externalCallId);
    }
}
