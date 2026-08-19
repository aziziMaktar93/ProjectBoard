<?php

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\User;

test('a board has many lists and a list has many cards', function () {
    $board = Board::factory()->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();

    expect($board->lists)->toHaveCount(1);
    expect($board->lists->first()->is($list))->toBeTrue();
    expect($list->cards)->toHaveCount(1);
    expect($list->cards->first()->is($card))->toBeTrue();
    expect($card->boardList->is($list))->toBeTrue();
    expect($list->board->is($board))->toBeTrue();
});

test('a user has many boards', function () {
    $user = User::factory()->create();
    Board::factory()->for($user)->count(2)->create();

    expect($user->boards)->toHaveCount(2);
});

test('the archived factory state sets archived_at', function () {
    $board = Board::factory()->archived()->create();
    $list = BoardList::factory()->archived()->create();
    $card = Card::factory()->archived()->create();

    expect($board->archived_at)->not->toBeNull();
    expect($list->archived_at)->not->toBeNull();
    expect($card->archived_at)->not->toBeNull();
});
