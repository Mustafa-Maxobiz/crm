<?php

namespace Webkul\SmrtPhone\Providers;

use Webkul\Core\Providers\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected $models = [
        \Webkul\SmrtPhone\Models\SmrtPhoneCallLog::class,
    ];
}
