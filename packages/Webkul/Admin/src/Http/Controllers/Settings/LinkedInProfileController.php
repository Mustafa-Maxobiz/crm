<?php

namespace Webkul\Admin\Http\Controllers\Settings;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Webkul\Admin\DataGrids\Settings\LinkedInProfileDataGrid;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Lead\Repositories\LinkedInProfileRepository;
use Webkul\Lead\Services\LinkedInProfileAccessService;
use Webkul\Lead\Services\SourceAccessService;

class LinkedInProfileController extends Controller
{
    public function __construct(
        protected LinkedInProfileRepository $linkedInProfileRepository,
        protected LinkedInProfileAccessService $linkedInProfileAccessService,
        protected SourceAccessService $sourceAccessService,
    ) {}

    public function index(): View|JsonResponse
    {
        $this->ensureCanManage();

        if (request()->ajax()) {
            return datagrid(LinkedInProfileDataGrid::class)->process();
        }

        return view('admin::settings.linkedin-profiles.index', [
            'assignableUsers' => $this->assignableUsers(),
        ]);
    }

    public function store(): JsonResponse
    {
        $this->ensureCanManage('settings.other_settings.linkedin_profiles.create');

        $data = $this->validateProfilePayload();

        $profile = DB::transaction(function () use ($data) {
            $profile = $this->linkedInProfileRepository->create([
                'name'                     => $data['name'],
                'profile_url'              => $data['profile_url'],
                'profile_url_normalized'   => $data['profile_url_normalized'],
                'is_active'                => (bool) ($data['is_active'] ?? true),
            ]);

            $this->linkedInProfileAccessService->syncProfileUsers($profile, $data['user_ids'] ?? []);

            return $profile;
        });

        return new JsonResponse([
            'message' => 'LinkedIn profile created successfully.',
        ]);
    }

    public function edit(int $id): JsonResponse
    {
        $this->ensureCanManage('settings.other_settings.linkedin_profiles.edit');

        $profile = $this->linkedInProfileRepository->with('users')->findOrFail($id);

        return new JsonResponse([
            'data' => [
                'id'          => $profile->id,
                'name'        => $profile->name,
                'profile_url' => $profile->profile_url,
                'is_active'   => $profile->is_active,
                'user_ids'    => $profile->users->pluck('id')->map(fn ($uid) => (int) $uid)->values(),
            ],
        ]);
    }

    public function update(int $id): JsonResponse
    {
        $this->ensureCanManage('settings.other_settings.linkedin_profiles.edit');

        $profile = $this->linkedInProfileRepository->findOrFail($id);
        $data = $this->validateProfilePayload($profile->id);

        DB::transaction(function () use ($profile, $data) {
            $this->linkedInProfileRepository->update([
                'name'                   => $data['name'],
                'profile_url'            => $data['profile_url'],
                'profile_url_normalized' => $data['profile_url_normalized'],
                'is_active'              => (bool) ($data['is_active'] ?? true),
            ], $profile->id);

            $this->linkedInProfileAccessService->syncProfileUsers(
                $profile->fresh(),
                $data['user_ids'] ?? [],
            );
        });

        return new JsonResponse([
            'message' => 'LinkedIn profile updated successfully.',
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->ensureCanManage('settings.other_settings.linkedin_profiles.delete');

        $profile = $this->linkedInProfileRepository->findOrFail($id);

        $inUse = DB::table('linkedin_entry')->where('linkedin_profile_id', $profile->id)->exists()
            || DB::table('leads')->where('linkedin_profile_id', $profile->id)->exists();

        if ($inUse) {
            $this->linkedInProfileRepository->update(['is_active' => false], $profile->id);

            return new JsonResponse([
                'message' => 'Profile is in use. It was deactivated instead of deleted.',
            ]);
        }

        $this->linkedInProfileRepository->delete($profile->id);

        return new JsonResponse([
            'message' => 'LinkedIn profile deleted successfully.',
        ]);
    }

    protected function validateProfilePayload(?int $ignoreId = null): array
    {
        $data = request()->validate([
            'name'        => ['required', 'string', 'max:255'],
            'profile_url' => ['required', 'string', 'max:2048'],
            'is_active'   => ['nullable', 'boolean'],
            'user_ids'    => ['nullable', 'array'],
            'user_ids.*'  => ['integer', 'exists:users,id'],
        ]);

        $normalizedUrl = $this->linkedInProfileAccessService->normalizeProfileUrl($data['profile_url']);

        validator(['profile_url' => $normalizedUrl], [
            'profile_url' => ['required', 'url', 'max:2048'],
        ])->validate();

        $compare = $this->linkedInProfileAccessService->normalizeProfileUrlForCompare($normalizedUrl);

        if ($this->linkedInProfileAccessService->profileUrlExists($compare, $ignoreId)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'profile_url' => ['This LinkedIn profile URL is already registered.'],
            ]);
        }

        $data['profile_url'] = $normalizedUrl;
        $data['profile_url_normalized'] = $compare;

        return $data;
    }

    protected function ensureCanManage(?string $permission = null): void
    {
        if (! $this->sourceAccessService->isAdmin()) {
            abort(403);
        }

        if ($permission && ! bouncer()->hasPermission($permission)) {
            abort(403);
        }
    }

    protected function assignableUsers()
    {
        $query = DB::table('users')->where('users.status', 1);

        if (\Illuminate\Support\Facades\Schema::hasTable('user_roles')) {
            return $query
                ->join('user_roles', 'users.id', '=', 'user_roles.user_id')
                ->join('roles', 'user_roles.role_id', '=', 'roles.id')
                ->distinct()
                ->orderBy('roles.name')
                ->orderBy('users.name')
                ->get(['users.id', 'users.name', 'users.email', 'roles.name as role_name']);
        }

        return $query
            ->join('roles', 'users.role_id', '=', 'roles.id')
            ->orderBy('roles.name')
            ->orderBy('users.name')
            ->get(['users.id', 'users.name', 'users.email', 'roles.name as role_name']);
    }
}
