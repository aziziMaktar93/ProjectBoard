<?php

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\Checklist;
use App\Models\User;
use App\Models\Workspace;
use App\Services\DashboardStatsService;

test('build computes total, completed, overdue, and due-soon counts', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($user)->create();
    $list = BoardList::factory()->for($board)->create();

    Card::factory()->for($list)->create(['name' => 'No due date']);
    Card::factory()->for($list)->overdue()->create(['name' => 'Overdue']);
    Card::factory()->for($list)->create(['name' => 'Due soon', 'due_date' => now()->addDays(2)->toDateString()]);

    $cards = Card::with(['boardList.board', 'checklists.items.members', 'members'])->get();

    $result = app(DashboardStatsService::class)->build($cards);

    expect($result['stats']['total'])->toBe(3);
    expect($result['stats']['overdue'])->toBe(1);
    expect($result['stats']['dueSoon'])->toBe(1);
});

test('build computes checklist progress and checklist due-date stats', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $checklist = Checklist::factory()->for($card)->create();
    $checklist->items()->create(['name' => 'Done', 'is_checked' => true, 'position' => 0]);
    $checklist->items()->create([
        'name' => 'Overdue item',
        'is_checked' => false,
        'position' => 1,
        'due_date' => now()->subDay()->toDateString(),
    ]);

    $cards = Card::with(['boardList.board', 'checklists.items.members', 'members'])->get();

    $result = app(DashboardStatsService::class)->build($cards);

    expect($result['stats']['checklistProgress'])->toBe(50);
    expect($result['stats']['checklistItemsOverdue'])->toBe(1);
});

test('build groups tasks by board and merges card and checklist-item workload', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($user)->create(['name' => 'Engineering']);
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $card->members()->attach($user->id);
    $checklist = Checklist::factory()->for($card)->create();
    $item = $checklist->items()->create(['name' => 'Step', 'is_checked' => false, 'position' => 0]);
    $item->members()->attach($user->id);

    $cards = Card::with(['boardList.board', 'checklists.items.members', 'members'])->get();

    $result = app(DashboardStatsService::class)->build($cards);

    expect($result['tasksByBoard']->first())->toBe(['name' => 'Engineering', 'count' => 1]);
    expect($result['workload']->first()['count'])->toBe(2);
});

test('build caps tasksByBoard at 8 by default', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();

    for ($i = 1; $i <= 10; $i++) {
        $board = Board::factory()->for($workspace)->for($user)->create(['name' => "Board {$i}"]);
        $list = BoardList::factory()->for($board)->create();
        Card::factory()->for($list)->count($i)->create();
    }

    $cards = Card::with(['boardList.board', 'checklists.items.members', 'members'])->get();

    $result = app(DashboardStatsService::class)->build($cards);

    expect($result['tasksByBoard']->count())->toBe(8);
});

test('build with a null limit returns the full tasksByBoard breakdown for more than 8 boards', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();

    for ($i = 1; $i <= 10; $i++) {
        $board = Board::factory()->for($workspace)->for($user)->create(['name' => "Board {$i}"]);
        $list = BoardList::factory()->for($board)->create();
        Card::factory()->for($list)->count($i)->create();
    }

    $cards = Card::with(['boardList.board', 'checklists.items.members', 'members'])->get();

    $result = app(DashboardStatsService::class)->build($cards, null);

    expect($result['tasksByBoard']->count())->toBe(10);
    expect($result['tasksByBoard']->count())->toBeGreaterThan(8);
});
