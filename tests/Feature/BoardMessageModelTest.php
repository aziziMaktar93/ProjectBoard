<?php

use App\Models\Board;
use App\Models\BoardMessage;
use App\Models\User;
use App\Models\Workspace;

test('a board message belongs to a board and a user', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($user)->create();

    $message = BoardMessage::factory()->for($board)->for($user)->create(['content' => 'Hello team']);

    expect($message->board->id)->toBe($board->id);
    expect($message->user->id)->toBe($user->id);
    expect($board->messages)->toHaveCount(1);
});

test('deleting a board deletes its messages', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($user)->create();
    BoardMessage::factory()->for($board)->for($user)->create();

    $board->delete();

    expect(BoardMessage::count())->toBe(0);
});

test('board_user pivot has a nullable chat_last_read_at column', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($user)->create();

    $pivot = $board->members()->where('users.id', $user->id)->first()->pivot;

    expect($pivot->chat_last_read_at)->toBeNull();
});
