<?php

use Tests\Support\AccessTestHelpers;
use Webkul\Lead\Services\SourceAccessService;

function mockNoChildSources(): void
{
    $parentBuilder = \Mockery::mock();

    $parentBuilder->shouldReceive('whereIn')
        ->with(\Mockery::anyOf('parent_source_id', 'source_id'), \Mockery::type('array'))
        ->andReturnSelf();

    $parentBuilder->shouldReceive('pluck')
        ->with(\Mockery::anyOf('source_id', 'parent_source_id'))
        ->andReturn(collect());

    $legacyBuilder = \Mockery::mock();

    $legacyBuilder->shouldReceive('whereIn')
        ->with(\Mockery::anyOf('parent_id', 'id'), \Mockery::type('array'))
        ->andReturnSelf();

    $legacyBuilder->shouldReceive('whereNotNull')
        ->with('parent_id')
        ->andReturnSelf();

    $legacyBuilder->shouldReceive('pluck')
        ->with(\Mockery::anyOf('id', 'parent_id'))
        ->andReturn(collect());

    \Illuminate\Support\Facades\DB::shouldReceive('table')
        ->with('lead_source_parents')
        ->andReturn($parentBuilder);

    \Illuminate\Support\Facades\DB::shouldReceive('table')
        ->with('lead_sources')
        ->andReturn($legacyBuilder);
}

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

    it('does not restrict sources when user and role have no sources', function () {
        $user = AccessTestHelpers::user();

        expect($this->service->getEffectiveRootSourceIds($user))->toBeNull()
            ->and($this->service->canAccessSourceId(1, $user))->toBeTrue();
    });

    it('returns empty source list when user sources do not match the role pool', function () {
        $user = AccessTestHelpers::user([
            'role_source_ids' => [1, 2],
            'user_source_ids' => [5],
        ]);

        expect($this->service->getEffectiveRootSourceIds($user))->toBe([])
            ->and($this->service->canAccessSourceId(5, $user))->toBeFalse();
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

    it('does not restrict companies when user and role have no companies', function () {
        $user = AccessTestHelpers::user();

        expect($this->service->getEffectiveOrganizationIds($user))->toBeNull()
            ->and($this->service->canAccessOrganizationId(99, $user))->toBeTrue();
    });

    it('returns empty company list when user companies do not match the role pool', function () {
        $user = AccessTestHelpers::user([
            'role_organization_ids' => [10, 20],
            'user_organization_ids' => [99],
        ]);

        expect($this->service->getEffectiveOrganizationIds($user))->toBe([])
            ->and($this->service->canAccessOrganizationId(99, $user))->toBeFalse();
    });
});

describe('combined lead access (source AND company)', function () {
    it('allows lead when both source and company match', function () {
        mockNoChildSources();

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
        mockNoChildSources();

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
        mockNoChildSources();

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
        mockNoChildSources();

        $user = AccessTestHelpers::user([
            'role_source_ids'        => [1],
            'role_organization_ids'  => [10],
        ]);

        $lead = AccessTestHelpers::lead([
            'lead_source_id' => 1,
        ]);

        expect($this->service->canAccessLead($lead, $user))->toBeFalse();
    });

    it('allows lead when source matches and company is unrestricted', function () {
        mockNoChildSources();

        $user = AccessTestHelpers::user([
            'role_source_ids' => [1],
        ]);

        $lead = AccessTestHelpers::lead([
            'lead_source_id' => 1,
        ]);

        expect($this->service->canAccessLead($lead, $user))->toBeTrue();
    });

    it('allows lead without source when source scope exists', function () {
        mockNoChildSources();

        $user = AccessTestHelpers::user([
            'role_source_ids' => [1],
        ]);

        $lead = AccessTestHelpers::lead();

        expect($this->service->canAccessLead($lead, $user))->toBeTrue();
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
