<?php

use Tests\Support\AccessTestHelpers;
use Webkul\Lead\Services\MeetingHandoffService;
use Webkul\Lead\Services\SourceAccessService;

function meetingHandoffServiceWithSourceAccess(SourceAccessService $sourceAccess): MeetingHandoffService
{
    return new MeetingHandoffService(
        $sourceAccess,
        \Mockery::mock(\Webkul\Lead\Repositories\LeadRepository::class),
        \Mockery::mock(\Webkul\Activity\Repositories\ActivityRepository::class),
    );
}

describe('MeetingHandoffService::isHandoffLeadForUser', function () {
    it('returns true when assignee changed away from the working owner', function () {
        $sdr = AccessTestHelpers::user(['id' => 2, 'role_name' => 'sdr']);
        $lead = AccessTestHelpers::lead([
            'user_id'       => 8,
            'lead_owner_id' => 2,
        ]);

        expect(MeetingHandoffService::isHandoffLeadForUser($lead, $sdr))->toBeTrue();
    });

    it('returns false when assignee is still unset on a working-owner lead', function () {
        $sdr = AccessTestHelpers::user(['id' => 2, 'role_name' => 'sdr']);
        $lead = AccessTestHelpers::lead([
            'user_id'       => null,
            'lead_owner_id' => 2,
        ]);

        expect(MeetingHandoffService::isHandoffLeadForUser($lead, $sdr))->toBeFalse();
    });

    it('returns false when the user is still the assignee', function () {
        $sdr = AccessTestHelpers::user(['id' => 2, 'role_name' => 'sdr']);
        $lead = AccessTestHelpers::lead([
            'user_id'       => 2,
            'lead_owner_id' => 2,
        ]);

        expect(MeetingHandoffService::isHandoffLeadForUser($lead, $sdr))->toBeFalse();
    });
});

describe('MeetingHandoffService::canCurrentUserEditStage', function () {
    it('allows an SDR working owner to edit before handoff completes', function () {
        $sourceAccess = \Mockery::mock(SourceAccessService::class);
        $sourceAccess->shouldReceive('isSdrUser')->andReturn(true);
        $sourceAccess->shouldReceive('isLgeUser')->andReturn(false);
        $sourceAccess->shouldReceive('canViewLead')->andReturn(true);

        $service = meetingHandoffServiceWithSourceAccess($sourceAccess);
        $sdr = AccessTestHelpers::user(['id' => 2, 'role_name' => 'sdr']);
        $lead = AccessTestHelpers::lead([
            'user_id'       => null,
            'lead_owner_id' => 2,
        ]);

        expect($service->canCurrentUserEditStage($lead, $sdr))->toBeTrue();
    });

    it('blocks an SDR after handoff to another assignee', function () {
        $service = meetingHandoffServiceWithSourceAccess(\Mockery::mock(SourceAccessService::class));
        $sdr = AccessTestHelpers::user(['id' => 2, 'role_name' => 'sdr']);
        $lead = AccessTestHelpers::lead([
            'user_id'       => 8,
            'lead_owner_id' => 2,
        ]);

        expect($service->canCurrentUserEditStage($lead, $sdr))->toBeFalse();
    });

    it('allows admins regardless of ownership', function () {
        $service = meetingHandoffServiceWithSourceAccess(\Mockery::mock(SourceAccessService::class));
        $admin = AccessTestHelpers::user(['id' => 1, 'admin' => true]);
        $lead = AccessTestHelpers::lead([
            'user_id'       => 8,
            'lead_owner_id' => 2,
        ]);

        expect($service->canCurrentUserEditStage($lead, $admin))->toBeTrue();
    });
});

describe('MeetingHandoffService::canInitiateMeetingHandoff', function () {
    it('allows an SDR to initiate handoff while still the working owner', function () {
        $sourceAccess = \Mockery::mock(SourceAccessService::class);
        $sourceAccess->shouldReceive('isSdrUser')->andReturn(true);
        $sourceAccess->shouldReceive('isLgeUser')->andReturn(false);
        $sourceAccess->shouldReceive('canViewLead')->andReturn(true);

        $service = meetingHandoffServiceWithSourceAccess($sourceAccess);
        $sdr = AccessTestHelpers::user(['id' => 2, 'role_name' => 'sdr']);
        $lead = AccessTestHelpers::lead([
            'user_id'       => null,
            'lead_owner_id' => 2,
        ]);

        expect($service->canInitiateMeetingHandoff($lead, $sdr))->toBeTrue();
    });

    it('allows an SDR to initiate handoff on a shared-queue lead assigned to another worker', function () {
        $sourceAccess = \Mockery::mock(SourceAccessService::class);
        $sourceAccess->shouldReceive('isSdrUser')->andReturn(true);
        $sourceAccess->shouldReceive('isLgeUser')->andReturn(false);
        $sourceAccess->shouldReceive('canViewLead')->andReturn(true);

        $service = meetingHandoffServiceWithSourceAccess($sourceAccess);
        $sdr = AccessTestHelpers::user(['id' => 2, 'role_name' => 'sdr']);
        $lead = AccessTestHelpers::lead([
            'user_id'       => 4,
            'lead_owner_id' => 4,
        ]);

        expect($service->canInitiateMeetingHandoff($lead, $sdr))->toBeTrue();
    });

    it('blocks an SDR after handoff is already complete', function () {
        $sourceAccess = \Mockery::mock(SourceAccessService::class);
        $sourceAccess->shouldReceive('isSdrUser')->andReturn(true);
        $sourceAccess->shouldReceive('isLgeUser')->andReturn(false);

        $service = meetingHandoffServiceWithSourceAccess($sourceAccess);
        $sdr = AccessTestHelpers::user(['id' => 2, 'role_name' => 'sdr']);
        $lead = AccessTestHelpers::lead([
            'user_id'       => 8,
            'lead_owner_id' => 2,
        ]);

        expect($service->canInitiateMeetingHandoff($lead, $sdr))->toBeFalse();
    });
});
