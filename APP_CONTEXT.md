# MaxoBiz CRM — Complete Application Context for AI Models

> **Purpose of this file:** Give DeepSeek / Cursor / any coding model enough structured knowledge to understand and safely change this CRM **without guessing**.
>
> **Companion docs:**
> - `MULTI_ROLE_ACTIVE_ROLE_CONTEXT.md` — multi-role feature + full code/diffs
> - Older docs (`LEADS_FLOW_DOCUMENTATION.md`, `FOLLOWUP_SYSTEM.md`, …) are useful but may be partially outdated; **prefer this file + live code**.

---

## 0. One-sentence summary

This is a **Krayin Laravel CRM fork** customized for MaxoBiz: a multi-role sales CRM where **SDR / LGE / Lead Closer / Admin** work leads through a pipeline, with LinkedIn sourcing, cold forwarding, meeting handoff, Meta leads, smrtPhone, and follow-ups.

---

## 1. Quick facts

| Item | Value |
|------|--------|
| Product base | Krayin CRM (`krayin/laravel-crm`) |
| Framework | Laravel + Blade + Vue-in-Blade + Vite |
| PHP packages root | `packages/Webkul/*` |
| Admin URL prefix | `/admin` (config `app.admin_path`) |
| Auth guard | `user` (session) |
| User model | `Webkul\User\Models\User` |
| ACL | Custom `Webkul\Admin\Bouncer` (NOT Silber/Bouncer) |
| Default DB | MySQL `krayin_crm` |
| Test DB | MySQL `krayin_crm_test` |
| Approx routes | ~445 |
| Approx tables | ~77 |
| Current branch context | `LGE-ROLE` (feature-heavy) |

---

## 2. Mental model (read this first)

```text
USER (identity)
  ├── assigned roles (user_roles)     e.g. SDR + LGE
  ├── active role (session)           e.g. currently acting as SDR
  ├── permissions (active role JSON)
  ├── sources / orgs (user + role scopes)
  └── pipeline stages (role_pipeline_stage)

LEAD (opportunity)
  ├── user_id            = CURRENT SALES OWNER (who works it now)
  ├── lead_owner_id      = ORIGINATOR (who created / historically owns)
  ├── person_id          = contact person
  ├── organization_id    = company
  ├── lead_pipeline_stage_id
  ├── lead_source_id / sub_source / source_link / linkedin_profile_id
  ├── services (M:N lead_service)
  ├── tags (Warm Lead / Cold Lead / …)
  └── phones live on persons.contact_numbers JSON (multiple allowed)

CRITICAL RULE:
  Opening/viewing a lead must NOT auto-change stage.
  Stage changes only via explicit actions (drag/drop, dropdown, meeting handoff, etc.).
```

### Ownership after handoff

```text
Before meeting handoff:
  user_id = SDR/LGE
  lead_owner_id = same (or set on create)

At Meeting handoff:
  user_id     → selected Lead Closer / Admin
  lead_owner_id stays originator

After handoff:
  Closer can edit
  Originator typically CANNOT open/edit (canViewLead requires user_id match in current code)
  Kanban listing may still show historical/handoff visibility via special stage helpers
```

### Cold forward (LGE → SDR)

```text
LGE creates/tags Cold Lead
  → must select SDR
  → LeadForwardService writes lead_forwards
  → ownership moves to SDR
  → LGE remains forward originator for attribution/dashboard counts
```

---

## 3. Roles in this deployment

From live `roles` table:

| id | name | permission_type | Meaning |
|----|------|-----------------|---------|
| 1 | Administrator | `all` | Full ACL; main leads/dashboard |
| 2 | SDR | `custom` | Calling role; SDR leads + SDR dashboard |
| 4 | LGE | `custom` | LinkedIn/calling role; LGE leads + LGE dashboard |
| 5 | BDE Exective | `custom` | Custom BDE role |
| 6 | Lead Closer | `custom` | Post-meeting owner; lead-clouser UI |

**Role detection is by name string** (case-insensitive) in `SourceAccessService`:

- Admin ⇒ `permission_type === 'all'`
- SDR ⇒ name `sdr`
- LGE ⇒ name `lge`
- Lead Closer ⇒ name in `lead`, `lead closer`, `lead clouser`, `lead closure`

### Multi-role (new)

- Table `user_roles` (user_id, role_id)
- Session key `active_role_id`
- Service: `Webkul\User\Services\ActiveRoleService`
- Login: 1 role → auto; N roles → `/admin/select-role`
- Header shows `Username — Role` + switcher
- `users.role_id` still exists as primary/compat (first selected role) — **do not drop without approval**

Detail + diffs: `MULTI_ROLE_ACTIVE_ROLE_CONTEXT.md`

---

## 4. Lead UI variants

Helper functions in `packages/Webkul/Admin/src/Http/helpers.php`:

- `lead_variant()` → `main` | `sdr` | `lge` | `lead_clouser`
- `lead_route()`, `lead_url()`, `lead_permission()`, `admin_menu_items()`

| Variant | URL | Named routes prefix | Who |
|---------|-----|---------------------|-----|
| main | `/admin/leads` | `admin.leads.*` | Admin |
| sdr | `/admin/leads/sdr` | `admin.leads.sdr.*` | SDR |
| lge | `/admin/leads/lge` | `admin.leads.lge.*` | LGE |
| lead_clouser | `/admin/leads/lead-clouser` | `admin.leads.lead_clouser.*` | Lead Closer |

**Note spelling:** code uses `lead_clouser` (typo) everywhere — keep it.

One controller serves all variants:

`packages/Webkul/Admin/src/Http/Controllers/Lead/LeadController.php`

Routes file:

`packages/Webkul/Admin/src/Routes/Admin/leads-routes.php`

### Common lead endpoints (repeat per variant)

| Action | Method | Example path |
|--------|--------|--------------|
| List UI | GET | `/admin/leads/sdr` |
| Kanban JSON | GET | `/admin/leads/sdr/get/{pipeline?}` |
| View | GET | `/admin/leads/sdr/view/{id}` |
| Edit page | GET | `/admin/leads/sdr/edit/{id}` |
| Edit modal data | GET | `/admin/leads/sdr/edit/{id}/form-data` |
| Update | PUT | `/admin/leads/sdr/edit/{id}` |
| Stage update | PUT | `/admin/leads/sdr/stage/edit/{id}` |
| Import | POST | `/admin/leads/sdr/import*` |
| Search | GET | `/admin/leads/sdr/search` |
| Meeting owners | GET | `/admin/leads/sdr/{id}/eligible-meeting-owners` |

Views:

- Table/kanban: `packages/Webkul/Admin/src/Resources/views/leads/index/**`
- Detail: `packages/Webkul/Admin/src/Resources/views/leads/view*.blade.php`
- Create/edit: `leads/create.blade.php`, `leads/edit.blade.php`

---

## 5. Pipeline stages (default pipeline id=1)

| sort | code | name |
|------|------|------|
| 1 | `new` | New |
| 2 | `follow-up` | Follow Up |
| 3 | `prospect` | Prospect |
| 4 | `meeting` | Meeting |
| 5 | `negotiation` | Negotiation |
| 6 | `won` | Won |
| 7 | `lost` | Lost |

Stage changes are explicit only:

- Kanban drag/drop → may prompt follow-up modal if target is `follow-up`
- Stage dropdown in table/detail
- Meeting handoff modal (requires eligible owner)
- Won/Lost modal (value/reason/closed_at)

**Never** change stage in `view()`, `edit()`, `formData()`, or GET handlers.

---

## 6. Core database schemas (columns)

### users
`id, name, email, password, status, view_permission, role_id, remember_token, created_at, updated_at, image`

- `view_permission`: `global` | `group` | `individual` (Bouncer listing helper)
- `role_id`: legacy primary role FK (still required by schema)
- Multi-role assignments: `user_roles`

### roles
`id, name, description, permission_type, permissions, created_at, updated_at`

- `permissions`: JSON array of ACL keys, e.g. `["sdr_leads.view","sdr_dashboard",…]`
- `permission_type=all` ⇒ admin bypass

### user_roles
`id, user_id, role_id, created_at, updated_at` + unique(user_id, role_id)

### leads (most important)
```
id, title, description, lead_value, status, lost_reason, closed_at,
user_id, lead_owner_id,
person_id, organization_id, team_id,
lead_source_id, lead_sub_source_id, source_sub_type, source_link, linkedin_profile_id,
lead_type_id, lead_pipeline_id, lead_pipeline_stage_id,
expected_close_date,
next_followup_date, followup_count, last_followup_date, followup_notes,
lead_disqualification_reason, lead_disqualification_comment, lead_disqualified_at,
created_at, updated_at, deleted_at
```

Soft deletes via `deleted_at`.

### persons
```
id, name, emails, contact_numbers, organization_id, job_title,
address_line, city, state, country, postcode, timezone, user_id, unique_id, …
```

`emails` and `contact_numbers` are JSON arrays, typically:

```json
[{"label":"work","value":"15552100001"},{"label":"work","value":"+1 555 200 1005"}]
```

Multi-phone helper: `Webkul\Contact\Support\ContactPhoneCollection`

### organizations
`id, name, address, user_id, …` (company)

### lead_forwards
`id, lead_id, from_user_id, to_user_id, forward_type, forwarded_at, …`  
`forward_type` example: `cold_lead` (`LeadForwardService::TYPE_COLD_LEAD`)

### services / service_user / lead_service
- `services`: offered services (`is_show` controls dropdown visibility)
- `service_user`: which closers/admins handle which services
- `lead_service`: services on a lead (drives meeting owner eligibility)

### Role scope pivots
- `role_source`, `user_source`
- `role_organization`, `user_organization`
- `role_pipeline_stage` (+ `is_shared`)

### LinkedIn
- `linkedin_entry`: request rows (`user_id`, `url`, `status`, `linkedin_profile_id`)
- `linkedin_profiles`: working profiles
- `linkedin_profile_user`: assignment

### meta_leads
Inbound Meta/FB leads; may link to CRM `lead_id`.

### activities
Meetings/calls/notes; linked via `lead_activities`.

### tags / lead_tags
Warm Lead / Cold Lead classification important for forward workflows.

---

## 7. Authorization architecture

### Request pipeline

```text
HTTP /admin/*
  middleware: web
  middleware: admin_locale
  middleware: user  (= Webkul\Admin\Http\Middleware\Bouncer)
      1) must be logged in
      2) status must be active
      3) if multi-role and no active_role_id → redirect select-role
      4) apply active role onto user
      5) if custom role has empty permissions → logout
      6) if route mapped in ACL → bouncer()->allow(permission)
  → controller
```

Registered in `packages/Webkul/Admin/src/Providers/AdminServiceProvider.php`.

### Two layers of authorization

1. **ACL permissions** (menu/routes) — Bouncer + `roles.permissions`
2. **Domain access** (which leads/sources/stages) — `SourceAccessService`

Both must pass for lead mutations.

### SourceAccessService (central domain ACL)

Path: `packages/Webkul/Lead/src/Services/SourceAccessService.php`

Key methods:

| Method | Purpose |
|--------|---------|
| `isAdmin / isSdrUser / isLgeUser / isLeadCloserUser / isCallingRoleUser` | Active-role identity |
| `getEffectiveSourceIds` | User sources ∩ role sources (or inherit) |
| `getEffectiveOrganizationIds` | Same for companies |
| `getAccessibleStageIds` | Stages from `role_pipeline_stage` (null = all) |
| `getSharedStageIds` | Shared pool stages |
| `canViewLead / canEditLead` | Per-lead read/write |
| `applyLeadOwnerVisibilityScope` | Listing owner filter |
| `applyLeadQueryScope / applyLeadTableScope` | Compose listing filters |

**Effective source/org rule (user-specific + role):**

- If user has personal sources AND role has sources → **intersection**
- If only user sources → user list
- If only role sources → role list
- If neither → unrestricted (`null`) for non-admin (treat carefully)

---

## 8. Role behavior matrix

| Capability | Admin | SDR (active) | LGE (active) | Lead Closer (active) |
|------------|-------|--------------|--------------|----------------------|
| Main leads UI | Yes | No (menu hidden) | No | No |
| SDR leads UI | Hidden for admin menu | Yes | No | No |
| LGE leads UI | Hidden | No | Yes | No |
| Lead Closer UI | Hidden | No | No | Yes |
| LinkedIn entries | If permitted | Usually no | Yes | Usually no |
| Cold forward to SDR | N/A | Receive | Initiate | N/A |
| Meeting handoff assign closer | Can be assignee | Initiate (calling) | Initiate (calling) | Receive |
| Edit after handoff (as originator) | N/A | No | No | Yes (as owner) |
| Stage up to Meeting | Yes | Yes (allowed stages) | Yes | Post-meeting stages |
| Import leads | If permitted | Yes | Yes (+ LinkedIn rules) | Limited |

After active-role switch, **same user** must get different dashboard + lead behavior.

---

## 9. Key business workflows

### A) Create lead (SDR/LGE)

```text
Form / import
  → LeadController@store / import*
  → prepare person + organization
  → set stage often to New
  → set user_id / lead_owner_id
  → sync tags/services
  → LGE may require LinkedIn source_link / profile
```

### B) Move to Follow Up

```text
Explicit stage change to follow-up
  → optional follow-up modal (auto schedule vs custom date)
  → updateStage + FollowupScheduleService
```

### C) Meeting handoff

```text
SDR/LGE moves lead to Meeting
  → must select eligible meeting owner (Admin/Closer on lead services)
  → MeetingHandoffService
  → stage=meeting, user_id=assignee, lead_owner_id preserved
  → originator locked from stage edits
```

Eligible owners query uses **`user_roles`** (not only `users.role_id`) so multi-role closers still appear.

### D) Cold forward

```text
LGE + Cold Lead tag / create / import
  → must choose SDR(s)
  → LeadForwardService::forwardColdLeadToSdr
  → lead_forwards row
  → SDR becomes working owner
```

### E) Won / Lost

```text
Explicit won/lost UI
  → updateStage with closed_at / lost_reason / value
```

### F) Import

```text
Upload Excel/CSV
  → importStart / importProcess / importRetry (chunked)
  → duplicate skip: company + email + any overlapping phone
  → row limit historically 500
  → phones: comma-separated → ContactPhoneCollection
```

### G) Multi-phone

```text
persons.contact_numbers JSON array
  → table stacks numbers + copy each
  → kanban first + “+N more”
  → detail copy each
  → search matches any number
```

---

## 10. Package map (where code lives)

```text
packages/Webkul/
  Admin/          HTTP + UI + ACL + menus + datagrids + dashboards
  Lead/           Lead models + pipeline + SourceAccess + handoff/forward/followup
  Contact/        Person, Organization, Team + phone helpers
  User/           User, Role, Group, user_roles, ActiveRoleService
  Activity/       Activities / meetings
  Attribute/      EAV custom fields
  Product/ Quote/ Warehouse/
  Email/ EmailTemplate/ Marketing/
  Tag/
  DataGrid/ DataTransfer/
  MetaLead/       Meta webhook intake
  SmrtPhone/      Calls + phone matcher
  WebForm/ Automation/ Core/ Installer/
```

Almost all product logic is in packages, not `app/`.

---

## 11. File entry points (bookmark these)

| Task | File |
|------|------|
| Login / role select / switch | `Admin/src/Http/Controllers/User/SessionController.php` |
| Active role service | `User/src/Services/ActiveRoleService.php` |
| Auth middleware | `Admin/src/Http/Middleware/Bouncer.php` |
| Permission helper | `Admin/src/Bouncer.php` |
| Helpers (variant/routes/menu) | `Admin/src/Http/helpers.php` |
| Lead HTTP API/UI | `Admin/src/Http/Controllers/Lead/LeadController.php` |
| Lead access rules | `Lead/src/Services/SourceAccessService.php` |
| Meeting handoff | `Lead/src/Services/MeetingHandoffService.php` |
| Cold forward | `Lead/src/Services/LeadForwardService.php` |
| Follow-up schedule | `Lead/src/Services/FollowupScheduleService.php` |
| Lead model | `Lead/src/Models/Lead.php` |
| Person phones | `Contact/src/Support/ContactPhoneCollection.php` |
| Users settings | `Admin/src/Http/Controllers/Settings/UserController.php` |
| Dashboard | `Admin/src/Http/Controllers/DashboardController.php` |
| LinkedIn entries | `Admin/src/Http/Controllers/LinkedInEntryController.php` |
| Menus | `Admin/src/Config/menu.php` |
| ACL keys | `Admin/src/Config/acl.php` |
| Lead routes | `Admin/src/Routes/Admin/leads-routes.php` |
| Auth routes | `Admin/src/Routes/Admin/auth-routes.php` |
| Route bootstrap | `Admin/src/Routes/Admin/web.php` |

---

## 12. Frontend conventions

- Blade views under `Admin/src/Resources/views`
- Vue components often inline in Blade (`<script type="module">` + `app.component(...)`)
- Kanban uses Sortable-style add events + `updateStage` axios PUT
- Table uses DataGrid component + edit modal loading `form-data`
- Flash messages via emitter `add-flash`
- Build: `npm run build` (Vite) when JS/CSS entrypoints change; Blade-only usually needs `php artisan view:cache` / clear views

---

## 13. Dashboards

| Route name | View audience |
|------------|---------------|
| `admin.dashboard.index` | Admin |
| `admin.dashboard.sdr` | SDR |
| `admin.dashboard.lge` | LGE (includes cold forward counts) |
| `admin.dashboard.lead_clouser` | Lead Closer |

Stats often attribute by `lead_owner_id` (originated) vs `user_id` (currently assigned).

Active role decides redirect after login/switch.

---

## 14. Integrations

### LinkedIn
LGE manages connection requests (`linkedin_entry`) and working profiles. Lead import/create can require `source_link` and profile resolution.

### MetaLead
Webhook → job → `meta_leads` → optional CRM lead creation/linking.

### smrtPhone
Inbound calls matched to `persons.contact_numbers` (any number in JSON).

### Mail / IMAP
Admin mailbox module (`/admin/mail`).

---

## 15. Testing & local DBs

```bash
# App DB
krayin_crm

# PHPUnit (phpunit.xml)
DB_DATABASE=krayin_crm_test
APP_ENV=testing

php artisan test --filter=ActiveRoleServiceTest
php artisan test --filter=SourceAccessServiceTest
php artisan view:cache
```

Note: root `.gitignore` may ignore `/tests/` broadly; some tests are tracked already, new ones may need `git add -f`.

---

## 16. Invariants — NEVER break these

1. **`user_id` = sales owner; `lead_owner_id` = originator.**
2. **No automatic stage change on view/open/click.**
3. **Active role drives SDR/LGE/Closer/Admin behavior** for multi-role users.
4. **Never trust client-sent role_id** without `user_roles` validation.
5. **Do not remove `users.role_id`** until explicitly approved.
6. **Preserve meeting handoff + cold forward + LinkedIn + multi-phone.**
7. **Keep `lead_clouser` spelling** in routes/permissions.
8. **Jobs must not depend on session active role.**
9. **Admin with `permission_type=all` bypasses ACL** but menu still hides calling UIs when active role is Admin (`admin_menu_items`).
10. **Soft deletes:** filter `deleted_at` on leads.

---

## 17. Common pitfalls for AI coding agents

| Pitfall | Correct approach |
|---------|------------------|
| Using `$user->role` only | Use `ActiveRoleService` / ensure active role bound |
| Filtering “all SDRs” via `users.role_id` | Join `user_roles` (+ fallback) |
| Assuming click opens edit and advances stage | View is read-only for stage |
| Changing ownership columns casually | Update handoff/forward services carefully |
| Editing only main lead routes | Mirror SDR/LGE/Closer variants |
| Writing new Silber/Bouncer code | Reuse Webkul Bouncer + roles.permissions |
| Migrating with `--force` blindly | Ask user; app vs test DB differ |
| Relying on outdated LEADS_FLOW doc | Re-read SourceAccessService |

---

## 18. How to approach a new task (AI playbook)

1. Identify if task is **role-sensitive** (SDR/LGE/Closer/Admin).
2. Find entry point from section 11.
3. Check whether change is HTTP, Service, Blade, or migration.
4. Preserve invariants (section 16).
5. Update **all lead variants** if touching lead routes/UI.
6. Add/adjust focused tests when possible.
7. Run `php artisan test --filter=…` and `view:cache`.
8. Document non-obvious behavior in PR/commit message.

---

## 19. Glossary

| Term | Meaning |
|------|---------|
| Active role | Session-selected role persona |
| Assigned roles | Rows in `user_roles` |
| Sales owner | `leads.user_id` |
| Originator / lead owner | `leads.lead_owner_id` |
| Calling role | SDR or LGE |
| Handoff | Transfer to Closer/Admin at Meeting |
| Cold forward | LGE → SDR with Cold Lead tag |
| Lead variant | main/sdr/lge/lead_clouser UI mode |
| Bouncer | Custom ACL helper (not Silber) |
| Shared stage | Stage visible as pool via `is_shared` |
| EAV | Custom attributes via Attribute package |
| Kanban get | JSON stages+leads endpoint |

---

## 20. Related documentation index

| File | Use for |
|------|---------|
| `APP_CONTEXT.md` (this file) | Whole-app understanding |
| `MULTI_ROLE_ACTIVE_ROLE_CONTEXT.md` | Multi-role implementation + diffs |
| `FOLLOWUP_SYSTEM.md` | Follow-up fields/automation |
| `LEADS_FLOW_DOCUMENTATION.md` | Older fetch explanation (verify vs code) |
| `USERS_FETCH_FLOW_DOCUMENTATION.md` | Users listing |
| `SOURCE_SUBTYPE_FEATURE.md` | Source subtypes |
| `NOTIFICATION_GUIDE.md` | Notifications |
| `CONTACT_FIELDS_OPTIONAL.md` | Contact fields |
| `README.md` | Upstream Krayin install basics |

---

## 21. Example: same user, two personas

```text
User: Alex
user_roles: SDR, LGE

Login → select "Alex — SDR"
  → active_role_id = SDR
  → menu: SDR Dashboard, SDR Leads
  → SourceAccessService::isSdrUser() = true
  → isLgeUser() = false

Header switch → "Alex — LGE"
  → session regenerate
  → active_role_id = LGE
  → menu: LGE Dashboard, LGE Leads, LinkedIn
  → isLgeUser() = true
  → cold forward UI available
```

---

## 22. Admin bootstrap wiring (short)

```text
AdminServiceProvider::boot
  middleware aliases: user, admin_locale
  load helpers.php
  Route group prefix admin_path + middleware web,admin_locale,user → Routes/Admin/web.php
  load views namespace admin::
  register bouncer singleton
  merge acl + menu configs
  morphMap: leads, organizations, persons, products, quotes, warehouses
```

---

**End of complete app context.**  
If implementing multi-role details or reading exact patches, open `MULTI_ROLE_ACTIVE_ROLE_CONTEXT.md` next.
