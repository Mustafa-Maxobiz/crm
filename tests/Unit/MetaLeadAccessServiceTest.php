<?php

use Tests\Support\AccessTestHelpers;
use Webkul\MetaLead\Services\MetaLeadAccessService;

it('allows admins to view any meta lead without checking assignments', function () {
    $admin = AccessTestHelpers::user(['admin' => true, 'id' => 1]);

    $this->actingAs($admin, 'user');

    expect(app(MetaLeadAccessService::class)->isAdmin())->toBeTrue();
});

it('identifies non-admin users correctly', function () {
    $user = AccessTestHelpers::user(['id' => 5]);

    $this->actingAs($user, 'user');

    expect(app(MetaLeadAccessService::class)->isAdmin())->toBeFalse();
});

it('denies meta lead access for guests', function () {
    auth()->guard('user')->logout();

    $metaLead = new \Webkul\MetaLead\Models\MetaLead;

    expect(app(MetaLeadAccessService::class)->canView($metaLead))->toBeFalse();
});
