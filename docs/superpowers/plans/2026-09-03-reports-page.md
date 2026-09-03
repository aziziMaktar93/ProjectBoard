# Reports Page Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a `/reports` page offering four new downloadable reports (On-Time vs Late Completion, Member Performance, Activity Log [PDF+CSV], Checklist Completion Timeline) alongside the existing Dashboard PDF report, with a shared workspace/board scope filter.

**Architecture:** A new `ReportsController` with one action per report, each reusing a private `resolveScope()` helper (board/workspace scoping logic mirrored from `DashboardController::buildReportData()`, left untouched to avoid regression risk). PDF rendering follows the existing `SnappyPdf::loadView(...)->download(...)` pattern already used by `DashboardController::report()`; CSV uses `response()->streamDownload()` with `fputcsv`. A new `CardActivityDescriber` service extracts the existing activity-description logic so both `DashboardController` and the new Activity Log report share it. `resources/js/pages/Reports.vue` is a static filter + link page (no server round-trip needed — download links are plain anchors with query params computed client-side).

**Tech Stack:** Laravel 12 / Pest 3 (backend, using `Barryvdh\Snappy\Facades\SnappyPdf::fake()` for PDF tests), Vue 3 `<script setup lang="ts">` + Inertia v2 (frontend). No new dependencies.

## Global Constraints

- PHP: curly braces always, constructor property promotion, explicit return types, `casts()` method not `$casts` property.
- Every task needs a Pest test; run `vendor/bin/pint --dirty --format agent` after PHP changes.
- No new npm/composer dependencies.
- Named routes via `route()`, following `routes/web.php`'s existing style (`Route::get(...)->middleware(['auth', 'verified'])->name(...)`, one statement per route, no route groups introduced).
- Access control: any board the requesting user is a member of (any role) is includable — no additional Gate, matching the existing Dashboard report.
- Vue: single root element, `<script setup lang="ts">`.
- `DashboardController::buildReportData()` and `DashboardController::report()`'s overall behavior must not change (other than delegating the activity-description text to the new shared service) — existing `tests/Feature/DashboardTest.php` tests must keep passing unmodified.

---

### Task 1: `CardActivityDescriber` service (refactor)

**Files:**
- Create: `app/Services/CardActivityDescriber.php`
- Modify: `app/Http/Controllers/DashboardController.php`
- Test: `tests/Feature/CardActivityDescriberTest.php`

**Interfaces:**
- Produces: `App\Services\CardActivityDescriber::describe(CardActivity $activity): string` — identical output to the current private `DashboardController::describeActivity()` for every `CardActivity::$type` value.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\CardActivity;
use App\Models\User;
use App\Services\CardActivityDescriber;

test('describe formats a comment activity', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create(['name' => 'Fix bug']);
    $activity = CardActivity::factory()->for($card)->for($user)->create(['type' => 'comment']);

    expect(app(CardActivityDescriber::class)->describe($activity))->toBe('commented on Fix bug');
});

test('describe formats a moved activity using from/to list data', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create(['name' => 'Fix bug']);
    $activity = CardActivity::factory()->for($card)->for($user)->create([
        'type' => 'moved',
        'data' => ['from_list' => 'To Do', 'to_list' => 'Doing'],
    ]);

    expect(app(CardActivityDescriber::class)->describe($activity))->toBe('moved Fix bug from To Do to Doing');
});

test('describe formats a due_date_changed activity with a formatted date', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create(['name' => 'Fix bug']);
    $activity = CardActivity::factory()->for($card)->for($user)->create([
        'type' => 'due_date_changed',
        'data' => ['due_date' => '2026-09-01'],
    ]);

    expect(app(CardActivityDescriber::class)->describe($activity))->toBe('set the due date on Fix bug to Sep 1, 2026');
});

test('describe falls back to a generic message for an unknown type', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create(['name' => 'Fix bug']);
    $activity = CardActivity::factory()->for($card)->for($user)->create(['type' => 'archived']);

    expect(app(CardActivityDescriber::class)->describe($activity))->toBe('archived Fix bug');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=CardActivityDescriberTest`
Expected: FAIL — class `App\Services\CardActivityDescriber` does not exist.

- [ ] **Step 3: Create the service**

`app/Services/CardActivityDescriber.php` — move the existing `describeActivity()` body verbatim (read it first at `app/Http/Controllers/DashboardController.php`'s current `describeActivity()` method to copy it exactly):

```php
<?php

namespace App\Services;

use App\Models\CardActivity;
use Carbon\Carbon;

class CardActivityDescriber
{
    public function describe(CardActivity $activity): string
    {
        $cardName = $activity->card->name ?? 'a card';
        $data = $activity->data ?? [];

        return match ($activity->type) {
            'comment' => "commented on {$cardName}",
            'moved' => "moved {$cardName} from {$data['from_list']} to {$data['to_list']}",
            'checklist_item_completed' => "completed {$data['item_name']} on {$cardName}",
            'checklist_item_uncompleted' => "marked {$data['item_name']} incomplete on {$cardName}",
            'member_added' => "added {$data['member_name']} to {$cardName}",
            'member_removed' => "removed {$data['member_name']} from {$cardName}",
            'label_added' => "added the {$data['label_name']} label to {$cardName}",
            'label_removed' => "removed the {$data['label_name']} label from {$cardName}",
            'attachment_added' => "added {$data['attachment_name']} to {$cardName}",
            'attachment_removed' => "removed {$data['attachment_name']} from {$cardName}",
            'due_date_changed' => "set the due date on {$cardName} to ".Carbon::parse($data['due_date'])->format('M j, Y'),
            'due_date_removed' => "removed the due date from {$cardName}",
            'archived' => "archived {$cardName}",
            'restored' => "restored {$cardName}",
            default => "updated {$cardName}",
        };
    }
}
```

- [ ] **Step 4: Update `DashboardController` to delegate**

In `app/Http/Controllers/DashboardController.php`:
1. Add `use App\Services\CardActivityDescriber;` to the imports (alphabetical order with the others).
2. Remove the `use Carbon\Carbon;` import (no longer used in this file once the method below is deleted) and remove the now-unused `private function describeActivity(CardActivity $activity): string { ... }` method entirely.
3. Find the call site inside `report()` (`'description' => $this->describeActivity($activity),`) and replace it with `'description' => app(CardActivityDescriber::class)->describe($activity),`.

- [ ] **Step 5: Run tests**

Run: `php artisan test --compact --filter=CardActivityDescriberTest`
Expected: PASS (4 tests)

Run: `php artisan test --compact --filter=DashboardTest`
Expected: PASS, unchanged — this refactor must not break the existing Dashboard report tests (including `'a user can download a PDF dashboard report'`, which uses `SnappyPdf::assertSee(...)` against text produced by this describer).

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/CardActivityDescriber.php app/Http/Controllers/DashboardController.php tests/Feature/CardActivityDescriberTest.php
git commit -m "refactor: extract CardActivityDescriber for reuse across reports"
```

---

### Task 2: On-Time vs Late Completion report

**Files:**
- Modify: `app/Http/Controllers/ReportsController.php` (create in this task; `resolveScope()` is written here first since this is the first report task, and reused by Tasks 3-5)
- Modify: `routes/web.php`
- Create: `resources/views/reports/on-time-completion.blade.php`
- Test: `tests/Feature/ReportsTest.php` (created in this task; Tasks 3-5 append to it)

**Interfaces:**
- Produces: `ReportsController::resolveScope(Request $request): array{boardIds: \Illuminate\Support\Collection, allBoardIds: \Illuminate\Support\Collection, workspaces: \Illuminate\Support\Collection, boards: \Illuminate\Support\Collection, selectedBoardId: ?int, selectedWorkspaceId: ?int, scopeLabel: string}` — a private method other tasks' controller actions in this same class will call.
- Route `reports.on-time-completion` (GET `/reports/on-time-completion`).

- [ ] **Step 1: Write the failing test**

`tests/Feature/ReportsTest.php`:

```php
<?php

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\User;
use Barryvdh\Snappy\Facades\SnappyPdf;

test('a guest cannot access any reports route', function () {
    $this->get('/reports')->assertRedirect('/login');
    $this->get('/reports/on-time-completion')->assertRedirect('/login');
});

test('the on-time-completion report separates on-time from late items', function () {
    SnappyPdf::fake();

    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create(['name' => 'Engineering']);
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $checklist = Checklist::factory()->for($card)->create(['name' => 'Launch Checklist']);

    ChecklistItem::factory()->for($checklist)->create([
        'name' => 'On time item',
        'due_date' => '2026-09-05',
        'completed_at' => '2026-09-04 10:00:00',
        'is_checked' => true,
    ]);

    ChecklistItem::factory()->for($checklist)->create([
        'name' => 'Late item',
        'due_date' => '2026-09-01',
        'completed_at' => '2026-09-05 10:00:00',
        'is_checked' => true,
    ]);

    ChecklistItem::factory()->for($checklist)->create([
        'name' => 'Never completed',
        'due_date' => '2026-09-01',
        'completed_at' => null,
        'is_checked' => false,
    ]);

    $response = $this->actingAs($user)->get('/reports/on-time-completion');

    $response->assertOk();
    SnappyPdf::assertViewIs('reports.on-time-completion');
    SnappyPdf::assertViewHas('onTimeCount', 1);
    SnappyPdf::assertViewHas('lateCount', 1);
    SnappyPdf::assertViewHas('totalCompleted', 2);
    SnappyPdf::assertSee('Late item');
    SnappyPdf::assertDontSee('Never completed');
});

test('the on-time-completion report respects the board filter', function () {
    SnappyPdf::fake();

    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create(['name' => 'Engineering']);
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $checklist = Checklist::factory()->for($card)->create();
    ChecklistItem::factory()->for($checklist)->create([
        'name' => 'Engineering late item',
        'due_date' => '2026-09-01',
        'completed_at' => '2026-09-05 10:00:00',
        'is_checked' => true,
    ]);

    $otherBoard = Board::factory()->for($user)->create(['workspace_id' => $board->workspace_id, 'name' => 'Marketing']);
    $otherList = BoardList::factory()->for($otherBoard)->create();
    $otherCard = Card::factory()->for($otherList)->create();
    $otherChecklist = Checklist::factory()->for($otherCard)->create();
    ChecklistItem::factory()->for($otherChecklist)->create([
        'name' => 'Marketing late item',
        'due_date' => '2026-09-01',
        'completed_at' => '2026-09-05 10:00:00',
        'is_checked' => true,
    ]);

    $response = $this->actingAs($user)->get("/reports/on-time-completion?board_id={$board->id}");

    $response->assertOk();
    SnappyPdf::assertSee('Engineering late item');
    SnappyPdf::assertDontSee('Marketing late item');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=ReportsTest`
Expected: FAIL — route `reports.on-time-completion` / `/reports/on-time-completion` doesn't exist (404).

- [ ] **Step 3: Write the controller**

`app/Http/Controllers/ReportsController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\ChecklistItem;
use Barryvdh\Snappy\Facades\SnappyPdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ReportsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $allBoardIds = $user->boardMemberships()->pluck('boards.id');

        return Inertia::render('Reports', [
            'workspaces' => $user->workspaces()->orderBy('name')->get(['workspaces.id', 'workspaces.name']),
            'boards' => Board::whereIn('id', $allBoardIds)->orderBy('name')->get(['id', 'name', 'workspace_id']),
        ]);
    }

    public function onTimeCompletion(Request $request): HttpResponse
    {
        $scope = $this->resolveScope($request);

        $items = ChecklistItem::query()
            ->whereHas('checklist.card.boardList', fn ($query) => $query->whereIn('board_id', $scope['boardIds'])->whereNull('archived_at'))
            ->whereNotNull('due_date')
            ->whereNotNull('completed_at')
            ->with('checklist.card.boardList.board')
            ->get();

        $onTime = $items->filter(fn (ChecklistItem $item) => $item->completed_at->toDateString() <= $item->due_date);
        $late = $items->filter(fn (ChecklistItem $item) => $item->completed_at->toDateString() > $item->due_date);

        $lateDetails = $late->map(fn (ChecklistItem $item) => [
            'item_name' => $item->name,
            'checklist_name' => $item->checklist->name,
            'board_name' => $item->checklist->card->boardList->board->name,
            'due_date' => $item->due_date,
            'completed_at' => $item->completed_at,
            'days_late' => Carbon::parse($item->due_date)->diffInDays($item->completed_at->toDateString()),
        ])->sortByDesc('days_late')->values();

        return SnappyPdf::loadView('reports.on-time-completion', [
            'scopeLabel' => $scope['scopeLabel'],
            'totalCompleted' => $items->count(),
            'onTimeCount' => $onTime->count(),
            'lateCount' => $late->count(),
            'onTimePercent' => $items->isEmpty() ? null : (int) round($onTime->count() / $items->count() * 100),
            'lateDetails' => $lateDetails,
            'generatedAt' => now(),
        ])->download('on-time-completion-report-'.now()->format('Y-m-d').'.pdf');
    }

    /**
     * @return array{boardIds: Collection, allBoardIds: Collection, workspaces: Collection, boards: Collection, selectedBoardId: int|null, selectedWorkspaceId: int|null, scopeLabel: string}
     */
    private function resolveScope(Request $request): array
    {
        $user = $request->user();
        $allBoardIds = $user->boardMemberships()->pluck('boards.id');
        $workspaces = $user->workspaces()->orderBy('name')->get(['workspaces.id', 'workspaces.name']);
        $memberWorkspaceIds = $workspaces->pluck('id');

        $selectedBoardId = $request->integer('board_id') ?: null;
        $selectedWorkspaceId = $request->integer('workspace_id') ?: null;

        $boardIds = $allBoardIds;

        if ($selectedBoardId && $allBoardIds->contains($selectedBoardId)) {
            $boardIds = collect([$selectedBoardId]);
        } elseif ($selectedWorkspaceId && $memberWorkspaceIds->contains($selectedWorkspaceId)) {
            $boardIds = Board::whereIn('id', $allBoardIds)->where('workspace_id', $selectedWorkspaceId)->pluck('id');
        }

        $boards = Board::whereIn('id', $allBoardIds)->orderBy('name')->get(['id', 'name', 'workspace_id']);

        $scopeLabel = 'All boards';

        if ($selectedBoardId) {
            $scopeLabel = $boards->firstWhere('id', $selectedBoardId)->name ?? $scopeLabel;
        } elseif ($selectedWorkspaceId) {
            $scopeLabel = $workspaces->firstWhere('id', $selectedWorkspaceId)->name ?? $scopeLabel;
        }

        return [
            'boardIds' => $boardIds,
            'allBoardIds' => $allBoardIds,
            'workspaces' => $workspaces,
            'boards' => $boards,
            'selectedBoardId' => $selectedBoardId,
            'selectedWorkspaceId' => $selectedWorkspaceId,
            'scopeLabel' => $scopeLabel,
        ];
    }
}
```

Note: this task's controller code above imports exactly what Task 2 uses. Do not pre-add `Card`, `CardActivity`, `User`, `CardActivityDescriber`, or a `StreamedResponse` import — Tasks 3-5 add exactly the imports they need when they need them, so `vendor/bin/pint`/static analysis never sees an unused import at any single commit.

- [ ] **Step 4: Write the Blade view**

`resources/views/reports/on-time-completion.blade.php`:

```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>On-Time vs Late Completion Report</title>
    <style>
        body { font-family: 'DejaVu Sans', Arial, sans-serif; color: #1f1f1f; font-size: 12px; margin: 0; padding: 24px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .subtitle { color: #6b7280; margin: 0 0 20px; }
        h2 { font-size: 14px; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; margin: 24px 0 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #f0f0f0; }
        th { color: #6b7280; font-weight: 600; font-size: 11px; text-transform: uppercase; }
        .stat-grid { width: 100%; }
        .stat-grid td { border: none; padding: 0 12px 0 0; }
        .stat-box { border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px 12px; }
        .stat-value { font-size: 18px; font-weight: 700; display: block; }
        .stat-label { color: #6b7280; font-size: 10px; text-transform: uppercase; }
        .muted { color: #6b7280; }
        .footer { margin-top: 24px; color: #9ca3af; font-size: 10px; }
    </style>
</head>
<body>
    <h1>On-Time vs Late Completion Report</h1>
    <p class="subtitle">{{ $scopeLabel }} &middot; Generated {{ $generatedAt->format('M j, Y g:i A') }}</p>

    <table class="stat-grid">
        <tr>
            <td width="25%">
                <div class="stat-box">
                    <span class="stat-value">{{ $totalCompleted }}</span>
                    <span class="stat-label">Compared</span>
                </div>
            </td>
            <td width="25%">
                <div class="stat-box">
                    <span class="stat-value">{{ $onTimeCount }}</span>
                    <span class="stat-label">On time</span>
                </div>
            </td>
            <td width="25%">
                <div class="stat-box">
                    <span class="stat-value">{{ $lateCount }}</span>
                    <span class="stat-label">Late</span>
                </div>
            </td>
            <td width="25%">
                <div class="stat-box">
                    <span class="stat-value">{{ $onTimePercent !== null ? $onTimePercent.'%' : '—' }}</span>
                    <span class="stat-label">On-time rate</span>
                </div>
            </td>
        </tr>
    </table>

    <h2>Late items</h2>
    @if ($lateDetails->isEmpty())
        <p class="muted">No late items in scope.</p>
    @else
        <table>
            <thead>
                <tr><th>Item</th><th>Checklist</th><th>Board</th><th>Due</th><th>Completed</th><th>Days late</th></tr>
            </thead>
            <tbody>
                @foreach ($lateDetails as $row)
                    <tr>
                        <td>{{ $row['item_name'] }}</td>
                        <td>{{ $row['checklist_name'] }}</td>
                        <td>{{ $row['board_name'] }}</td>
                        <td>{{ \Carbon\Carbon::parse($row['due_date'])->format('M j, Y') }}</td>
                        <td>{{ $row['completed_at']->format('M j, Y') }}</td>
                        <td>{{ $row['days_late'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="footer">ProjectBoard &middot; {{ $generatedAt->format('Y') }}</p>
</body>
</html>
```

- [ ] **Step 5: Add the routes**

In `routes/web.php`, add `use App\Http\Controllers\ReportsController;` to the imports, and add after the `notifications/*` routes:

```php
Route::get('reports', [ReportsController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('reports.index');

Route::get('reports/on-time-completion', [ReportsController::class, 'onTimeCompletion'])
    ->middleware(['auth', 'verified'])
    ->name('reports.on-time-completion');
```

- [ ] **Step 6: Run the tests**

Run: `php artisan test --compact --filter=ReportsTest`
Expected: PASS (4 tests). Note: `reports.index`'s `Inertia::render('Reports', ...)` will fail without a `resources/js/pages/Reports.vue` file existing — Task 6 creates it. If the `'a guest cannot access any reports route'` test's `$this->get('/reports')` somehow reaches the controller for an authenticated-guest edge case, that's fine (guests get redirected to `/login` before rendering); but if any test in this task attempts an authenticated GET to `/reports` itself, skip that until Task 6 — this task's tests above only hit `/reports/on-time-completion` when authenticated.

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/ReportsController.php routes/web.php resources/views/reports/on-time-completion.blade.php tests/Feature/ReportsTest.php
git commit -m "feat: add on-time vs late completion report"
```

---

### Task 3: Member Performance report

**Files:**
- Modify: `app/Http/Controllers/ReportsController.php`
- Modify: `routes/web.php`
- Create: `resources/views/reports/member-performance.blade.php`
- Modify: `tests/Feature/ReportsTest.php`

**Interfaces:**
- Consumes: `ReportsController::resolveScope()` from Task 2.
- Route `reports.member-performance` (GET `/reports/member-performance`).

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/ReportsTest.php`:

```php
test('the member-performance report ranks members by completed count', function () {
    SnappyPdf::fake();

    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();

    $topPerformer = User::factory()->create(['name' => 'Priya']);
    $board->workspace->members()->attach($topPerformer->id);
    $board->members()->attach($topPerformer->id);

    $card1 = Card::factory()->for($list)->create();
    $checklist1 = Checklist::factory()->for($card1)->create();
    $item1 = ChecklistItem::factory()->for($checklist1)->create(['is_checked' => true]);
    $item1->members()->attach($topPerformer->id);

    $card2 = Card::factory()->for($list)->create();
    $checklist2 = Checklist::factory()->for($card2)->create();
    $item2 = ChecklistItem::factory()->for($checklist2)->create(['is_checked' => true]);
    $item2->members()->attach($topPerformer->id);

    $slower = User::factory()->create(['name' => 'Sam']);
    $board->workspace->members()->attach($slower->id);
    $board->members()->attach($slower->id);
    $card3 = Card::factory()->for($list)->create();
    $checklist3 = Checklist::factory()->for($card3)->create();
    $item3 = ChecklistItem::factory()->for($checklist3)->create(['is_checked' => false, 'due_date' => '2020-01-01']);
    $item3->members()->attach($slower->id);

    $response = $this->actingAs($owner)->get('/reports/member-performance');

    $response->assertOk();
    SnappyPdf::assertViewIs('reports.member-performance');
    SnappyPdf::assertViewHas('rows', function ($rows) use ($topPerformer, $slower) {
        $first = $rows->first();

        return $first['user']->id === $topPerformer->id
            && $first['completed'] === 2
            && $rows->firstWhere(fn ($row) => $row['user']->id === $slower->id)['overdue'] === 1;
    });
});

test('the member-performance report computes average days late', function () {
    SnappyPdf::fake();

    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $member = User::factory()->create(['name' => 'Priya']);
    $board->workspace->members()->attach($member->id);
    $board->members()->attach($member->id);

    $card = Card::factory()->for($list)->create();
    $checklist = Checklist::factory()->for($card)->create();
    $item = ChecklistItem::factory()->for($checklist)->create([
        'is_checked' => true,
        'due_date' => '2026-09-01',
        'completed_at' => '2026-09-05 10:00:00',
    ]);
    $item->members()->attach($member->id);

    $response = $this->actingAs($owner)->get('/reports/member-performance');

    $response->assertOk();
    SnappyPdf::assertViewHas('rows', fn ($rows) => $rows->first()['avg_days_late'] === 4.0);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=ReportsTest`
Expected: FAIL — route `reports.member-performance` doesn't exist.

- [ ] **Step 3: Add the controller action**

In `app/Http/Controllers/ReportsController.php`, add `use App\Models\Card;` to the imports (`Carbon\Carbon` is already imported from Task 2), then add this method (after `onTimeCompletion`):

```php
    public function memberPerformance(Request $request): HttpResponse
    {
        $scope = $this->resolveScope($request);

        $cards = Card::query()
            ->whereHas('boardList', fn ($query) => $query->whereIn('board_id', $scope['boardIds'])->whereNull('archived_at'))
            ->whereNull('archived_at')
            ->with(['checklists.items.members', 'members'])
            ->get();

        $today = now()->toDateString();
        $memberStats = [];

        foreach ($cards as $card) {
            $items = $card->checklists->flatMap(fn ($checklist) => $checklist->items);
            $cardComplete = $items->isNotEmpty() && $items->every(fn ($item) => $item->is_checked);

            foreach ($card->members as $member) {
                $memberStats[$member->id] ??= ['user' => $member, 'completed' => 0, 'overdue' => 0, 'lateDays' => []];

                if ($cardComplete) {
                    $memberStats[$member->id]['completed']++;
                } elseif ($card->due_date && $card->due_date < $today) {
                    $memberStats[$member->id]['overdue']++;
                }
            }

            foreach ($items as $item) {
                foreach ($item->members as $member) {
                    $memberStats[$member->id] ??= ['user' => $member, 'completed' => 0, 'overdue' => 0, 'lateDays' => []];

                    if ($item->is_checked) {
                        $memberStats[$member->id]['completed']++;

                        if ($item->due_date && $item->completed_at && $item->completed_at->toDateString() > $item->due_date) {
                            $memberStats[$member->id]['lateDays'][] = Carbon::parse($item->due_date)->diffInDays($item->completed_at->toDateString());
                        }
                    } elseif ($item->due_date && $item->due_date < $today) {
                        $memberStats[$member->id]['overdue']++;
                    }
                }
            }
        }

        $rows = collect($memberStats)->map(fn (array $stat) => [
            'user' => $stat['user'],
            'completed' => $stat['completed'],
            'overdue' => $stat['overdue'],
            'avg_days_late' => count($stat['lateDays']) ? round(array_sum($stat['lateDays']) / count($stat['lateDays']), 1) : null,
        ])->sortByDesc('completed')->values();

        return SnappyPdf::loadView('reports.member-performance', [
            'scopeLabel' => $scope['scopeLabel'],
            'rows' => $rows,
            'generatedAt' => now(),
        ])->download('member-performance-report-'.now()->format('Y-m-d').'.pdf');
    }
```

- [ ] **Step 4: Write the Blade view**

`resources/views/reports/member-performance.blade.php` (reuse the same `<style>` block as `reports/on-time-completion.blade.php` verbatim):

```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Member Performance Report</title>
    <style>
        body { font-family: 'DejaVu Sans', Arial, sans-serif; color: #1f1f1f; font-size: 12px; margin: 0; padding: 24px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .subtitle { color: #6b7280; margin: 0 0 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #f0f0f0; }
        th { color: #6b7280; font-weight: 600; font-size: 11px; text-transform: uppercase; }
        .muted { color: #6b7280; }
        .footer { margin-top: 24px; color: #9ca3af; font-size: 10px; }
    </style>
</head>
<body>
    <h1>Member Performance Report</h1>
    <p class="subtitle">{{ $scopeLabel }} &middot; Generated {{ $generatedAt->format('M j, Y g:i A') }}</p>

    @if ($rows->isEmpty())
        <p class="muted">No assigned tasks in scope.</p>
    @else
        <table>
            <thead>
                <tr><th>Member</th><th>Completed</th><th>Overdue</th><th>Avg days late</th></tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $row['user']->name }}</td>
                        <td>{{ $row['completed'] }}</td>
                        <td>{{ $row['overdue'] }}</td>
                        <td>{{ $row['avg_days_late'] !== null ? $row['avg_days_late'] : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="footer">ProjectBoard &middot; {{ $generatedAt->format('Y') }}</p>
</body>
</html>
```

- [ ] **Step 5: Add the route**

In `routes/web.php`, add after `reports.on-time-completion`:

```php
Route::get('reports/member-performance', [ReportsController::class, 'memberPerformance'])
    ->middleware(['auth', 'verified'])
    ->name('reports.member-performance');
```

- [ ] **Step 6: Run the tests**

Run: `php artisan test --compact --filter=ReportsTest`
Expected: PASS (6 tests)

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/ReportsController.php routes/web.php resources/views/reports/member-performance.blade.php tests/Feature/ReportsTest.php
git commit -m "feat: add member performance report"
```

---

### Task 4: Activity Log report (PDF + CSV)

**Files:**
- Modify: `app/Http/Controllers/ReportsController.php`
- Modify: `routes/web.php`
- Create: `resources/views/reports/activity-log.blade.php`
- Modify: `tests/Feature/ReportsTest.php`

**Interfaces:**
- Consumes: `ReportsController::resolveScope()` from Task 2, `App\Services\CardActivityDescriber::describe()` from Task 1.
- Routes `reports.activity-log` (GET `/reports/activity-log`, PDF) and `reports.activity-log-csv` (GET `/reports/activity-log/csv`, CSV).

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/ReportsTest.php`:

```php
test('the activity-log report lists activities newest first', function () {
    SnappyPdf::fake();

    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create(['name' => 'Engineering']);
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create(['name' => 'Fix bug']);
    $card->activities()->create(['user_id' => $user->id, 'type' => 'archived']);
    $card->activities()->create(['user_id' => $user->id, 'type' => 'restored']);

    $response = $this->actingAs($user)->get('/reports/activity-log');

    $response->assertOk();
    SnappyPdf::assertViewIs('reports.activity-log');
    SnappyPdf::assertSee('archived Fix bug');
    SnappyPdf::assertSee('restored Fix bug');
});

test('the activity-log csv export has a header row and one row per activity', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create(['name' => 'Engineering']);
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create(['name' => 'Fix bug']);
    $card->activities()->create(['user_id' => $user->id, 'type' => 'archived']);
    $card->activities()->create(['user_id' => $user->id, 'type' => 'restored']);

    $response = $this->actingAs($user)->get('/reports/activity-log/csv');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $lines = array_filter(explode("\n", $response->streamedContent()));
    expect($lines)->toHaveCount(3);
    expect($lines[0])->toBe('Date,Board,User,Activity');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=ReportsTest`
Expected: FAIL — routes don't exist.

- [ ] **Step 3: Add the controller actions**

In `app/Http/Controllers/ReportsController.php`, add these imports: `use App\Models\CardActivity;`, `use App\Services\CardActivityDescriber;`, and `use Symfony\Component\HttpFoundation\StreamedResponse;` (this is the actual return type of Laravel's `response()->streamDownload()`).

Add these two methods after `memberPerformance`:

```php
    public function activityLog(Request $request): HttpResponse
    {
        $scope = $this->resolveScope($request);
        $activities = $this->activitiesInScope($scope['boardIds']);
        $describer = app(CardActivityDescriber::class);

        return SnappyPdf::loadView('reports.activity-log', [
            'scopeLabel' => $scope['scopeLabel'],
            'activities' => $activities,
            'describer' => $describer,
            'generatedAt' => now(),
        ])->download('activity-log-report-'.now()->format('Y-m-d').'.pdf');
    }

    public function activityLogCsv(Request $request): StreamedResponse
    {
        $scope = $this->resolveScope($request);
        $activities = $this->activitiesInScope($scope['boardIds']);
        $describer = app(CardActivityDescriber::class);

        return response()->streamDownload(function () use ($activities, $describer) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date', 'Board', 'User', 'Activity']);

            foreach ($activities as $activity) {
                fputcsv($handle, [
                    $activity->created_at->format('Y-m-d H:i:s'),
                    $activity->card->boardList->board->name ?? '',
                    $activity->user->name,
                    $describer->describe($activity),
                ]);
            }

            fclose($handle);
        }, 'activity-log-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int>  $boardIds
     * @return \Illuminate\Support\Collection<int, CardActivity>
     */
    private function activitiesInScope(Collection $boardIds): Collection
    {
        return CardActivity::query()
            ->whereHas('card.boardList', fn ($query) => $query->whereIn('board_id', $boardIds)->whereNull('archived_at'))
            ->with(['user', 'card.boardList.board'])
            ->latest()
            ->limit(500)
            ->get();
    }
```

- [ ] **Step 4: Write the Blade view**

`resources/views/reports/activity-log.blade.php` (same `<style>` block as the other two reports):

```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Activity Log Report</title>
    <style>
        body { font-family: 'DejaVu Sans', Arial, sans-serif; color: #1f1f1f; font-size: 12px; margin: 0; padding: 24px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .subtitle { color: #6b7280; margin: 0 0 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #f0f0f0; }
        th { color: #6b7280; font-weight: 600; font-size: 11px; text-transform: uppercase; }
        .muted { color: #6b7280; }
        .footer { margin-top: 24px; color: #9ca3af; font-size: 10px; }
    </style>
</head>
<body>
    <h1>Activity Log Report</h1>
    <p class="subtitle">{{ $scopeLabel }} &middot; Generated {{ $generatedAt->format('M j, Y g:i A') }}</p>

    @if ($activities->isEmpty())
        <p class="muted">No activity in scope.</p>
    @else
        <table>
            <thead>
                <tr><th width="18%">When</th><th width="20%">Board</th><th width="15%">User</th><th>Activity</th></tr>
            </thead>
            <tbody>
                @foreach ($activities as $activity)
                    <tr>
                        <td>{{ $activity->created_at->format('M j, Y g:i A') }}</td>
                        <td>{{ $activity->card->boardList->board->name ?? '—' }}</td>
                        <td>{{ $activity->user->name }}</td>
                        <td>{{ $describer->describe($activity) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="footer">ProjectBoard &middot; {{ $generatedAt->format('Y') }}</p>
</body>
</html>
```

- [ ] **Step 5: Add the routes**

In `routes/web.php`, add after `reports.member-performance`:

```php
Route::get('reports/activity-log', [ReportsController::class, 'activityLog'])
    ->middleware(['auth', 'verified'])
    ->name('reports.activity-log');

Route::get('reports/activity-log/csv', [ReportsController::class, 'activityLogCsv'])
    ->middleware(['auth', 'verified'])
    ->name('reports.activity-log-csv');
```

- [ ] **Step 6: Run the tests**

Run: `php artisan test --compact --filter=ReportsTest`
Expected: PASS (8 tests)

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/ReportsController.php routes/web.php resources/views/reports/activity-log.blade.php tests/Feature/ReportsTest.php
git commit -m "feat: add activity log report with PDF and CSV export"
```

---

### Task 5: Checklist Completion Timeline report

**Files:**
- Modify: `app/Http/Controllers/ReportsController.php`
- Modify: `routes/web.php`
- Create: `resources/views/reports/checklist-timeline.blade.php`
- Modify: `tests/Feature/ReportsTest.php`

**Interfaces:**
- Consumes: `ReportsController::resolveScope()` from Task 2.
- Route `reports.checklist-timeline` (GET `/reports/checklist-timeline`).

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/ReportsTest.php`:

```php
test('the checklist-timeline report groups items by board, card, and checklist', function () {
    SnappyPdf::fake();

    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create(['name' => 'Engineering']);
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create(['name' => 'Sprint 1']);

    $checklistA = Checklist::factory()->for($card)->create(['name' => 'GPPK100']);
    ChecklistItem::factory()->for($checklistA)->create(['name' => 'UPDATE', 'due_date' => '2026-08-26', 'is_checked' => false]);

    $checklistB = Checklist::factory()->for($card)->create(['name' => 'GPPK200']);
    ChecklistItem::factory()->for($checklistB)->create(['name' => 'UPDATE', 'due_date' => '2026-09-01', 'completed_at' => '2026-08-30', 'is_checked' => true]);

    $response = $this->actingAs($user)->get('/reports/checklist-timeline');

    $response->assertOk();
    SnappyPdf::assertViewIs('reports.checklist-timeline');
    SnappyPdf::assertSee('Engineering');
    SnappyPdf::assertSee('Sprint 1');
    SnappyPdf::assertSee('GPPK100');
    SnappyPdf::assertSee('GPPK200');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=ReportsTest`
Expected: FAIL — route doesn't exist.

- [ ] **Step 3: Add the controller action**

In `app/Http/Controllers/ReportsController.php`, ensure `use App\Models\ChecklistItem;` is present (added in Task 2), then add after `activityLogCsv`:

```php
    public function checklistTimeline(Request $request): HttpResponse
    {
        $scope = $this->resolveScope($request);
        $today = now()->toDateString();

        $items = ChecklistItem::query()
            ->whereHas('checklist.card', fn ($query) => $query->whereNull('archived_at'))
            ->whereHas('checklist.card.boardList', fn ($query) => $query->whereIn('board_id', $scope['boardIds'])->whereNull('archived_at'))
            ->where(fn ($query) => $query->whereNotNull('due_date')->orWhereNotNull('completed_at'))
            ->with('checklist.card.boardList.board')
            ->get();

        $grouped = $items
            ->groupBy(fn (ChecklistItem $item) => $item->checklist->card->boardList->board->name)
            ->map(fn ($boardItems) => $boardItems
                ->groupBy(fn (ChecklistItem $item) => $item->checklist->card->name)
                ->map(fn ($cardItems) => $cardItems
                    ->groupBy(fn (ChecklistItem $item) => $item->checklist->name)
                    ->map(fn ($checklistItems) => $checklistItems->map(fn (ChecklistItem $item) => [
                        'name' => $item->name,
                        'due_date' => $item->due_date,
                        'completed_at' => $item->completed_at,
                        'status' => $item->is_checked
                            ? 'Done'
                            : ($item->due_date && $item->due_date < $today ? 'Overdue' : 'Pending'),
                    ]))));

        return SnappyPdf::loadView('reports.checklist-timeline', [
            'scopeLabel' => $scope['scopeLabel'],
            'grouped' => $grouped,
            'generatedAt' => now(),
        ])->download('checklist-timeline-report-'.now()->format('Y-m-d').'.pdf');
    }
```

- [ ] **Step 4: Write the Blade view**

`resources/views/reports/checklist-timeline.blade.php`:

```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Checklist Completion Timeline Report</title>
    <style>
        body { font-family: 'DejaVu Sans', Arial, sans-serif; color: #1f1f1f; font-size: 12px; margin: 0; padding: 24px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .subtitle { color: #6b7280; margin: 0 0 20px; }
        h2 { font-size: 16px; margin: 20px 0 6px; }
        h3 { font-size: 13px; margin: 12px 0 4px; color: #374151; }
        h4 { font-size: 11px; text-transform: uppercase; color: #6b7280; margin: 8px 0 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { text-align: left; padding: 4px 8px; border-bottom: 1px solid #f0f0f0; }
        th { color: #6b7280; font-weight: 600; font-size: 10px; text-transform: uppercase; }
        .muted { color: #6b7280; }
        .footer { margin-top: 24px; color: #9ca3af; font-size: 10px; }
    </style>
</head>
<body>
    <h1>Checklist Completion Timeline Report</h1>
    <p class="subtitle">{{ $scopeLabel }} &middot; Generated {{ $generatedAt->format('M j, Y g:i A') }}</p>

    @if ($grouped->isEmpty())
        <p class="muted">No checklist items with a due date or completion date in scope.</p>
    @else
        @foreach ($grouped as $boardName => $cards)
            <h2>{{ $boardName }}</h2>
            @foreach ($cards as $cardName => $checklists)
                <h3>{{ $cardName }}</h3>
                @foreach ($checklists as $checklistName => $items)
                    <h4>{{ $checklistName }}</h4>
                    <table>
                        <thead>
                            <tr><th>Item</th><th>Due</th><th>Completed</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($items as $item)
                                <tr>
                                    <td>{{ $item['name'] }}</td>
                                    <td>{{ $item['due_date'] ? \Carbon\Carbon::parse($item['due_date'])->format('M j, Y') : '—' }}</td>
                                    <td>{{ $item['completed_at'] ? $item['completed_at']->format('M j, Y') : '—' }}</td>
                                    <td>{{ $item['status'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endforeach
            @endforeach
        @endforeach
    @endif

    <p class="footer">ProjectBoard &middot; {{ $generatedAt->format('Y') }}</p>
</body>
</html>
```

- [ ] **Step 5: Add the route**

In `routes/web.php`, add after `reports.activity-log-csv`:

```php
Route::get('reports/checklist-timeline', [ReportsController::class, 'checklistTimeline'])
    ->middleware(['auth', 'verified'])
    ->name('reports.checklist-timeline');
```

- [ ] **Step 6: Run the tests**

Run: `php artisan test --compact --filter=ReportsTest`
Expected: PASS (9 tests)

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/ReportsController.php routes/web.php resources/views/reports/checklist-timeline.blade.php tests/Feature/ReportsTest.php
git commit -m "feat: add checklist completion timeline report"
```

---

### Task 6: `Reports.vue` page and navigation

**Files:**
- Create: `resources/js/pages/Reports.vue`
- Modify: `resources/js/components/AppSidebar.vue`
- Modify: `tests/Feature/ReportsTest.php`

**Interfaces:**
- Consumes: `reports.index` (Inertia render, props `workspaces: {id, name}[]`, `boards: {id, name, workspace_id}[]` from Task 2's `ReportsController::index()`), and every report download route from Tasks 2-5 (`reports.on-time-completion`, `reports.member-performance`, `reports.activity-log`, `reports.activity-log-csv`, `reports.checklist-timeline`), plus `route('dashboard')` for the existing general report link.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/ReportsTest.php`:

```php
test('a user can view the reports index page', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create(['name' => 'Engineering']);

    $response = $this->actingAs($user)->get('/reports');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Reports')
        ->has('boards', 1)
        ->where('boards.0.name', 'Engineering'));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=ReportsTest`
Expected: FAIL — `resources/js/pages/Reports.vue` doesn't exist, so Inertia rendering fails.

- [ ] **Step 3: Write the page**

`resources/js/pages/Reports.vue`:

```vue
<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { Download, FileSpreadsheet, Kanban, ListFilter } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    workspaces: { id: number; name: string }[];
    boards: { id: number; name: string; workspace_id: number }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Reports', href: '/reports' }];

const ALL = 'all';
const selectedWorkspace = ref<string>(ALL);
const selectedBoard = ref<string>(ALL);

const availableBoards = computed(() =>
    selectedWorkspace.value === ALL ? props.boards : props.boards.filter((b) => String(b.workspace_id) === selectedWorkspace.value),
);

function onWorkspaceChange(value: string) {
    selectedWorkspace.value = value;

    if (selectedBoard.value !== ALL && !availableBoards.value.some((b) => String(b.id) === selectedBoard.value)) {
        selectedBoard.value = ALL;
    }
}

function reportUrl(name: string): string {
    return route(name, {
        workspace_id: selectedWorkspace.value === ALL ? undefined : selectedWorkspace.value,
        board_id: selectedBoard.value === ALL ? undefined : selectedBoard.value,
    });
}

const onTimeUrl = computed(() => reportUrl('reports.on-time-completion'));
const memberPerformanceUrl = computed(() => reportUrl('reports.member-performance'));
const activityLogUrl = computed(() => reportUrl('reports.activity-log'));
const activityLogCsvUrl = computed(() => reportUrl('reports.activity-log-csv'));
const checklistTimelineUrl = computed(() => reportUrl('reports.checklist-timeline'));

const reportCards = computed(() => [
    { title: 'On-Time vs Late Completion', description: 'Checklist items compared against their due date.', href: onTimeUrl.value },
    { title: 'Member Performance', description: 'Completed, overdue, and average days late per member.', href: memberPerformanceUrl.value },
    { title: 'Checklist Completion Timeline', description: 'Every checklist item grouped by board, card, and checklist.', href: checklistTimelineUrl.value },
]);
</script>

<template>
    <Head title="Reports" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-lg font-semibold">Reports</h1>
                    <p class="text-sm text-muted-foreground">Download reports across every board you belong to.</p>
                </div>

                <div class="flex flex-wrap items-center gap-1.5 rounded-xl border border-black/5 bg-black/[0.03] p-1.5 dark:border-white/10 dark:bg-white/5">
                    <div class="flex items-center gap-1.5 pl-2 text-xs font-medium text-muted-foreground">
                        <ListFilter class="size-3.5" />
                        Scope
                    </div>

                    <Select :model-value="selectedWorkspace" @update:model-value="onWorkspaceChange">
                        <SelectTrigger class="h-8 w-48 gap-1.5 text-xs">
                            <span class="flex min-w-0 items-center gap-1.5 truncate">
                                <Kanban class="size-3.5 shrink-0 text-muted-foreground" />
                                <SelectValue placeholder="All workspaces" />
                            </span>
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">All workspaces</SelectItem>
                            <SelectItem v-for="workspace in workspaces" :key="workspace.id" :value="String(workspace.id)">
                                {{ workspace.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <Select :model-value="selectedBoard" @update:model-value="(value) => (selectedBoard = String(value))">
                        <SelectTrigger class="h-8 w-48 text-xs">
                            <SelectValue placeholder="All boards" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">All boards</SelectItem>
                            <SelectItem v-for="board in availableBoards" :key="board.id" :value="String(board.id)">
                                {{ board.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-xl border border-border p-4">
                    <h3 class="font-semibold">Dashboard Overview</h3>
                    <p class="mt-1 text-sm text-muted-foreground">Overall stats, tasks by board, and workload — the general report.</p>
                    <Button as-child class="mt-3" variant="outline">
                        <Link :href="route('dashboard')">Go to Dashboard</Link>
                    </Button>
                </div>

                <div v-for="card in reportCards" :key="card.title" class="rounded-xl border border-border p-4">
                    <h3 class="font-semibold">{{ card.title }}</h3>
                    <p class="mt-1 text-sm text-muted-foreground">{{ card.description }}</p>
                    <Button as-child class="mt-3">
                        <a :href="card.href"><Download class="size-3.5" /> Download PDF</a>
                    </Button>
                </div>

                <div class="rounded-xl border border-border p-4">
                    <h3 class="font-semibold">Activity Log</h3>
                    <p class="mt-1 text-sm text-muted-foreground">Every logged card activity in scope.</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <Button as-child>
                            <a :href="activityLogUrl"><Download class="size-3.5" /> PDF</a>
                        </Button>
                        <Button as-child variant="outline">
                            <a :href="activityLogCsvUrl"><FileSpreadsheet class="size-3.5" /> CSV</a>
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
```

- [ ] **Step 4: Add the nav item**

In `resources/js/components/AppSidebar.vue`, add `FileText` to the `lucide-vue-next` import list, and insert a new entry in `mainNavItems` between "Calendar" and "Members":

```typescript
    {
        title: 'Reports',
        href: '/reports',
        icon: FileText,
    },
```

- [ ] **Step 5: Run the test and build**

Run: `php artisan test --compact --filter=ReportsTest`
Expected: PASS (10 tests)

Run: `npm run build`
Expected: succeeds with no TypeScript/Vue compiler errors.

- [ ] **Step 6: Commit**

```bash
git add resources/js/pages/Reports.vue resources/js/components/AppSidebar.vue tests/Feature/ReportsTest.php
git commit -m "feat: add Reports page with scope filter and navigation"
```

---

### Task 7: Final verification pass

**Files:** None created — this task only runs the full verification chain and fixes anything it surfaces.

- [ ] **Step 1: Full backend test suite**

Run: `php artisan test --compact`
Expected: all tests pass, including every `ReportsTest` and `CardActivityDescriberTest` case, and `DashboardTest` unchanged.

- [ ] **Step 2: Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: passes cleanly, or auto-fixes remaining style issues in files touched by this plan only.

- [ ] **Step 3: Frontend build**

Run: `npm run build`
Expected: succeeds with no TypeScript or Vue compiler errors.

- [ ] **Step 4: Commit any fixes**

If Steps 1-3 required changes, stage exactly the files touched and commit:

```bash
git add -A -- . ':!debug.log'
git commit -m "fix: address issues found in reports page final verification"
```

If no changes were needed, skip this step.
