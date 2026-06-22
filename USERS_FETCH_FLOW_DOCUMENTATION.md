# Users Fetch Flow — Documentation

**Project:** Krayin CRM
**URL:** `admin/settings/users`

---

## How Users Are Fetched

When a user visits the Users settings page, the system fetches the list of users based on the following flow.

---

### Step 1 — Middleware (Authentication & Permission Checks)

Same as the leads flow, every request passes through the Bouncer middleware first:

1. Is the user logged in? → If not, redirect to login.
2. Is the user account active? → If not, logout and redirect.
3. Does the user's role have any permissions? → If empty, logout and redirect.
4. Does the user have permission for this route (`settings.user.users.edit`)? → If not, abort with 401.

---

### Step 2 — Controller (`UserController@index`)

The controller checks if the request is AJAX. If yes, it passes the request to `UserDataGrid::process()` which returns the user data as JSON. If it is a normal page load, it returns the view with all roles and groups available in the system.

---

### Step 3 — Database Query (What Controls the Data)

The DataGrid builds the following query:

```sql
SELECT DISTINCT
    users.id,
    users.name,
    users.email,
    users.image,
    users.status,
    users.created_at

FROM users

LEFT JOIN user_groups ON users.id = user_groups.user_id

WHERE users.id IN ({authorized_user_ids})   -- role-based visibility filter
```

There is only **one condition** that controls which users are returned:

- **`users.id IN (...)`** — Only users whose IDs are in the authorized list are returned. This list is determined by the logged-in user's `view_permission` setting (explained in Step 4).

The `LEFT JOIN user_groups` is there only to support group-based filtering — it does not add any columns to the output. `DISTINCT` is used to avoid duplicate rows that could appear from that join.

---

### Step 4 — Role-Based Visibility (Who Can See Which Users)

Just like in the leads flow, `bouncer()->getAuthorizedUserIds()` determines which user IDs are allowed to be seen based on the `view_permission` field on the logged-in user:

| Permission | Behavior |
|------------|----------|
| `global` | No filter applied — **all users** in the system are returned |
| `group` | Only users who belong to the **same group(s)** as the logged-in user are returned |
| `individual` | Only the **logged-in user themselves** is returned |

#### How `group` permission works internally

When `view_permission = group`, the system runs this logic:

```
1. Get all group IDs the logged-in user belongs to
2. Find all users who are members of any of those groups
3. Return those user IDs as the filter list
```

This is handled by `UserRepository::getCurrentUserGroupsUserIds()`, which joins `users → user_groups → groups` and filters by the logged-in user's group memberships.

---

### Step 5 — Response

The query results are returned as a paginated JSON response. The frontend renders them as a table with edit and delete actions — but those action buttons themselves are also permission-gated:

- **Edit button** — only shown if the user has `settings.user.users.edit` permission
- **Delete button** — only shown if the user has `settings.user.users.delete` permission

---

## Summary

Users are fetched based on one thing only:

- **The logged-in user's `view_permission`** — controls whether they see all users, users in their group, or only themselves.

Unlike the leads flow, there is no pipeline filter or soft delete here. The only condition on the query is the role-based `user_id IN (...)` filter. If the logged-in user has `global` permission, that filter does not apply and all users are returned.
