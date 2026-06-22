# Leads Fetch Flow — Documentation

**Project:** Maxobiz CRM
**URL:** `admin/leads`

---

## How Leads Are Fetched

When a user visits `admin/leads`, the request goes through the following flow before any data is returned.

---

### Step 1 — Pipeline Selection (Controller)

The controller determines **which pipeline** to scope the data to. Every lead in the system belongs to a pipeline. If a `pipeline_id` is provided in the URL, that pipeline is used. Otherwise, the system falls back to the **default pipeline**. This pipeline ID is then used as a filter in the database query — meaning only leads belonging to that pipeline will be fetched.

Pipeline **stages** have no filtering role here. On the list view, all leads from the selected pipeline are returned regardless of which stage they are in. The stage is only fetched as a display value so the user can see where each lead currently sits in the pipeline.

---

### Step 2 — Database Query (What Controls the Data)

The DataGrid builds a SQL query with the following three conditions that directly control which leads are returned:

- **`leads.deleted_at IS NULL`** — Leads are never physically deleted. When a lead is removed, only the `deleted_at` timestamp is set (soft delete). This condition excludes those leads from the results.

- **`leads.lead_pipeline_id = {pipeline_id}`** — Only leads belonging to the currently selected pipeline are returned. Leads in other pipelines are ignored.

- **`leads.user_id IN (...)`** — Only leads assigned to authorized users are returned. The list of user IDs here is determined by the logged-in user's `view_permission` setting (explained in Step 4).

Beyond these filters, the query joins eight additional tables (`users`, `persons`, `lead_types`, `lead_pipeline_stages`, `lead_sources`, `lead_pipelines`, `lead_tags`, `tags`) purely to pull in display data like sales person name, contact name, source, stage name, and tags. A `GROUP BY leads.id` is applied because a lead can have multiple tags, which would otherwise produce duplicate rows.

---

### Step 3 — Role-Based Visibility (Who Can See What)

The `user_id IN (...)` condition in the query is populated by `bouncer()->getAuthorizedUserIds()`, which checks the logged-in user's `view_permission` setting:

| Permission | Behavior |
|------------|----------|
| `global` | No filter applied — the user sees **all leads** in the system |
| `group` | Only leads assigned to **members of the user's group** are returned |
| `individual` | Only leads assigned to **the logged-in user themselves** are returned |

---

### Step 5 — Response

The query results are returned as a paginated JSON response to the frontend, which renders them as a list table or Kanban board.

---

## Summary

Leads are fetched based on three things only:

1. **The selected pipeline** — only leads in that pipeline are included.
2. **Soft delete status** — deleted leads are always excluded.
3. **The user's view permission** — controls whether the user sees all leads, their group's leads, or only their own.

Pipeline stages play no role in filtering on the list view — they are display data only. Stage-based filtering only applies in the Kanban view, where leads are grouped and loaded per stage.
