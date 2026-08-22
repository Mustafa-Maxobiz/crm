<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\AccessTestHelpers;
use Tests\TestCase;
use Webkul\Lead\Services\SourceAccessService;

class KanbanHandoffVisibilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('leads')) {
            $this->markTestSkipped('Leads table is not migrated.');
        }
    }

    public function test_handed_off_won_stage_is_included_in_visible_kanban_stages(): void
    {
        $pipelineId = (int) (DB::table('lead_pipelines')->value('id') ?? 0);

        if ($pipelineId <= 0) {
            $this->markTestSkipped('Default pipeline is unavailable.');
        }

        $wonStageId = (int) DB::table('lead_pipeline_stages')
            ->where('lead_pipeline_id', $pipelineId)
            ->where('code', 'won')
            ->value('id');

        $meetingStageId = (int) DB::table('lead_pipeline_stages')
            ->where('lead_pipeline_id', $pipelineId)
            ->where('code', 'meeting')
            ->value('id');

        if ($wonStageId <= 0 || $meetingStageId <= 0) {
            $this->markTestSkipped('Pipeline won/meeting stages are unavailable.');
        }

        $lgeUserId = (int) (DB::table('users')->where('status', 1)->value('id') ?? 0);
        $closerUserId = (int) (DB::table('users')->where('status', 1)->where('id', '!=', $lgeUserId)->value('id') ?? 0);

        if ($lgeUserId <= 0 || $closerUserId <= 0) {
            $this->markTestSkipped('Active users are unavailable.');
        }

        $leadId = (int) DB::table('leads')->insertGetId([
            'title'                  => 'Handed Off Won Lead',
            'lead_pipeline_id'       => $pipelineId,
            'lead_pipeline_stage_id' => $wonStageId,
            'user_id'                => $closerUserId,
            'lead_owner_id'          => $lgeUserId,
            'status'                 => 1,
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        $lge = AccessTestHelpers::user([
            'id'                      => $lgeUserId,
            'role_name'               => 'lge',
            'role_pipeline_stage_ids' => DB::table('lead_pipeline_stages')
                ->where('lead_pipeline_id', $pipelineId)
                ->where('sort_order', '<=', DB::table('lead_pipeline_stages')->where('id', $meetingStageId)->value('sort_order'))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all(),
        ]);

        $this->actingAs($lge, 'user');

        $stages = DB::table('lead_pipeline_stages')
            ->where('lead_pipeline_id', $pipelineId)
            ->orderBy('sort_order')
            ->get(['id', 'sort_order', 'code']);

        $service = app(SourceAccessService::class);
        $visibleIds = $service
            ->getVisibleStagesForLeadListing($stages, $pipelineId, $lge)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertContains($wonStageId, $visibleIds);

        DB::table('leads')->where('id', $leadId)->delete();
    }

    public function test_handed_off_lead_is_visible_in_table_scope_for_originating_lge(): void
    {
        $pipelineId = (int) (DB::table('lead_pipelines')->value('id') ?? 0);
        $lostStageId = (int) DB::table('lead_pipeline_stages')->where('code', 'lost')->value('id');

        if ($pipelineId <= 0 || $lostStageId <= 0) {
            $this->markTestSkipped('Pipeline lost stage is unavailable.');
        }

        $lgeUserId = (int) (DB::table('users')->where('status', 1)->value('id') ?? 0);
        $closerUserId = (int) (DB::table('users')->where('status', 1)->where('id', '!=', $lgeUserId)->value('id') ?? 0);

        if ($lgeUserId <= 0 || $closerUserId <= 0) {
            $this->markTestSkipped('Active users are unavailable.');
        }

        $leadId = (int) DB::table('leads')->insertGetId([
            'title'                  => 'Handed Off Lost Lead',
            'lead_pipeline_id'       => $pipelineId,
            'lead_pipeline_stage_id' => $lostStageId,
            'user_id'                => $closerUserId,
            'lead_owner_id'          => $lgeUserId,
            'status'                 => 1,
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        $lge = AccessTestHelpers::user([
            'id'        => $lgeUserId,
            'role_name' => 'lge',
        ]);

        $this->actingAs($lge, 'user');

        $query = DB::table('leads')->where('id', $leadId);
        app(SourceAccessService::class)->applyAccessibleStageTableScope($query);

        $this->assertTrue($query->exists());

        DB::table('leads')->where('id', $leadId)->delete();
    }
}
