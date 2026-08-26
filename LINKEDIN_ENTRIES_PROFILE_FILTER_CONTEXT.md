# Context: LinkedIn Entries — Profile Filter + Add Entry Default Profile

**Date:** 2026-08-26  
**Branch:** `LGE-ROLE`  
**Type:** Additive UI/query feature (no new tables/APIs)  
**Status:** Implemented (uncommitted at doc generation)

Related whole-app context: [`APP_CONTEXT.md`](./APP_CONTEXT.md)

---

## 1. Goal

On the existing **LinkedIn Entries** page:

1. Show an always-visible **LinkedIn Profile** filter:
   - `All`
   - only profiles the current user may access
2. Filter entries **in SQL** (before pagination), on top of existing authorization.
3. When opening **Add LinkedIn Entry**, preselect the page’s filtered profile (if not `All`).
4. Keep the modal profile dropdown **editable**.
5. Page filter and modal profile stay **independent** on save.

---

## 2. Audit findings (what already existed)

Before this change, much of the filter was already present:

| Piece | Already existed? |
|-------|------------------|
| Query param `linkedin_profile_id` | Yes |
| SQL `WHERE linkedin_entry.linkedin_profile_id = ?` | Yes |
| Authorized profile options via `LinkedInProfileAccessService` | Yes |
| `assertCanUseProfile` on create/import | Yes |
| Join `linkedin_profiles` for display name | Yes |
| Pagination `paginate(10)->withQueryString()` | Yes |
| Filter panel dropdown (collapsed under Filter) | Yes |
| Add Entry modal default from **page filter** | **No** |
| Preserve page filters after create | **No** |
| Clear unauthorized filter IDs | **No** (partial) |
| Always-visible “LinkedIn Profile: [ All ▼ ]” | **No** |

This change **reuses** the existing stack and completes the missing UX/security hardening.

---

## 3. Architecture (reuse only)

```text
Browser LinkedIn Entries page
  GET ?linkedin_profile_id=&search=&status=...
        ↓
LinkedInEntryController@index
        ↓
LinkedInProfileAccessService
  - getFilterOptionsWithHistoricalEntries()  → dropdown options
  - getAssignedProfileIds()                  → auth scope for non-admins
        ↓
SQL:
  linkedin_entry
  JOIN users
  LEFT JOIN linkedin_profiles
  + existing auth scopes
  + optional profile filter
  + paginate

Add Entry modal:
  initial linkedin_profile_id = page filter (if specific & authorized)
  user may change selection
  POST store → assertCanUseProfile(selected)
  redirect back to index WITH original page filters preserved
```

### Tables reused

- `linkedin_entry` (`linkedin_profile_id`, `user_id`, …)
- `linkedin_profiles` (`id`, `name`, `is_active`, …)
- `linkedin_profile_user` (assignment)
- `users`

### No new

- tables
- models
- services
- routes/APIs
- authorization system

---

## 4. Files changed

| File | Why |
|------|-----|
| `packages/Webkul/Admin/src/Http/Controllers/LinkedInEntryController.php` | Validate filter against authorized options; auth scope then profile filter; preserve filters on create redirect |
| `packages/Webkul/Admin/src/Resources/views/linkedin-entries/index.blade.php` | Always-visible profile filter; Add Entry prefill; return hidden fields; reset-on-open helper |

Unchanged (reused):

- `packages/Webkul/Lead/src/Services/LinkedInProfileAccessService.php`
- `packages/Webkul/Lead/src/Models/LinkedInProfile.php`
- LinkedIn entry/profile migrations
- Import/create validation path (`assertCanUseProfile`)

---

## 5. Behavior contract

### Page filter

| Selection | Result |
|-----------|--------|
| `All` (empty) | All entries user is already authorized to see |
| Specific profile ID | Same auth query **AND** `linkedin_entry.linkedin_profile_id = ID` |

### Add Entry modal

| Page filter | Modal initial profile | Changeable? |
|-------------|----------------------|-------------|
| All | “Select LinkedIn Profile” | Yes |
| Profile A | Profile A | Yes (can switch to B) |

### After save

- New entry uses **modal** profile (not page filter).
- Redirect restores **page** filters (so filter can stay Profile A even if entry saved as B).

### Security

- Dropdown options only from `getFilterOptionsWithHistoricalEntries`.
- Unauthorized filter query values cleared server-side.
- Create still calls `assertCanUseProfile`.

### N+1

- Single list query with `leftJoin linkedin_profiles` for `working_profile_name`.
- One options query for the dropdown.
- No per-row profile loads.

---

## 6. Query shape (conceptual)

```sql
SELECT linkedin_entry.*, users.name AS owner_name, linkedin_profiles.name AS working_profile_name
FROM linkedin_entry
JOIN users ON linkedin_entry.user_id = users.id
LEFT JOIN linkedin_profiles ON linkedin_entry.linkedin_profile_id = linkedin_profiles.id
WHERE
  -- non-admin: linkedin_entry.user_id = auth_id
  -- non-admin: linkedin_profile_id IN (assigned) OR NULL
  -- optional: linkedin_entry.linkedin_profile_id = :filter
  -- optional: search / status / dates / admin user_id
ORDER BY linkedin_entry.id DESC
LIMIT 10 OFFSET ...
```

---

## 7. Manual test checklist

1. Filter All → all authorized entries  
2. Filter Profile A → only A  
3. Pagination stays on A  
4. Profile A + search “John”  
5. Add Entry opens with A selected  
6. Change A→B, save → entry is B  
7. Page filter still A after save  
8. Unauthorized profile ID rejected on create  
9. User with A,B only sees A,B in filter  
10. Edit/status flows unchanged (no overwrite from page filter)  

---

## 8. Validation run

```text
php -l LinkedInEntryController.php     → OK
php artisan view:cache                 → OK
npm run build                          → OK
php artisan test --filter=LinkedInProfileAccessTest
  → 4 skipped (krayin_crm_test missing linkedin_profiles tables)
```

---

## 9. Reused service code (reference)

### `getAssignedProfiles`

```php
    /**
     * @return Collection<int, object{id: int, name: string, profile_url: string, is_active: bool}>
     */
    public function getAssignedProfiles(?UserContract $user = null, bool $activeOnly = true): Collection
    {
        $user = $this->resolveUser($user);

        if (! $user) {
            return collect();
        }

        if ($this->isAdmin($user)) {
            $query = DB::table('linkedin_profiles')->orderBy('name');

            if ($activeOnly) {
                $query->where('is_active', true);
            }

            return $query->get(['id', 'name', 'profile_url', 'is_active']);
        }

        $query = DB::table('linkedin_profiles')
            ->join('linkedin_profile_user', 'linkedin_profiles.id', '=', 'linkedin_profile_user.linkedin_profile_id')
            ->where('linkedin_profile_user.user_id', $user->id)
            ->orderBy('linkedin_profiles.name');

        if ($activeOnly) {
            $query->where('linkedin_profiles.is_active', true);
        }

        return $query->get([
            'linkedin_profiles.id',
            'linkedin_profiles.name',
            'linkedin_profiles.profile_url',
            'linkedin_profiles.is_active',
        ]);
    }
```

### `getFilterOptions + historical entries`

```php
    public function getFilterOptions(?UserContract $user = null, array $includeInactiveUsedIds = []): array
    {
        $profiles = $this->getAssignedProfiles($user, true);

        if ($this->isAdmin($user)) {
            $profiles = DB::table('linkedin_profiles')
                ->orderBy('name')
                ->get(['id', 'name', 'profile_url', 'is_active']);
        }

        $includeInactiveUsedIds = collect($includeInactiveUsedIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->all();

        if (! empty($includeInactiveUsedIds)) {
            $missing = array_diff(
                $includeInactiveUsedIds,
                $profiles->pluck('id')->map(fn ($id) => (int) $id)->all()
            );

            if (! empty($missing)) {
                $extra = DB::table('linkedin_profiles')
                    ->whereIn('id', $missing)
                    ->orderBy('name')
                    ->get(['id', 'name', 'profile_url', 'is_active']);

                $profiles = $profiles->concat($extra);
            }
        }

        return $profiles
            ->unique('id')
            ->map(fn ($profile) => [
                'label' => $profile->name.($profile->is_active ? '' : ' (Inactive)'),
                'value' => (int) $profile->id,
            ])
            ->values()
            ->all();
    }

    /**
     * Filter dropdown options including inactive profiles referenced by the user's leads.
     *
     * @return array<int, array{label: string, value: int}>
     */
    public function getFilterOptionsWithHistoricalLeads(?UserContract $user = null): array
    {
        return $this->getFilterOptions($user, $this->getHistoricalProfileIdsForLeads($user));
    }

    /**
     * Filter dropdown options including inactive profiles referenced by the user's entries.
     *
     * @return array<int, array{label: string, value: int}>
     */
    public function getFilterOptionsWithHistoricalEntries(?UserContract $user = null): array
    {
        return $this->getFilterOptions($user, $this->getHistoricalProfileIdsForEntries($user));
    }
```

### `canUseProfile / assertCanUseProfile`

```php
    public function canUseProfile(int $profileId, ?UserContract $user = null, ?int $ownerUserId = null): bool
    {
        $profile = DB::table('linkedin_profiles')->where('id', $profileId)->first();

        if (! $profile || ! $profile->is_active) {
            return false;
        }

        $user = $this->resolveUser($user);

        if (! $user) {
            return false;
        }

        if ($this->isAdmin($user)) {
            if ($ownerUserId) {
                return $this->isProfileAssignedToUser($profileId, $ownerUserId);
            }

            return true;
        }

        return $this->isProfileAssignedToUser($profileId, (int) $user->id);
    }

    public function isProfileAssignedToUser(int $profileId, int $userId): bool
    {
        return DB::table('linkedin_profile_user')
            ->where('linkedin_profile_id', $profileId)
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * @throws ValidationException
     */
    public function assertCanUseProfile(int $profileId, ?UserContract $user = null, ?int $ownerUserId = null): void
    {
        if ($profileId <= 0) {
            throw ValidationException::withMessages([
                'linkedin_profile_id' => ['Please select a LinkedIn working profile.'],
            ]);
        }

        $profile = DB::table('linkedin_profiles')->where('id', $profileId)->first();

        if (! $profile) {
            throw ValidationException::withMessages([
                'linkedin_profile_id' => ['The selected LinkedIn working profile does not exist.'],
            ]);
        }

        if (! $profile->is_active) {
            throw ValidationException::withMessages([
                'linkedin_profile_id' => ['The selected LinkedIn working profile is inactive.'],
            ]);
        }

        $targetUserId = $ownerUserId ?: (int) ($this->resolveUser($user)?->id ?? 0);

        if ($targetUserId <= 0) {
            throw ValidationException::withMessages([
                'linkedin_profile_id' => ['Unable to validate LinkedIn working profile assignment.'],
            ]);
        }

        if ($this->isAdmin($user) && ! $ownerUserId) {
            return;
        }

        if (! $this->isProfileAssignedToUser($profileId, $targetUserId)) {
            throw ValidationException::withMessages([
                'linkedin_profile_id' => ['The selected LinkedIn working profile is not assigned to this user.'],
            ]);
        }
    }
```

---

## 10. Current controller snippets (post-change)

### `index()` filter + auth + pagination core

```php
    public function index(Request $request): View
    {
        $this->authorizeAccess();

        $user = auth()->guard('user')->user();
        $isAdmin = app(SourceAccessService::class)->isAdmin($user);
        $filters = [
            'search'    => trim((string) $request->query('search', '')),
            'status'    => (string) $request->query('status', ''),
            'date_from' => (string) $request->query('date_from', ''),
            'date_to'   => (string) $request->query('date_to', ''),
            'user_id'             => $isAdmin ? (string) $request->query('user_id', '') : '',
            'linkedin_profile_id' => (string) $request->query('linkedin_profile_id', ''),
        ];

        if (! array_key_exists($filters['status'], self::STATUSES)) {
            $filters['status'] = '';
        }

        foreach (['date_from', 'date_to'] as $dateFilter) {
            if ($filters[$dateFilter] && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters[$dateFilter])) {
                $filters[$dateFilter] = '';
            }
        }

        if ($filters['user_id'] !== '' && (! ctype_digit($filters['user_id']) || (int) $filters['user_id'] <= 0)) {
            $filters['user_id'] = '';
        }

        if ($filters['linkedin_profile_id'] !== '' && (! ctype_digit($filters['linkedin_profile_id']) || (int) $filters['linkedin_profile_id'] <= 0)) {
            $filters['linkedin_profile_id'] = '';
        }

        if ($isAdmin && $filters['user_id'] !== '' && ! $this->userCanAccessLinkedInEntries((int) $filters['user_id'])) {
            $filters['user_id'] = '';
        }

        $profileAccess = app(LinkedInProfileAccessService::class);
        $availableProfiles = $profileAccess->getFilterOptionsWithHistoricalEntries($user);
        $availableProfileIds = collect($availableProfiles)
            ->pluck('value')
            ->map(fn ($id) => (int) $id)
            ->all();

        // Only allow filtering by profiles the user is authorized to see/use.
        if (
            $filters['linkedin_profile_id'] !== ''
            && ! in_array((int) $filters['linkedin_profile_id'], $availableProfileIds, true)
        ) {
            $filters['linkedin_profile_id'] = '';
        }

        $query = DB::table('linkedin_entry')
            ->join('users', 'linkedin_entry.user_id', '=', 'users.id')
            ->leftJoin('linkedin_profiles', 'linkedin_entry.linkedin_profile_id', '=', 'linkedin_profiles.id')
            ->select(
                'linkedin_entry.id',
                'linkedin_entry.user_id',
                'linkedin_entry.linkedin_profile_id',
                'linkedin_entry.name',
                'linkedin_entry.url',
                'linkedin_entry.status',
                'linkedin_entry.created_at',
                'users.name as owner_name',
                'linkedin_profiles.name as working_profile_name',
            )
            ->latest('linkedin_entry.id');

        if (! $isAdmin) {
            $query->where('linkedin_entry.user_id', $user->id);
        }

        if ($filters['search'] !== '') {
            $query->where(function ($query) use ($filters) {
                $query
                    ->where('linkedin_entry.name', 'like', "%{$filters['search']}%")
                    ->orWhere('linkedin_entry.url', 'like', "%{$filters['search']}%")
                    ->orWhere('linkedin_entry.status', 'like', "%{$filters['search']}%")
                    ->orWhere('users.name', 'like', "%{$filters['search']}%");
            });
        }

        if ($filters['status'] !== '') {
            $query->where('linkedin_entry.status', $filters['status']);
        }

        if ($filters['date_from'] !== '') {
            $query->whereDate('linkedin_entry.created_at', '>=', $filters['date_from']);
        }

        if ($filters['date_to'] !== '') {
            $query->whereDate('linkedin_entry.created_at', '<=', $filters['date_to']);
        }

        if ($isAdmin && $filters['user_id'] !== '') {
            $query->where('linkedin_entry.user_id', (int) $filters['user_id']);
        }

        // Existing authorization scope for non-admins (assigned profiles), then optional profile filter.
        if (! $isAdmin) {
            $assignedIds = $profileAccess->getAssignedProfileIds($user, false);

            if (empty($assignedIds)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function ($profileScope) use ($assignedIds) {
                    $profileScope
                        ->whereIn('linkedin_entry.linkedin_profile_id', $assignedIds)
                        ->orWhereNull('linkedin_entry.linkedin_profile_id');
                });
            }
        }

        if ($filters['linkedin_profile_id'] !== '') {
            $query->where('linkedin_entry.linkedin_profile_id', (int) $filters['linkedin_profile_id']);
        }

        $availableUsers = $isAdmin
            ? $this->linkedinEntryUsers()
            : collect([$user]);

        return view('admin::linkedin-entries.index', [
            'entries'  => $query->paginate(10)->withQueryString(),
            'statuses' => self::STATUSES,
            'availableUsers' => $availableUsers,
            'availableProfiles' => $availableProfiles,
            'isAdmin'  => $isAdmin,
            'filters'  => $filters,
            'search'   => $filters['search'],
            'hasFilters' => $filters['status'] !== ''
                || $filters['date_from'] !== ''
                || $filters['date_to'] !== ''
                || $filters['user_id'] !== '',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess('linkedin_entries.create');

        $data = $request->validate([
            'user_id'             => ['nullable', 'integer', 'exists:users,id'],
            'linkedin_profile_id' => ['required', 'integer', 'exists:linkedin_profiles,id'],
            'name'                => ['required', 'string', 'max:255'],

```

### Create success redirect (preserve page filters)

```php
session()->flash('success', 'LinkedIn entry created successfully.');

        return redirect()->route('admin.linkedin_entries.index', array_filter([
            'search'              => $request->input('_return_search'),
            'status'              => $request->input('_return_status'),
            'date_from'           => $request->input('_return_date_from'),
            'date_to'             => $request->input('_return_date_to'),
            'user_id'             => $request->input('_return_user_id'),
            'linkedin_profile_id' => $request->input('_return_linkedin_profile_id'),
        ], fn ($value) => filled($value)));
```

---

## 11. Full git diffs

### `LinkedInEntryController.php`

```diff
diff --git a/packages/Webkul/Admin/src/Http/Controllers/LinkedInEntryController.php b/packages/Webkul/Admin/src/Http/Controllers/LinkedInEntryController.php
index 85dbe409..095bd57e 100644
--- a/packages/Webkul/Admin/src/Http/Controllers/LinkedInEntryController.php
+++ b/packages/Webkul/Admin/src/Http/Controllers/LinkedInEntryController.php
@@ -61,6 +61,21 @@ public function index(Request $request): View
             $filters['user_id'] = '';
         }
 
+        $profileAccess = app(LinkedInProfileAccessService::class);
+        $availableProfiles = $profileAccess->getFilterOptionsWithHistoricalEntries($user);
+        $availableProfileIds = collect($availableProfiles)
+            ->pluck('value')
+            ->map(fn ($id) => (int) $id)
+            ->all();
+
+        // Only allow filtering by profiles the user is authorized to see/use.
+        if (
+            $filters['linkedin_profile_id'] !== ''
+            && ! in_array((int) $filters['linkedin_profile_id'], $availableProfileIds, true)
+        ) {
+            $filters['linkedin_profile_id'] = '';
+        }
+
         $query = DB::table('linkedin_entry')
             ->join('users', 'linkedin_entry.user_id', '=', 'users.id')
             ->leftJoin('linkedin_profiles', 'linkedin_entry.linkedin_profile_id', '=', 'linkedin_profiles.id')
@@ -107,13 +122,7 @@ public function index(Request $request): View
             $query->where('linkedin_entry.user_id', (int) $filters['user_id']);
         }
 
-        if ($filters['linkedin_profile_id'] !== '') {
-            $query->where('linkedin_entry.linkedin_profile_id', (int) $filters['linkedin_profile_id']);
-        }
-
-        $profileAccess = app(LinkedInProfileAccessService::class);
-        $availableProfiles = $profileAccess->getFilterOptionsWithHistoricalEntries($user);
-
+        // Existing authorization scope for non-admins (assigned profiles), then optional profile filter.
         if (! $isAdmin) {
             $assignedIds = $profileAccess->getAssignedProfileIds($user, false);
 
@@ -128,6 +137,10 @@ public function index(Request $request): View
             }
         }
 
+        if ($filters['linkedin_profile_id'] !== '') {
+            $query->where('linkedin_entry.linkedin_profile_id', (int) $filters['linkedin_profile_id']);
+        }
+
         $availableUsers = $isAdmin
             ? $this->linkedinEntryUsers()
             : collect([$user]);
@@ -143,8 +156,7 @@ public function index(Request $request): View
             'hasFilters' => $filters['status'] !== ''
                 || $filters['date_from'] !== ''
                 || $filters['date_to'] !== ''
-                || $filters['user_id'] !== ''
-                || $filters['linkedin_profile_id'] !== '',
+                || $filters['user_id'] !== '',
         ]);
     }
 
@@ -202,7 +214,14 @@ public function store(Request $request): RedirectResponse
 
         session()->flash('success', 'LinkedIn entry created successfully.');
 
-        return redirect()->route('admin.linkedin_entries.index');
+        return redirect()->route('admin.linkedin_entries.index', array_filter([
+            'search'              => $request->input('_return_search'),
+            'status'              => $request->input('_return_status'),
+            'date_from'           => $request->input('_return_date_from'),
+            'date_to'             => $request->input('_return_date_to'),
+            'user_id'             => $request->input('_return_user_id'),
+            'linkedin_profile_id' => $request->input('_return_linkedin_profile_id'),
+        ], fn ($value) => filled($value)));
     }
 
     public function importTemplate(): StreamedResponse
@@ -986,23 +1005,13 @@ protected function authorizeAccess(?string $permission = 'linkedin_entries'): vo
 
     protected function linkedinEntryUsers()
     {
-        return DB::table('users')
-            ->leftJoin('roles', 'users.role_id', '=', 'roles.id')
+        $users = DB::table('users')
             ->where('users.status', 1)
             ->orderBy('users.name')
-            ->get([
-                'users.id',
-                'users.name',
-                'users.email',
-                'roles.permission_type',
-                'roles.permissions',
-            ])
-            ->filter(fn ($user) => $this->roleCanAccessLinkedInEntries($user->permission_type, $user->permissions))
-            ->map(fn ($user) => (object) [
-                'id'    => $user->id,
-                'name'  => $user->name,
-                'email' => $user->email,
-            ])
+            ->get(['users.id', 'users.name', 'users.email']);
+
+        return $users
+            ->filter(fn ($user) => $this->userCanAccessLinkedInEntries((int) $user->id))
             ->values();
     }
 
@@ -1012,6 +1021,19 @@ protected function userCanAccessLinkedInEntries(int $userId): bool
             return false;
         }
 
+        if (\Illuminate\Support\Facades\Schema::hasTable('user_roles')) {
+            $roles = DB::table('user_roles')
+                ->join('roles', 'user_roles.role_id', '=', 'roles.id')
+                ->where('user_roles.user_id', $userId)
+                ->get(['roles.permission_type', 'roles.permissions']);
+
+            foreach ($roles as $role) {
+                if ($this->roleCanAccessLinkedInEntries($role->permission_type, $role->permissions)) {
+                    return true;
+                }
+            }
+        }
+
         $user = DB::table('users')
             ->leftJoin('roles', 'users.role_id', '=', 'roles.id')
             ->where('users.id', $userId)
```

### `linkedin-entries/index.blade.php`

```diff
diff --git a/packages/Webkul/Admin/src/Resources/views/linkedin-entries/index.blade.php b/packages/Webkul/Admin/src/Resources/views/linkedin-entries/index.blade.php
index 76874ba1..19b42e38 100644
--- a/packages/Webkul/Admin/src/Resources/views/linkedin-entries/index.blade.php
+++ b/packages/Webkul/Admin/src/Resources/views/linkedin-entries/index.blade.php
@@ -386,12 +386,13 @@ class="primary-button hidden"
                     @if (bouncer()->hasPermission('linkedin_entries.create'))
                     <x-admin::modal
                         ref="linkedinEntryCreateModal"
-                        :is-active="$errors->has('user_id') || $errors->has('name') || $errors->has('url')"
+                        :is-active="$errors->has('user_id') || $errors->has('name') || $errors->has('url') || $errors->has('linkedin_profile_id')"
                     >
                     <x-slot:toggle>
                         <button
                             type="button"
                             class="primary-button"
+                            onclick="window.resetLinkedinCreateProfileDefault?.()"
                         >
                             Add Entry
                         </button>
@@ -412,6 +413,14 @@ class="grid gap-4"
                         >
                             @csrf
 
+                            {{-- Preserve page filters after create; page filter stays independent of modal profile. --}}
+                            <input type="hidden" name="_return_search" value="{{ $filters['search'] }}" />
+                            <input type="hidden" name="_return_status" value="{{ $filters['status'] }}" />
+                            <input type="hidden" name="_return_date_from" value="{{ $filters['date_from'] }}" />
+                            <input type="hidden" name="_return_date_to" value="{{ $filters['date_to'] }}" />
+                            <input type="hidden" name="_return_user_id" value="{{ $filters['user_id'] }}" />
+                            <input type="hidden" name="_return_linkedin_profile_id" value="{{ $filters['linkedin_profile_id'] }}" />
+
                             @if ($isAdmin)
                                 <div class="grid gap-1">
                                     <label class="text-sm font-medium text-gray-800 dark:text-white">
@@ -446,7 +455,24 @@ class="custom-select min-h-[39px] w-full rounded-md border border-gray-300 px-3
                                     LinkedIn Working Profile *
                                 </label>
 
+                                @php
+                                    $createProfileDefault = (string) old(
+                                        'linkedin_profile_id',
+                                        $filters['linkedin_profile_id'] ?? ''
+                                    );
+
+                                    $createProfileValues = collect($availableProfiles)
+                                        ->pluck('value')
+                                        ->map(fn ($id) => (string) $id)
+                                        ->all();
+
+                                    if ($createProfileDefault !== '' && ! in_array($createProfileDefault, $createProfileValues, true)) {
+                                        $createProfileDefault = '';
+                                    }
+                                @endphp
+
                                 <select
+                                    id="linkedin-entry-create-profile"
                                     name="linkedin_profile_id"
                                     class="custom-select min-h-[39px] w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                                     required
@@ -456,7 +482,7 @@ class="custom-select min-h-[39px] w-full rounded-md border border-gray-300 px-3
                                     @foreach ($availableProfiles as $profile)
                                         <option
                                             value="{{ $profile['value'] }}"
-                                            @selected((string) old('linkedin_profile_id') === (string) $profile['value'])
+                                            @selected($createProfileDefault === (string) $profile['value'])
                                         >
                                             {{ $profile['label'] }}
                                         </option>
@@ -543,7 +569,7 @@ class="primary-button"
                     class="grid gap-3"
                 >
                     <div class="flex items-center justify-between gap-3 max-lg:flex-wrap">
-                        <div class="relative w-full">
+                        <div class="relative w-full max-w-xl">
                             <input
                                 id="linkedin-entry-search"
                                 type="text"
@@ -565,6 +591,33 @@ class="hidden absolute top-1/2 -translate-y-1/2 ltr:right-3 rtl:left-3"
                             </div>
                         </div>
 
+                        <div class="flex shrink-0 items-center gap-2">
+                            <label
+                                for="linkedin-entry-profile-filter"
+                                class="whitespace-nowrap text-sm font-medium text-gray-700 dark:text-gray-200"
+                            >
+                                LinkedIn Profile:
+                            </label>
+
+                            <select
+                                id="linkedin-entry-profile-filter"
+                                name="linkedin_profile_id"
+                                class="custom-select min-h-[39px] min-w-[180px] rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-white"
+                                onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()"
+                            >
+                                <option value="">All</option>
+
+                                @foreach ($availableProfiles as $profile)
+                                    <option
+                                        value="{{ $profile['value'] }}"
+                                        @selected((string) $filters['linkedin_profile_id'] === (string) $profile['value'])
+                                    >
+                                        {{ $profile['label'] }}
+                                    </option>
+                                @endforeach
+                            </select>
+                        </div>
+
                         <button
                             id="linkedin-entry-filter-toggle"
                             type="button"
@@ -672,28 +725,6 @@ class="custom-select min-h-[39px] w-full rounded-md border border-gray-300 px-3
                                 </div>
                             @endif
 
-                            <div class="grid gap-1">
-                                <label class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
-                                    LinkedIn Profile
-                                </label>
-
-                                <select
-                                    name="linkedin_profile_id"
-                                    class="custom-select min-h-[39px] w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-white"
-                                >
-                                    <option value="">All Profiles</option>
-
-                                    @foreach ($availableProfiles as $profile)
-                                        <option
-                                            value="{{ $profile['value'] }}"
-                                            @selected((string) $filters['linkedin_profile_id'] === (string) $profile['value'])
-                                        >
-                                            {{ $profile['label'] }}
-                                        </option>
-                                    @endforeach
-                                </select>
-                            </div>
-
                             <div class="grid gap-1">
                                 <label class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                                     Created From
@@ -896,6 +927,26 @@ class="inline-flex cursor-not-allowed appearance-none items-center justify-cente
 
     @pushOnce('scripts')
         <script>
+            window.resetLinkedinCreateProfileDefault = function () {
+                const select = document.getElementById('linkedin-entry-create-profile');
+                const pageFilter = document.getElementById('linkedin-entry-profile-filter');
+
+                if (! select) {
+                    return;
+                }
+
+                // Prefer validation old value when present; otherwise use page filter (All => blank).
+                const oldSelected = select.querySelector('option[selected]');
+                const preferred = oldSelected?.value
+                    || pageFilter?.value
+                    || '';
+
+                const hasOption = Array.from(select.options).some((option) => option.value === preferred);
+
+                select.value = hasOption ? preferred : '';
+                select.disabled = false;
+            };
+
             window.addEventListener('load', function () {
                 let activeStatusForm = null;
 
```

---

## 12. Key Blade behaviors (summary)

1. **Always-visible filter**  
   `select#linkedin-entry-profile-filter` named `linkedin_profile_id`, options `All` + `$availableProfiles`, `onchange` submits GET form.

2. **Add Entry prefill**  
   `old('linkedin_profile_id', $filters['linkedin_profile_id'])` if that value exists in `$availableProfiles`.

3. **Reset on open**  
   `onclick="window.resetLinkedinCreateProfileDefault?.()"` restores select from page filter / old input; keeps enabled.

4. **Preserve filters after create**  
   Hidden `_return_*` fields posted with create form; `store()` redirects to index with those query params.

---

## 13. Do not / invariants

- Do not invent new profile assignment tables.
- Do not filter only the current page in PHP/JS.
- Do not disable the modal profile dropdown.
- Do not overwrite existing entry profiles from the page filter.
- Do not change Lead ownership / handoff / cold-forward logic.

---

*End of LinkedIn Profile filter context.*
