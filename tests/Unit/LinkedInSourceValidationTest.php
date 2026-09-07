<?php

namespace Tests\Unit;

use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\Support\AccessTestHelpers;
use Tests\TestCase;
use Webkul\Admin\Http\Controllers\Lead\LeadController;
use Webkul\Lead\Repositories\SourceRepository;
use Webkul\Lead\Services\LinkedInProfileAccessService;

class LinkedInSourceValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $sources = Mockery::mock(SourceRepository::class);
        $sources->shouldReceive('isLinkedInSource')->andReturnUsing(fn (int $id) => $id === 10);
        $this->app->instance(SourceRepository::class, $sources);
        $this->actingAs(AccessTestHelpers::user(['id' => 50, 'role_name' => 'BDE']), 'user');
    }

    private function applyProfile(array $data): array
    {
        $controller = app(LeadController::class);
        $method = new \ReflectionMethod($controller, 'applyLinkedInProfileToLeadData');
        $method->invokeArgs($controller, [&$data]);

        return $data;
    }

    public function test_linkedin_create_requires_profile_even_without_source_link(): void
    {
        $this->expectException(ValidationException::class);

        $this->applyProfile(['lead_source_id' => 10]);
    }

    public function test_other_source_clears_profile_and_skips_linkedin_entry_lookup(): void
    {
        $profiles = Mockery::mock(LinkedInProfileAccessService::class);
        $profiles->shouldNotReceive('assertCanUseProfile');
        $this->app->instance(LinkedInProfileAccessService::class, $profiles);

        $data = $this->applyProfile([
            'lead_source_id' => 20,
            'source_link' => 'https://linkedin.com/in/previous-selection',
            'linkedin_profile_id' => 99,
        ]);

        $this->assertNull($data['linkedin_profile_id']);
    }

    public function test_linkedin_create_validates_selected_profile_access(): void
    {
        $profiles = Mockery::mock(LinkedInProfileAccessService::class);
        $profiles->shouldReceive('assertCanUseProfile')->once()->with(7, Mockery::any(), 50);
        $this->app->instance(LinkedInProfileAccessService::class, $profiles);

        $data = $this->applyProfile(['lead_source_id' => 10, 'linkedin_profile_id' => 7]);

        $this->assertSame(7, $data['linkedin_profile_id']);
    }

    public function test_linkedin_create_rejects_unauthorized_profile(): void
    {
        $profiles = Mockery::mock(LinkedInProfileAccessService::class);
        $profiles->shouldReceive('assertCanUseProfile')->once()->andThrow(
            ValidationException::withMessages(['linkedin_profile_id' => 'Profile is not assigned to this user.'])
        );
        $this->app->instance(LinkedInProfileAccessService::class, $profiles);
        $this->expectException(ValidationException::class);

        $this->applyProfile(['lead_source_id' => 10, 'linkedin_profile_id' => 7]);
    }

    public function test_import_profile_is_required_only_for_linkedin_across_variants(): void
    {
        $controller = app(LeadController::class);
        $method = new \ReflectionMethod($controller, 'validatedLgeImportProfileId');
        request()->headers->set('Accept', 'application/json');

        foreach (['main', 'bde', 'lge', 'sdr'] as $variant) {
            view()->share('leadVariant', $variant);
            request()->replace(['lead_source_id' => 10]);
            $this->assertSame(422, $method->invoke($controller)->getStatusCode());

            request()->replace(['lead_source_id' => 20, 'import_linkedin_profile_id' => 999]);
            $this->assertNull($method->invoke($controller));
        }
    }

    public function test_import_rows_require_profile_without_source_link_only_for_linkedin(): void
    {
        $controller = app(LeadController::class);
        $method = new \ReflectionMethod($controller, 'validateImportRow');
        $linkedInErrors = $method->invoke($controller, [], true, null, 10);
        $otherErrors = $method->invoke($controller, [], true, null, 20);

        $profileError = 'LinkedIn working profile is required for rows without a matching entry profile.';
        $this->assertContains($profileError, $linkedInErrors);
        $this->assertNotContains($profileError, $otherErrors);
    }
}
