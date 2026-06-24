<?php

namespace Webkul\MetaLead\Providers;

use Webkul\Core\Providers\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected $models = [
        \Webkul\MetaLead\Models\MetaLead::class,
    ];
}
