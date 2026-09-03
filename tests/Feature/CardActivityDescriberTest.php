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
