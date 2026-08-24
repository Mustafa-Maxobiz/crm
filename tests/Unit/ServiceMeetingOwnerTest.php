<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\Support\AccessTestHelpers;
use Tests\TestCase;
use Webkul\Admin\Http\Controllers\Settings\LeadAttributeOptionController;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Service;
use Webkul\Lead\Services\MeetingHandoffService;

class ServiceMeetingOwnerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('service_user') || ! Schema::hasTable('services')) {
            $this->markTestSkipped('Service assignment tables are not migrated.');
        }
    }

    protected function createService(string $name): int
    {
        return (int) DB::table('services')->insertGetId([
            'name'       => $name,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function createActiveMeetingOwner(string $suffix, string $roleName = 'lead closer'): int
    {
        $roleId = (int) DB::table('roles')->insertGetId([
            'name'             => $roleName.' '.$suffix,
            'description'      => 'Test role',
            'permission_type'  => 'custom',
            'permissions'      => '[]',
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        return (int) DB::table('users')->insertGetId([
            'name'              => 'Closer '.$suffix,
            'email'             => 'closer-'.$suffix.'@example.com',
            'password'          => bcrypt('password'),
            'status'            => 1,
            'role_id'           => $roleId,
            'view_permission'   => 'global',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }

    protected function assignServiceUsers(int $serviceId, array $userIds): void
    {
        foreach ($userIds as $userId) {
            DB::table('service_user')->insert([
                'service_id' => $serviceId,
                'user_id'    => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    protected function attachLeadServices(int $leadId, array $serviceIds): void
    {
        foreach ($serviceIds as $serviceId) {
            DB::table('lead_service')->insert([
                'lead_id'    => $leadId,
                'service_id' => $serviceId,
            ]);
        }
    }

    public function test_service_user_pivot_can_be_synced(): void
    {
        $serviceId = $this->createService('Website Development');
        $userA = $this->createActiveMeetingOwner('a');
        $userB = $this->createActiveMeetingOwner('b');

        $service = Service::query()->findOrFail($serviceId);
        $service->users()->sync([$userA, $userB]);

        $this->assertEqualsCanonicalizing(
            [$userA, $userB],
            DB::table('service_user')->where('service_id', $serviceId)->pluck('user_id')->map(fn ($id) => (int) $id)->all()
        );
    }

    public function test_service_user_sync_replaces_previous_assignments(): void
    {
        $serviceId = $this->createService('SEO');
        $userA = $this->createActiveMeetingOwner('sync-a');
        $userB = $this->createActiveMeetingOwner('sync-b');
        $userC = $this->createActiveMeetingOwner('sync-c');

        $service = Service::query()->findOrFail($serviceId);
        $service->users()->sync([$userA, $userB]);
        $service->users()->sync([$userB, $userC]);

        $assigned = DB::table('service_user')
            ->where('service_id', $serviceId)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertEqualsCanonicalizing([$userB, $userC], $assigned);
        $this->assertNotContains($userA, $assigned);
    }

    public function test_lead_without_services_allows_all_active_meeting_owners(): void
    {
        if (! Schema::hasTable('leads')) {
            $this->markTestSkipped('Leads table is not migrated.');
        }

        $service = app(MeetingHandoffService::class);
        $lead = AccessTestHelpers::lead(['id' => 9001]);

        $allOwners = $service->getAllActiveMeetingOwners();
        $eligible = $service->getEligibleMeetingOwnersForLead($lead);

        $this->assertSame(
            collect($allOwners)->pluck('id')->sort()->values()->all(),
            collect($eligible)->pluck('id')->sort()->values()->all()
        );
    }

    public function test_lead_with_one_service_returns_only_assigned_owners(): void
    {
        if (! Schema::hasTable('leads') || ! Schema::hasTable('lead_service')) {
            $this->markTestSkipped('Lead service pivot is not migrated.');
        }

        $serviceId = $this->createService('SEO Filter');
        $allowed = $this->createActiveMeetingOwner('allowed');
        $blocked = $this->createActiveMeetingOwner('blocked');
        $this->assignServiceUsers($serviceId, [$allowed]);

        $leadId = (int) DB::table('leads')->insertGetId([
            'title'      => 'SEO Lead',
            'status'     => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->attachLeadServices($leadId, [$serviceId]);

        $lead = Lead::query()->findOrFail($leadId);
        $eligibleIds = collect(app(MeetingHandoffService::class)->getEligibleMeetingOwnersForLead($lead))
            ->pluck('id')
            ->all();

        $this->assertContains($allowed, $eligibleIds);
        $this->assertNotContains($blocked, $eligibleIds);
    }

    public function test_lead_with_multiple_services_returns_union_of_owners(): void
    {
        if (! Schema::hasTable('leads') || ! Schema::hasTable('lead_service')) {
            $this->markTestSkipped('Lead service pivot is not migrated.');
        }

        $seoId = $this->createService('SEO Union');
        $webId = $this->createService('Website Union');
        $userA = $this->createActiveMeetingOwner('union-a');
        $userB = $this->createActiveMeetingOwner('union-b');
        $userC = $this->createActiveMeetingOwner('union-c');
        $this->assignServiceUsers($seoId, [$userA, $userB]);
        $this->assignServiceUsers($webId, [$userB, $userC]);

        $leadId = (int) DB::table('leads')->insertGetId([
            'title'      => 'Multi Service Lead',
            'status'     => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->attachLeadServices($leadId, [$seoId, $webId]);

        $lead = Lead::query()->findOrFail($leadId);
        $eligibleIds = collect(app(MeetingHandoffService::class)->getEligibleMeetingOwnersForLead($lead))
            ->pluck('id')
            ->all();

        $this->assertEqualsCanonicalizing([$userA, $userB, $userC], $eligibleIds);
    }

    public function test_service_without_assignments_returns_no_eligible_owners(): void
    {
        if (! Schema::hasTable('leads') || ! Schema::hasTable('lead_service')) {
            $this->markTestSkipped('Lead service pivot is not migrated.');
        }

        $serviceId = $this->createService('Unassigned Service');
        $leadId = (int) DB::table('leads')->insertGetId([
            'title'      => 'Assigned Service Lead',
            'status'     => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->attachLeadServices($leadId, [$serviceId]);

        $lead = Lead::query()->findOrFail($leadId);
        $eligible = app(MeetingHandoffService::class)->getEligibleMeetingOwnersForLead($lead);

        $this->assertSame([], $eligible);
    }

    public function test_forged_unassigned_owner_is_rejected_for_lead_services(): void
    {
        if (! Schema::hasTable('leads') || ! Schema::hasTable('lead_service')) {
            $this->markTestSkipped('Lead service pivot is not migrated.');
        }

        $serviceId = $this->createService('Protected Service');
        $allowed = $this->createActiveMeetingOwner('protected-allowed');
        $forged = $this->createActiveMeetingOwner('protected-forged');
        $this->assignServiceUsers($serviceId, [$allowed]);

        $leadId = (int) DB::table('leads')->insertGetId([
            'title'      => 'Protected Lead',
            'status'     => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->attachLeadServices($leadId, [$serviceId]);

        $lead = Lead::query()->findOrFail($leadId);
        $service = app(MeetingHandoffService::class);

        $this->assertTrue($service->isEligibleMeetingOwnerForLead($lead, $allowed));
        $this->assertFalse($service->isEligibleMeetingOwnerForLead($lead, $forged));
    }

    public function test_validated_service_user_ids_reject_inactive_users(): void
    {
        $serviceId = $this->createService('Validation Service');
        $inactiveUserId = (int) DB::table('users')->insertGetId([
            'name'              => 'Inactive User',
            'email'             => 'inactive@example.com',
            'password'          => bcrypt('password'),
            'status'            => 0,
            'role_id'           => DB::table('roles')->insertGetId([
                'name'            => 'Inactive Role',
                'description'     => 'Test',
                'permission_type' => 'custom',
                'permissions'     => '[]',
                'created_at'      => now(),
                'updated_at'      => now(),
            ]),
            'view_permission'   => 'global',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $controller = app(LeadAttributeOptionController::class);
        $method = new \ReflectionMethod($controller, 'validatedServiceUserIds');
        $method->setAccessible(true);

        request()->merge(['user_ids' => [$inactiveUserId]]);

        $this->expectException(ValidationException::class);
        $method->invoke($controller);
    }
}
