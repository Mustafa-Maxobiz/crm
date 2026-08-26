<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\Support\AccessTestHelpers;
use Tests\TestCase;
use Webkul\Lead\Services\LinkedInProfileAccessService;

class LinkedInProfileAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('linkedin_profiles') || ! Schema::hasTable('linkedin_profile_user')) {
            $this->markTestSkipped('LinkedIn profile tables are not migrated.');
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

    public function test_lge_sees_only_assigned_active_profiles(): void
    {
        $profileA = $this->createProfile('Profile A');
        $profileB = $this->createProfile('Profile B');
        $this->createProfile('Inactive Profile', false);

        $lge = AccessTestHelpers::user([
            'id'        => 10,
            'role_name' => 'LGE',
        ]);

        DB::table('linkedin_profile_user')->insert([
            'linkedin_profile_id' => $profileA,
            'user_id'             => 10,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        $service = app(LinkedInProfileAccessService::class);

        $profiles = $service->getAssignedProfiles($lge, true);

        $this->assertCount(1, $profiles);
        $this->assertSame($profileA, (int) $profiles->first()->id);
        $this->assertFalse($service->canUseProfile($profileB, $lge));
        $this->assertTrue($service->canUseProfile($profileA, $lge));
    }

    public function test_assert_can_use_profile_rejects_unassigned_profile(): void
    {
        $profileId = $this->createProfile('Other Profile');

        $lge = AccessTestHelpers::user([
            'id'        => 11,
            'role_name' => 'LGE',
        ]);

        $this->expectException(ValidationException::class);

        app(LinkedInProfileAccessService::class)->assertCanUseProfile($profileId, $lge);
    }

    public function test_inactive_profile_cannot_be_used_for_new_work(): void
    {
        $profileId = $this->createProfile('Disabled Profile', false);

        $lge = AccessTestHelpers::user([
            'id'        => 12,
            'role_name' => 'LGE',
        ]);

        DB::table('linkedin_profile_user')->insert([
            'linkedin_profile_id' => $profileId,
            'user_id'             => 12,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        $service = app(LinkedInProfileAccessService::class);

        $this->assertFalse($service->canUseProfile($profileId, $lge));
    }

    public function test_inactive_profile_appears_in_historical_lead_filter_options(): void
    {
        $activeId = $this->createProfile('Active Profile');
        $inactiveId = $this->createProfile('Historical Inactive', false);

        $lge = AccessTestHelpers::user([
            'id'        => 20,
            'role_name' => 'LGE',
        ]);

        $this->actingAs($lge, 'user');

        DB::table('linkedin_profile_user')->insert([
            'linkedin_profile_id' => $activeId,
            'user_id'             => 20,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        if (! Schema::hasTable('leads')) {
            $this->markTestSkipped('Leads table is not migrated.');
        }

        DB::table('leads')->insert([
            'title'               => 'Historical Lead',
            'linkedin_profile_id' => $inactiveId,
            'user_id'             => 20,
            'lead_owner_id'       => 20,
            'status'              => 1,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        $service = app(LinkedInProfileAccessService::class);
        $options = $service->getFilterOptionsWithHistoricalLeads($lge);
        $values = collect($options)->pluck('value')->map(fn ($id) => (int) $id)->all();
        $labels = collect($options)->pluck('label', 'value');

        $this->assertContains($activeId, $values);
        $this->assertContains($inactiveId, $values);
        $this->assertStringContainsString('(Inactive)', (string) $labels->get($inactiveId));
        $this->assertFalse($service->canUseProfile($inactiveId, $lge));
    }
}
