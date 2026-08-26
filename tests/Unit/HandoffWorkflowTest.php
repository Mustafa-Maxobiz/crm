<?php

use Tests\Support\AccessTestHelpers;
use Webkul\Lead\Services\SourceAccessService;

describe('SourceAccessService view vs edit separation', function () {
    beforeEach(function () {
        $this->service = new SourceAccessService;
    });

    it('blocks SDR from opening handed-off leads even when they remain the originator', function () {
        $sdr = AccessTestHelpers::user([
            'id'                      => 2,
            'role_name'               => 'sdr',
            'role_pipeline_stage_ids' => [1, 2, 3],
        ]);

        $lead = AccessTestHelpers::lead([
            'user_id'                => 8,
            'lead_owner_id'          => 2,
            'lead_pipeline_stage_id' => 9,
        ]);

        expect($this->service->canViewLead($lead, $sdr))->toBeFalse()
            ->and($this->service->canEditLead($lead, $sdr))->toBeFalse();
    });

    it('blocks SDR from viewing non-owned leads in inaccessible stages', function () {
        $sdr = AccessTestHelpers::user([
            'id'                      => 2,
            'role_name'               => 'sdr',
            'role_pipeline_stage_ids' => [1, 2, 3],
        ]);

        $lead = AccessTestHelpers::lead([
            'user_id'                => 4,
            'lead_owner_id'          => 4,
            'lead_pipeline_stage_id' => 9,
        ]);

        expect($this->service->canViewLead($lead, $sdr))->toBeFalse()
            ->and($this->service->canEditLead($lead, $sdr))->toBeFalse();
    });

    it('allows the current assignee to edit after handoff', function () {
        $closer = AccessTestHelpers::user([
            'id'                      => 8,
            'role_name'               => 'lead',
            'role_pipeline_stage_ids' => [9],
        ]);

        $lead = AccessTestHelpers::lead([
            'user_id'                => 8,
            'lead_owner_id'          => 2,
            'lead_pipeline_stage_id' => 9,
        ]);

        expect($this->service->canViewLead($lead, $closer))->toBeTrue()
            ->and($this->service->canEditLead($lead, $closer))->toBeTrue();
    });

    it('allows SDR to edit their active working lead before handoff', function () {
        $sdr = AccessTestHelpers::user([
            'id'                      => 2,
            'role_name'               => 'sdr',
            'role_pipeline_stage_ids' => [1, 2, 3],
        ]);

        $lead = AccessTestHelpers::lead([
            'user_id'                => 2,
            'lead_owner_id'          => 2,
            'lead_pipeline_stage_id' => 2,
        ]);

        expect($this->service->canViewLead($lead, $sdr))->toBeTrue()
            ->and($this->service->canEditLead($lead, $sdr))->toBeTrue();
    });
});

describe('SourceAccessService kanban visible stages', function () {
    beforeEach(function () {
        $this->service = new SourceAccessService;
    });

    it('includes only editable stages when no handed-off leads exist', function () {
        $lge = AccessTestHelpers::user([
            'id'                      => 5,
            'role_name'               => 'lge',
            'role_pipeline_stage_ids' => [1, 2, 3, 4],
        ]);

        $stages = collect([
            (object) ['id' => 1, 'sort_order' => 1, 'code' => 'new'],
            (object) ['id' => 2, 'sort_order' => 2, 'code' => 'calling'],
            (object) ['id' => 3, 'sort_order' => 3, 'code' => 'follow-up'],
            (object) ['id' => 4, 'sort_order' => 4, 'code' => 'meeting'],
            (object) ['id' => 8, 'sort_order' => 8, 'code' => 'negotiation'],
            (object) ['id' => 9, 'sort_order' => 9, 'code' => 'won'],
            (object) ['id' => 10, 'sort_order' => 10, 'code' => 'lost'],
        ]);

        $service = \Mockery::mock(SourceAccessService::class)->makePartial();
        $service->shouldReceive('getHandedOffLeadStageIds')->andReturn([]);

        $visible = $service->getVisibleStagesForLeadListing($stages, null, $lge);

        expect($visible->pluck('id')->map(fn ($id) => (int) $id)->all())
            ->toEqual([1, 2, 3, 4]);
    });

    it('merges handed-off stage ids from getHandedOffLeadStageIds into visible stages', function () {
        $lge = AccessTestHelpers::user([
            'id'                      => 5,
            'role_name'               => 'lge',
            'role_pipeline_stage_ids' => [1, 2, 3, 4],
        ]);

        $service = \Mockery::mock(SourceAccessService::class)->makePartial();
        $service->shouldReceive('getHandedOffLeadStageIds')->andReturn([9, 10]);

        $stages = collect([
            (object) ['id' => 1, 'sort_order' => 1, 'code' => 'new'],
            (object) ['id' => 4, 'sort_order' => 4, 'code' => 'meeting'],
            (object) ['id' => 9, 'sort_order' => 9, 'code' => 'won'],
            (object) ['id' => 10, 'sort_order' => 10, 'code' => 'lost'],
        ]);

        $visible = $service->getVisibleStagesForLeadListing($stages, 1, $lge);

        expect($visible->pluck('id')->map(fn ($id) => (int) $id)->all())
            ->toEqual([1, 4, 9, 10]);
    });

    it('blocks LGE from opening handed-off leads after sales ownership transfer', function () {
        $lge = AccessTestHelpers::user([
            'id'                      => 5,
            'role_name'               => 'lge',
            'role_pipeline_stage_ids' => [1, 2, 3, 4],
        ]);

        $lead = AccessTestHelpers::lead([
            'user_id'                => 9,
            'lead_owner_id'          => 5,
            'lead_pipeline_stage_id' => 9,
        ]);

        $service = new SourceAccessService;

        expect($service->canViewLead($lead, $lge))->toBeFalse()
            ->and($service->canEditLead($lead, $lge))->toBeFalse();
    });
});
