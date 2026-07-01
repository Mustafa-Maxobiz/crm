<?php

use Tests\Support\AccessTestHelpers;
use Webkul\Lead\Services\SourceAccessService;

beforeEach(function () {
    $this->service = new SourceAccessService;
});

describe('source access inheritance', function () {
    it('grants all sources to admin users', function () {
        $admin = AccessTestHelpers::user(['admin' => true]);

        expect($this->service->getEffectiveRootSourceIds($admin))->toBeNull()
            ->and($this->service->getExpandedSourceIds($admin))->toBeNull()
            ->and($this->service->canAccessSourceId(999, $admin))->toBeTrue();
    });

    it('inherits role sources when user has none assigned', function () {
        $user = AccessTestHelpers::user(['role_source_ids' => [1, 2]]);

        expect($this->service->getEffectiveRootSourceIds($user))->toBe([1, 2]);
    });

    it('uses user sources when assigned', function () {
        $user = AccessTestHelpers::user([
            'role_source_ids' => [1, 2, 3],
            'user_source_ids' => [2, 3],
        ]);

        expect($this->service->getEffectiveRootSourceIds($user))->toBe([2, 3]);
    });

    it('intersects user sources with role pool when role has sources', function () {
        $user = AccessTestHelpers::user([
            'role_source_ids' => [1, 2],
            'user_source_ids' => [2, 5],
        ]);

        expect($this->service->getEffectiveRootSourceIds($user))->toBe([2]);
    });

    it('returns empty source list when user and role have no sources', function () {
        $user = AccessTestHelpers::user();

        expect($this->service->getEffectiveRootSourceIds($user))->toBe([])
            ->and($this->service->canAccessSourceId(1, $user))->toBeFalse();
    });
});

describe('organization access inheritance', function () {
    it('grants all companies to admin users', function () {
        $admin = AccessTestHelpers::user(['admin' => true]);

        expect($this->service->getEffectiveOrganizationIds($admin))->toBeNull()
            ->and($this->service->canAccessOrganizationId(99, $admin))->toBeTrue();
    });

    it('inherits role companies when user has none assigned', function () {
        $user = AccessTestHelpers::user(['role_organization_ids' => [10, 20]]);

        expect($this->service->getEffectiveOrganizationIds($user))->toBe([10, 20]);
    });

    it('uses user companies when assigned', function () {
        $user = AccessTestHelpers::user([
            'role_organization_ids' => [10, 20, 30],
            'user_organization_ids' => [20],
        ]);

        expect($this->service->getEffectiveOrganizationIds($user))->toBe([20]);
    });
});

describe('combined lead access (source AND company)', function () {
    it('allows lead when both source and company match', function () {
        $user = AccessTestHelpers::user([
            'role_source_ids'        => [1],
            'role_organization_ids'  => [10],
        ]);

        $lead = AccessTestHelpers::lead([
            'lead_source_id'   => 1,
            'organization_id'  => 10,
        ]);

        expect($this->service->canAccessLead($lead, $user))->toBeTrue();
    });

    it('denies lead when source matches but company does not', function () {
        $user = AccessTestHelpers::user([
            'role_source_ids'        => [1],
            'role_organization_ids'  => [10],
        ]);

        $lead = AccessTestHelpers::lead([
            'lead_source_id'   => 1,
            'organization_id'  => 99,
        ]);

        expect($this->service->canAccessLead($lead, $user))->toBeFalse();
    });

    it('denies lead when company matches but source does not', function () {
        $user = AccessTestHelpers::user([
            'role_source_ids'        => [1],
            'role_organization_ids'  => [10],
        ]);

        $lead = AccessTestHelpers::lead([
            'lead_source_id'   => 5,
            'organization_id'  => 10,
        ]);

        expect($this->service->canAccessLead($lead, $user))->toBeFalse();
    });

    it('denies lead without company when user has company restrictions', function () {
        $user = AccessTestHelpers::user([
            'role_source_ids'        => [1],
            'role_organization_ids'  => [10],
        ]);

        $lead = AccessTestHelpers::lead([
            'lead_source_id' => 1,
        ]);

        expect($this->service->canAccessLead($lead, $user))->toBeFalse();
    });

    it('allows admin to access any lead', function () {
        $admin = AccessTestHelpers::user(['admin' => true]);

        $lead = AccessTestHelpers::lead([
            'lead_source_id'  => 999,
            'organization_id' => 999,
        ]);

        expect($this->service->canAccessLead($lead, $admin))->toBeTrue();
    });
});

describe('role pool validation helpers', function () {
    it('accepts empty user source selection', function () {
        expect($this->service->userSourcesValidForRole(1, []))->toBeTrue();
    });

    it('accepts user sources inside role pool', function () {
        \Illuminate\Support\Facades\DB::shouldReceive('table')
            ->with('role_source')
            ->andReturnSelf();

        \Illuminate\Support\Facades\DB::shouldReceive('where')
            ->with('role_id', 1)
            ->andReturnSelf();

        \Illuminate\Support\Facades\DB::shouldReceive('pluck')
            ->with('lead_source_id')
            ->andReturn(collect([1, 2]));

        expect($this->service->userSourcesValidForRole(1, [2]))->toBeTrue();
    });

    it('rejects user sources outside role pool', function () {
        \Illuminate\Support\Facades\DB::shouldReceive('table')
            ->with('role_source')
            ->andReturnSelf();

        \Illuminate\Support\Facades\DB::shouldReceive('where')
            ->with('role_id', 1)
            ->andReturnSelf();

        \Illuminate\Support\Facades\DB::shouldReceive('pluck')
            ->with('lead_source_id')
            ->andReturn(collect([1, 2]));

        expect($this->service->userSourcesValidForRole(1, [3]))->toBeFalse();
    });
});
