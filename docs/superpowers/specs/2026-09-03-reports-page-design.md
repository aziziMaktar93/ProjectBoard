# Reports Page — Design Spec

## Purpose

Give users a central place to generate and download reports beyond the existing single Dashboard PDF report: on-time vs late checklist completion, per-member performance, an exportable activity log, and a checklist completion timeline (which board/card/checklist an item belongs to — the ambiguity that prompted this feature, visible when two checklists share item names like "UPDATE").

## Scope

- A new `/reports` page (nav item "Reports", between "Calendar" and "Members" in the sidebar) listing the available report types with a shared scope selector (workspace / board / all — identical semantics to the existing Dashboard scope filter) and download actions.
- Four new report types, each downloadable as PDF; the Activity Log additionally offers a CSV download. The existing Dashboard PDF report is unchanged and is not duplicated — the Reports page links to `/dashboard` for it.
- Access: any board the requesting user is a member of (any role — viewer/editor/hod) is includable in scope, matching the existing Dashboard report's authorization model (implicit via query scoping to `$user->boardMemberships()`, no additional Gate).
- No new database schema — all four reports query existing tables (`cards`, `checklist_items` including the `completed_at` column added earlier this session, `card_activities`, `board_user`).

Out of scope: date-range filtering (reports cover full history within the selected board/workspace/all scope), scheduling/emailing reports, saved report configurations.

## Shared Infrastructure

**`app/Services/CardActivityDescriber.php`** (new): the `describeActivity()` match-expression currently private in `DashboardController` is extracted verbatim into `CardActivityDescriber::describe(CardActivity $activity): string`, so both `DashboardController` (updated to delegate to it) and the new `ReportsController`'s Activity Log report share one implementation. Pure refactor — no behavior change, existing Dashboard PDF tests must keep passing unchanged.

**`ReportsController::resolveScope(Request $request): array`** (private helper): reimplements the same board/workspace scoping logic already in `DashboardController::buildReportData()` (which is left untouched — not refactored into a shared service, to avoid any regression risk to the existing, already-shipped Dashboard report). Returns `boardIds`, `allBoardIds`, `workspaces`, `boards`, `selectedBoardId`, `selectedWorkspaceId`, `scopeLabel`.

## Report Types

### 1. On-Time vs Late Completion (`GET /reports/on-time-completion`)

Checklist items in scope with both `due_date` and `completed_at` set (items never completed, or completed without a due date, are excluded — there's nothing to compare). Classifies each as on-time (`completed_at`'s date ≤ `due_date`) or late. PDF shows: total compared, on-time count, late count, on-time percentage, and a table of late items (item name, checklist name, board name, due date, completed date, days late), sorted worst-first.

### 2. Member Performance (`GET /reports/member-performance`)

Per member (union of card-assignees and checklist-item-assignees, mirroring `DashboardStatsService`'s workload aggregation but extended): completed count (cards whose checklist is 100% checked, plus checked checklist items assigned to them), currently-overdue count (assigned cards/items past due and not complete), and average days late (over items that were completed after their due date). Ranked by completed count, descending.

### 3. Activity Log Export (`GET /reports/activity-log` PDF, `GET /reports/activity-log/csv` CSV)

All `CardActivity` records in scope (date, board, user, description via `CardActivityDescriber`), newest first, capped at 500 rows (a safety bound against unbounded output for long-lived boards — the same trade-off already applied to the board chat message fetch earlier this session). CSV columns: Date, Board, User, Activity.

### 4. Checklist Completion Timeline (`GET /reports/checklist-timeline`)

Every checklist item in scope with a `due_date` and/or `completed_at` set, grouped Board → Card → Checklist, each item row showing due date, completed date, and status (Done / Overdue / Pending). This directly disambiguates same-named items across multiple checklists on one card by showing the checklist name as a grouping header.

## Frontend

**`resources/js/pages/Reports.vue`** (new): mirrors `Dashboard.vue`'s workspace/board filter dropdown (same query-param-driven `router.get` pattern). Below the filter, one card per report type with a short description and a plain `<a>` download link (not an Inertia visit — these are file downloads) pointing at the relevant route with the current `workspace_id`/`board_id` query string appended; the Activity Log card has two links (PDF, CSV). A fifth card links to `/dashboard` for the existing general report.

**`resources/js/components/AppSidebar.vue`**: add a "Reports" `NavItem` (`href: '/reports'`, an appropriate icon e.g. `FileText` from `lucide-vue-next`) between "Calendar" and "Members".

## Testing

- `CardActivityDescriber` unit/feature test covering a representative activity type, plus confirming `DashboardController`'s existing report tests still pass after the refactor.
- One feature test per report type: correct rows/counts for a constructed scenario, scope filtering (board_id/workspace_id) narrows results correctly, and a user outside all boards in scope gets an empty report (not an error).
- Activity Log CSV test: correct header row and correct number of data rows.
- `Reports.vue` gets no dedicated frontend test (consistent with `Dashboard.vue`, which also has none) — the download links are plain anchors verified by the backend route tests.

## Global Constraints

- PHP: curly braces always, constructor property promotion, explicit return types, `casts()` method not `$casts` property.
- Every task needs a Pest test; `vendor/bin/pint --dirty --format agent` after PHP changes.
- No new npm/composer dependencies (PDF via existing `barryvdh/laravel-snappy`, CSV via plain `fputcsv`/`response()->streamDownload()`).
- Named routes via `route()`; follow `routes/web.php`'s existing route-registration conventions for where `/reports*` routes live.
- Vue: single root element, `<script setup lang="ts">`.
