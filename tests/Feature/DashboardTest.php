<?php

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\Checklist;
use App\Models\User;
use Barryvdh\Snappy\Facades\SnappyPdf;

test('the dashboard reports total, completed, overdue, and due soon counts', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();

    Card::factory()->for($list)->create(['name' => 'No due date']);
    Card::factory()->for($list)->overdue()->create(['name' => 'Overdue']);
    Card::factory()->for($list)->create(['name' => 'Due soon', 'due_date' => now()->addDays(2)->toDateString()]);
    Card::factory()->for($list)->archived()->create(['name' => 'Archived, excluded']);

    $completedCard = Card::factory()->for($list)->create(['name' => 'Completed']);
    $checklist = Checklist::factory()->for($completedCard)->create();
    $checklist->items()->create(['name' => 'Step', 'is_checked' => true, 'position' => 0]);

    $incompleteCard = Card::factory()->for($list)->create(['name' => 'Incomplete checklist']);
    $incompleteChecklist = Checklist::factory()->for($incompleteCard)->create();
    $incompleteChecklist->items()->create(['name' => 'Step', 'is_checked' => false, 'position' => 0]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Dashboard')
            ->where('stats.total', 5)
            ->where('stats.completed', 1)
            ->where('stats.overdue', 1)
            ->where('stats.dueSoon', 1)
            ->where('stats.checklistProgress', 50)
            ->where('hasBoards', true)
    );
});

test('the dashboard excludes cards that sit in an archived list', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $activeList = BoardList::factory()->for($board)->create(['position' => 0]);
    $archivedList = BoardList::factory()->for($board)->archived()->create(['position' => 1]);

    Card::factory()->for($activeList)->create();
    // The card itself is still active — only its list was archived — but it
    // should be invisible everywhere the board page itself doesn't show it.
    Card::factory()->for($archivedList)->count(2)->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertInertia(fn ($page) => $page->where('stats.total', 1));
});

test('checklist progress is null when no cards have a checklist', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    Card::factory()->for($list)->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertInertia(fn ($page) => $page->where('stats.checklistProgress', null));
});

test('checklist progress counts individual items, not whole cards', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $checklist = Checklist::factory()->for($card)->create();
    $checklist->items()->create(['name' => 'Done 1', 'is_checked' => true, 'position' => 0]);
    $checklist->items()->create(['name' => 'Done 2', 'is_checked' => true, 'position' => 1]);
    $checklist->items()->create(['name' => 'Not done', 'is_checked' => false, 'position' => 2]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertInertia(fn ($page) => $page->where('stats.checklistProgress', 67));
});

test('the dashboard only counts cards from boards the user is a member of', function () {
    $user = User::factory()->create();
    $myBoard = Board::factory()->for($user)->create();
    $myList = BoardList::factory()->for($myBoard)->create();
    Card::factory()->for($myList)->create();

    $otherBoard = Board::factory()->create();
    $otherList = BoardList::factory()->for($otherBoard)->create();
    Card::factory()->for($otherList)->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertInertia(fn ($page) => $page->where('stats.total', 1));
});

test('the dashboard reports workload per member', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card1 = Card::factory()->for($list)->create();
    $card2 = Card::factory()->for($list)->create();
    $card1->members()->attach($user->id);
    $card2->members()->attach($user->id);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertInertia(
        fn ($page) => $page
            ->has('workload', 1)
            ->where('workload.0.count', 2)
            ->where('workload.0.user.id', $user->id)
    );
});

test('the dashboard shows recent activity scoped to the user\'s boards', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $card->activities()->create(['user_id' => $user->id, 'type' => 'comment', 'body' => 'Hello team']);

    $otherBoard = Board::factory()->create();
    $otherList = BoardList::factory()->for($otherBoard)->create();
    $otherCard = Card::factory()->for($otherList)->create();
    $otherCard->activities()->create(['user_id' => $otherBoard->user_id, 'type' => 'comment', 'body' => 'Not visible to me']);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertInertia(
        fn ($page) => $page
            ->has('recentActivity', 1)
            ->where('recentActivity.0.body', 'Hello team')
    );
});

test('the dashboard caps recent activity at 10 entries', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();

    foreach (range(1, 12) as $i) {
        $card->activities()->create(['user_id' => $user->id, 'type' => 'comment', 'body' => "Comment {$i}"]);
    }

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertInertia(fn ($page) => $page->has('recentActivity', 10));
});

test('the dashboard can be filtered by board', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    Card::factory()->for($list)->create();

    $otherBoard = Board::factory()->for($user)->create(['workspace_id' => $board->workspace_id]);
    $otherList = BoardList::factory()->for($otherBoard)->create();
    Card::factory()->for($otherList)->count(2)->create();

    $response = $this->actingAs($user)->get("/dashboard?board_id={$board->id}");

    $response->assertInertia(
        fn ($page) => $page
            ->where('stats.total', 1)
            ->where('filters.board_id', $board->id)
    );
});

test('the dashboard can be filtered by workspace', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    Card::factory()->for($list)->create();

    $otherBoard = Board::factory()->for($user)->create();
    $otherList = BoardList::factory()->for($otherBoard)->create();
    Card::factory()->for($otherList)->count(2)->create();

    $response = $this->actingAs($user)->get("/dashboard?workspace_id={$board->workspace_id}");

    $response->assertInertia(
        fn ($page) => $page
            ->where('stats.total', 1)
            ->where('filters.workspace_id', $board->workspace_id)
    );
});

test('filtering by board reports a task breakdown by list, including empty lists', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $todo = BoardList::factory()->for($board)->create(['name' => 'To Do', 'position' => 0]);
    $inProgress = BoardList::factory()->for($board)->create(['name' => 'In Progress', 'position' => 1]);
    $done = BoardList::factory()->for($board)->create(['name' => 'Done', 'position' => 2]);
    Card::factory()->for($todo)->count(2)->create();
    Card::factory()->for($inProgress)->create();

    $response = $this->actingAs($user)->get("/dashboard?board_id={$board->id}");

    $response->assertInertia(
        fn ($page) => $page
            ->has('tasksByList', 3)
            ->where('tasksByList.0.name', 'To Do')
            ->where('tasksByList.0.count', 2)
            ->where('tasksByList.1.name', 'In Progress')
            ->where('tasksByList.1.count', 1)
            ->where('tasksByList.2.name', 'Done')
            ->where('tasksByList.2.count', 0)
    );
});

test('tasksByList is null when no specific board is selected', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    BoardList::factory()->for($board)->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertInertia(fn ($page) => $page->where('tasksByList', null));
});

test('the dashboard ignores a board_id filter for a board the user is not a member of', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    Card::factory()->for($list)->create();

    $foreignBoard = Board::factory()->create();

    $response = $this->actingAs($user)->get("/dashboard?board_id={$foreignBoard->id}");

    $response->assertInertia(fn ($page) => $page->where('stats.total', 1));
});

test('the dashboard ignores a workspace_id filter for a workspace the user is not a member of', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    Card::factory()->for($list)->create();

    $foreignWorkspace = Board::factory()->create()->workspace;

    $response = $this->actingAs($user)->get("/dashboard?workspace_id={$foreignWorkspace->id}");

    $response->assertInertia(fn ($page) => $page->where('stats.total', 1));
});

test('the dashboard reports no boards for a brand new user', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertInertia(
        fn ($page) => $page
            ->where('stats.total', 0)
            ->where('hasBoards', false)
    );
});

test('a user can download a PDF dashboard report', function () {
    SnappyPdf::fake();

    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create(['name' => 'Engineering']);
    $list = BoardList::factory()->for($board)->create();
    Card::factory()->for($list)->create();

    $response = $this->actingAs($user)->get('/dashboard/report');

    $response->assertOk();

    SnappyPdf::assertViewIs('reports.dashboard');
    SnappyPdf::assertViewHas('scopeLabel', 'All boards');
    SnappyPdf::assertViewHas('stats', fn ($stats) => $stats['total'] === 1);
    SnappyPdf::assertSee('Engineering');
});

test('the dashboard report respects the board filter', function () {
    SnappyPdf::fake();

    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create(['name' => 'Engineering']);
    $list = BoardList::factory()->for($board)->create();
    Card::factory()->for($list)->create();

    $otherBoard = Board::factory()->for($user)->create(['workspace_id' => $board->workspace_id, 'name' => 'Marketing']);
    $otherList = BoardList::factory()->for($otherBoard)->create();
    Card::factory()->for($otherList)->create();

    $response = $this->actingAs($user)->get("/dashboard/report?board_id={$board->id}");

    $response->assertOk();

    SnappyPdf::assertViewHas('scopeLabel', 'Engineering');
    SnappyPdf::assertSee('Engineering');
    SnappyPdf::assertDontSee('Marketing');
});
