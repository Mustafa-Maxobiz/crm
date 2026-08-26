<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Webkul\Contact\Repositories\OrganizationRepository;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Lead\Repositories\LeadRepository;

class LeadCreateWithCompanyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (
            ! Schema::hasTable('leads')
            || ! Schema::hasTable('persons')
            || ! Schema::hasTable('organizations')
        ) {
            $this->markTestSkipped('Lead/contact tables are not migrated.');
        }
    }

    public function test_lead_create_with_existing_contact_company_reuses_person(): void
    {
        if (! Schema::hasColumn('leads', 'organization_id')) {
            $this->markTestSkipped('leads.organization_id is not migrated on the test database.');
        }

        $suffix = uniqid('co_', true);
        $email = "john.{$suffix}@example.com";
        $companyName = "ABC Company {$suffix}";

        [$pipelineId, $stageId, $userId, $createdUserId] = $this->ensureLeadCreateFixtures();

        $organization = app(OrganizationRepository::class)->create([
            'entity_type' => 'organizations',
            'name'        => $companyName,
        ]);

        $person = app(PersonRepository::class)->create([
            'entity_type'     => 'persons',
            'name'            => 'John Doe',
            'emails'          => [['value' => $email, 'label' => 'work']],
            'contact_numbers' => [['value' => '', 'label' => 'work']],
            'organization_id' => $organization->id,
        ]);

        $lead = app(LeadRepository::class)->create([
            'entity_type'            => 'leads',
            'title'                  => "Lead {$suffix}",
            'lead_value'             => 100,
            'status'                 => 1,
            'lead_pipeline_id'       => $pipelineId,
            'lead_pipeline_stage_id' => $stageId,
            'user_id'                => $userId,
            'person'                 => [
                'name'              => 'John Doe',
                'emails'            => [['value' => $email, 'label' => 'work']],
                'contact_numbers'   => [['value' => '', 'label' => 'work']],
                'organization_id'   => '',
                'organization_name' => $companyName,
            ],
        ]);

        $this->assertNotNull($lead->id);
        $this->assertSame((int) $person->id, (int) $lead->person_id);
        $this->assertSame((int) $organization->id, (int) $lead->organization_id);

        $personCount = DB::table('persons')
            ->where('emails', 'like', '%'.$email.'%')
            ->count();

        $this->assertSame(1, $personCount);

        DB::table('leads')->where('id', $lead->id)->delete();
        DB::table('persons')->where('id', $person->id)->delete();
        DB::table('organizations')->where('id', $organization->id)->delete();

        if ($createdUserId) {
            DB::table('users')->where('id', $createdUserId)->delete();
        }
    }

    public function test_person_create_with_organization_name_includes_org_in_unique_id(): void
    {
        $suffix = uniqid('orgname_', true);
        $email = "pat.{$suffix}@example.com";
        $companyName = "Org Name Co {$suffix}";

        $person = app(PersonRepository::class)->create([
            'entity_type'       => 'persons',
            'name'              => 'Pat Doe',
            'emails'            => [['value' => $email, 'label' => 'work']],
            'contact_numbers'   => [['value' => '', 'label' => 'work']],
            'organization_name' => $companyName,
        ]);

        $this->assertNotNull($person->organization_id);
        $this->assertSame(
            $person->organization_id.'|'.$email,
            $person->unique_id
        );

        DB::table('persons')->where('id', $person->id)->delete();
        DB::table('organizations')->where('id', $person->organization_id)->delete();
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int|null}
     */
    protected function ensureLeadCreateFixtures(): array
    {
        $pipelineId = (int) (DB::table('lead_pipelines')->orderBy('id')->value('id') ?? 0);

        if ($pipelineId <= 0) {
            $pipelineId = (int) DB::table('lead_pipelines')->insertGetId([
                'name'       => 'Test Pipeline',
                'is_default' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $stageId = (int) (DB::table('lead_pipeline_stages')
            ->where('lead_pipeline_id', $pipelineId)
            ->where('code', 'new')
            ->value('id')
            ?: DB::table('lead_pipeline_stages')
                ->where('lead_pipeline_id', $pipelineId)
                ->orderBy('id')
                ->value('id')
            ?: 0);

        if ($stageId <= 0) {
            $stageId = (int) DB::table('lead_pipeline_stages')->insertGetId([
                'name'             => 'New',
                'code'             => 'new',
                'lead_pipeline_id' => $pipelineId,
                'sort_order'       => 1,
                'probability'      => 100,
            ]);
        }

        $userId = (int) (DB::table('users')->where('status', 1)->orderBy('id')->value('id') ?? 0);
        $createdUserId = null;

        if ($userId <= 0) {
            $roleId = (int) (DB::table('roles')->orderBy('id')->value('id') ?? 0);

            if ($roleId <= 0) {
                $roleId = (int) DB::table('roles')->insertGetId([
                    'name'            => 'Administrator',
                    'description'     => 'Test admin',
                    'permission_type' => 'all',
                    'permissions'     => null,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }

            $createdUserId = (int) DB::table('users')->insertGetId([
                'name'       => 'Test User',
                'email'      => 'lead-company-test-'.uniqid().'@example.com',
                'password'   => bcrypt('password'),
                'status'     => 1,
                'role_id'    => $roleId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $userId = $createdUserId;
        }

        return [$pipelineId, $stageId, $userId, $createdUserId];
    }
}
