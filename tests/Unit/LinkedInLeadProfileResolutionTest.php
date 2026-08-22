<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\Support\AccessTestHelpers;
use Tests\TestCase;
use Webkul\Admin\Http\Controllers\Lead\LeadController;
use Webkul\Lead\Services\LinkedInUrlNormalizer;

class LinkedInLeadProfileResolutionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('linkedin_entry') || ! Schema::hasTable('linkedin_profiles')) {
            $this->markTestSkipped('LinkedIn tables are not migrated.');
        }
    }

    protected function createProfile(string $name, bool $active = true): int
    {
        return (int) DB::table('linkedin_profiles')->insertGetId([
            'name'                   => $name,
            'profile_url'            => 'https://linkedin.com/in/'.str_replace(' ', '-', strtolower($name)),
            'profile_url_normalized' => 'linkedin.com/in/'.str_replace(' ', '-', strtolower($name)),
            'is_active'              => $active,
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);
    }

    protected function assignProfile(int $profileId, int $userId): void
    {
        DB::table('linkedin_profile_user')->insert([
            'linkedin_profile_id' => $profileId,
            'user_id'             => $userId,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);
    }

    protected function lgeUser(int $userId = 50): \Webkul\User\Models\User
    {
        $user = AccessTestHelpers::user([
            'id'        => $userId,
            'role_name' => 'LGE',
        ]);

        $this->actingAs($user, 'user');

        return $user;
    }

    protected function invokeApplyLinkedInProfile(array $data, ?int $batchProfileId = null): array
    {
        $controller = app(LeadController::class);
        $method = new \ReflectionMethod($controller, 'applyLinkedInProfileToLeadData');
        $method->setAccessible(true);
        $method->invokeArgs($controller, [&$data, $batchProfileId]);

        return $data;
    }

    protected function invokeBackfill(string $sourceLink, int $profileId): void
    {
        $controller = app(LeadController::class);
        $method = new \ReflectionMethod($controller, 'backfillLinkedInEntryProfile');
        $method->setAccessible(true);
        $method->invoke($controller, $sourceLink, $profileId);
    }

    public function test_url_normalizer_compare_expression_is_stable(): void
    {
        $normalized = LinkedInUrlNormalizer::normalizeForCompare(
            LinkedInUrlNormalizer::normalize('HTTP://WWW.LinkedIn.com/in/Test-User/')
        );

        $this->assertSame('linkedin.com/in/test-user', $normalized);
    }

    public function test_find_linkedin_entry_by_source_link_matches_normalized_url(): void
    {
        $profileId = $this->createProfile('Maxobiz Sales');
        $targetUrl = 'https://www.linkedin.com/in/john-smith/';

        DB::table('linkedin_entry')->insert([
            'user_id'             => 1,
            'linkedin_profile_id' => $profileId,
            'name'                => 'John Smith',
            'url'                 => $targetUrl,
            'status'              => 'pending',
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        $controller = app(LeadController::class);
        $method = new \ReflectionMethod($controller, 'findLinkedInEntryBySourceLink');
        $method->setAccessible(true);

        $entry = $method->invoke($controller, 'linkedin.com/in/john-smith');

        $this->assertNotNull($entry);
        $this->assertSame($profileId, (int) $entry->linkedin_profile_id);
    }

    public function test_entry_inherits_profile_on_lead_create(): void
    {
        $profileId = $this->createProfile('Profile A');
        $this->assignProfile($profileId, 50);
        $this->lgeUser(50);

        $sourceLink = 'https://www.linkedin.com/in/entry-inherit/';

        DB::table('linkedin_entry')->insert([
            'user_id'             => 50,
            'linkedin_profile_id' => $profileId,
            'name'                => 'Entry Inherit',
            'url'                 => $sourceLink,
            'status'              => 'pending',
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        $data = [
            'source_link' => $sourceLink,
        ];

        $data = $this->invokeApplyLinkedInProfile($data);

        $this->assertSame($profileId, (int) $data['linkedin_profile_id']);
    }

    public function test_legacy_entry_backfills_profile_on_lead_create(): void
    {
        $profileId = $this->createProfile('Legacy Profile');
        $this->assignProfile($profileId, 51);
        $this->lgeUser(51);

        $sourceLink = 'https://www.linkedin.com/in/legacy-entry/';

        $entryId = (int) DB::table('linkedin_entry')->insertGetId([
            'user_id'             => 51,
            'linkedin_profile_id' => null,
            'name'                => 'Legacy Entry',
            'url'                 => $sourceLink,
            'status'              => 'pending',
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        $data = [
            'source_link'         => $sourceLink,
            'linkedin_profile_id' => $profileId,
        ];

        $data = $this->invokeApplyLinkedInProfile($data);
        $this->invokeBackfill($sourceLink, (int) $data['linkedin_profile_id']);

        $this->assertSame($profileId, (int) $data['linkedin_profile_id']);
        $this->assertSame(
            $profileId,
            (int) DB::table('linkedin_entry')->where('id', $entryId)->value('linkedin_profile_id')
        );
    }

    public function test_direct_lead_without_entry_uses_selected_profile(): void
    {
        $profileId = $this->createProfile('Direct Profile');
        $this->assignProfile($profileId, 52);
        $this->lgeUser(52);

        $data = [
            'source_link'         => 'https://www.linkedin.com/in/no-entry-lead/',
            'linkedin_profile_id' => $profileId,
        ];

        $data = $this->invokeApplyLinkedInProfile($data);

        $this->assertSame($profileId, (int) $data['linkedin_profile_id']);
    }

    public function test_unassigned_profile_is_rejected(): void
    {
        $assignedId = $this->createProfile('Assigned Profile');
        $otherId = $this->createProfile('Other Profile');
        $this->assignProfile($assignedId, 53);
        $this->lgeUser(53);

        $this->expectException(ValidationException::class);

        $this->invokeApplyLinkedInProfile([
            'source_link'         => 'https://www.linkedin.com/in/unauthorized/',
            'linkedin_profile_id' => $otherId,
        ]);
    }

    public function test_handoff_preserves_linkedin_profile_id(): void
    {
        if (! Schema::hasTable('leads')) {
            $this->markTestSkipped('Leads table is not migrated.');
        }

        $profileId = $this->createProfile('Handoff Profile');

        $leadId = (int) DB::table('leads')->insertGetId([
            'title'               => 'Handoff Lead',
            'linkedin_profile_id' => $profileId,
            'user_id'             => 5,
            'lead_owner_id'       => 5,
            'status'              => 1,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        DB::table('leads')->where('id', $leadId)->update([
            'user_id'    => 9,
            'updated_at' => now(),
        ]);

        $this->assertSame(
            $profileId,
            (int) DB::table('leads')->where('id', $leadId)->value('linkedin_profile_id')
        );

        DB::table('leads')->where('id', $leadId)->delete();
    }

    public function test_won_lost_transition_preserves_linkedin_profile_id(): void
    {
        if (! Schema::hasTable('leads')) {
            $this->markTestSkipped('Leads table is not migrated.');
        }

        $profileId = $this->createProfile('Closed Profile');
        $wonStageId = (int) DB::table('lead_pipeline_stages')->where('code', 'won')->value('id');
        $lostStageId = (int) DB::table('lead_pipeline_stages')->where('code', 'lost')->value('id');

        if ($wonStageId <= 0 || $lostStageId <= 0) {
            $this->markTestSkipped('Won/lost stages are unavailable.');
        }

        $leadId = (int) DB::table('leads')->insertGetId([
            'title'                  => 'Closed Lead',
            'linkedin_profile_id'    => $profileId,
            'user_id'                => 9,
            'lead_owner_id'          => 5,
            'lead_pipeline_stage_id' => $wonStageId,
            'status'                 => 1,
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        DB::table('leads')->where('id', $leadId)->update([
            'lead_pipeline_stage_id' => $lostStageId,
            'updated_at'             => now(),
        ]);

        $this->assertSame(
            $profileId,
            (int) DB::table('leads')->where('id', $leadId)->value('linkedin_profile_id')
        );

        DB::table('leads')->where('id', $leadId)->delete();
    }
}
