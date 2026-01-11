# Admin panel plan

## Goals
- Central place for admins to manage users/roles and monitor key system metrics.
- Every sensitive admin action must be audited.

## Current reality (already implemented)
- Roles are stored in `users.role` (string).
- Gates exist in `AuthServiceProvider`: is-admin, is-organizer, is-staff, approve-organizer-request, etc.
- Tables exist: organizer_requests, organizer_staff, account_link_*.
- SoftDeletes added to users (`deleted_at`).
- `admin_audits` exists for auditing admin actions.

## Routes (admin prefix)
- GET  /admin/dashboard                 -> AdminDashboardController@index
- GET  /admin/users                     -> AdminUserController@index
- GET  /admin/users/{user}              -> AdminUserController@show
- POST /admin/users/{user}/role         -> AdminRoleController@updateUserRole
- Organizer requests already present.

## UI pages (Jetstream-style)
- Admin Dashboard (KPI + quick links)
- Users index (search/filter/pagination)
- Users show (profile + providers + role editor + audit history)

## Auditing policy
- Any role change: action = user.role.update, target_type=user, target_id=user.id, meta={from,to}.
- Later: bans/unbans, manual merges, event admin actions.

## Next iterations (in order)
1) Users index: add filters (role, date range, deleted), plus “only tg/vk/both”.
2) Admin audit log viewer page with filters.
3) Statistics charts (registrations/deletions per day) - optional.
4) Security: admin login events and suspicious activity (failed link attempts, duplicates).

## Non-goals (for now)
- Complex BI, realtime charts, heavy exports.
