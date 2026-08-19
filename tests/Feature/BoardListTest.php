<?php

use App\Models\Board;
use App\Models\BoardList;
use App\Models\User;

test('a user can add a list to their board', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();

    $response = $this->actingAs($user)->post("/boards/{$board->id}/lists", [
        'name' => 'To Do',
    ]);

    $response->assertRedirect();
    $list = $board->lists()->first();
    expect($list->name)->toBe('To Do');
    expect($list->position)->toBe(0);
});

test('a new list is appended after existing lists', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    BoardList::factory()->for($board)->create(['position' => 0]);
    BoardList::factory()->for($board)->create(['position' => 1]);

    $this->actingAs($user)->post("/boards/{$board->id}/lists", ['name' => 'Third']);

    expect($board->lists()->where('name', 'Third')->first()->position)->toBe(2);
});

test('a user cannot add a list to another user\'s board', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $other = User::factory()->create();

    $response = $this->actingAs($other)->post("/boards/{$board->id}/lists", ['name' => 'To Do']);

    $response->assertForbidden();
});

test('a user can rename a list', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create(['name' => 'Old']);

    $response = $this->actingAs($user)->patch("/lists/{$list->id}", ['name' => 'New']);

    $response->assertRedirect();
    expect($list->fresh()->name)->toBe('New');
});

test('a user can reorder lists on their board', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $first = BoardList::factory()->for($board)->create(['position' => 0]);
    $second = BoardList::factory()->for($board)->create(['position' => 1]);

    $response = $this->actingAs($user)->patch("/boards/{$board->id}/lists/reorder", [
        'ordered_ids' => [$second->id, $first->id],
    ]);

    $response->assertRedirect();
    expect($second->fresh()->position)->toBe(0);
    expect($first->fresh()->position)->toBe(1);
});

test('reorder rejects a list id that does not belong to the board', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $foreignList = BoardList::factory()->create();

    $response = $this->actingAs($user)->patch("/boards/{$board->id}/lists/reorder", [
        'ordered_ids' => [$list->id, $foreignList->id],
    ]);

    $response->assertSessionHasErrors('ordered_ids.1');
});

test('a user can archive and restore a list', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();

    $this->actingAs($user)->patch("/lists/{$list->id}/archive")->assertRedirect();
    expect($list->fresh()->archived_at)->not->toBeNull();

    $this->actingAs($user)->patch("/lists/{$list->id}/restore")->assertRedirect();
    expect($list->fresh()->archived_at)->toBeNull();
});

test('archived lists are excluded from the board show payload', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    BoardList::factory()->for($board)->create(['name' => 'Visible']);
    BoardList::factory()->for($board)->archived()->create(['name' => 'Hidden']);

    $response = $this->actingAs($user)->get("/boards/{$board->id}");

    $response->assertInertia(
        fn ($page) => $page
            ->component('boards/Show')
            ->has('board.lists', 1)
            ->where('board.lists.0.name', 'Visible')
    );
});

test('a non archived list cannot be permanently deleted', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();

    $response = $this->actingAs($user)->delete("/lists/{$list->id}");

    $response->assertStatus(422);
    expect(BoardList::find($list->id))->not->toBeNull();
});

test('an archived list can be permanently deleted', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->archived()->create();

    $response = $this->actingAs($user)->delete("/lists/{$list->id}");

    $response->assertRedirect();
    expect(BoardList::find($list->id))->toBeNull();
});

test('a user cannot modify a list on another user\'s board', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $other = User::factory()->create();

    $this->actingAs($other)->patch("/lists/{$list->id}", ['name' => 'Hacked'])->assertForbidden();
    $this->actingAs($other)->patch("/lists/{$list->id}/archive")->assertForbidden();
    expect($list->fresh()->name)->not->toBe('Hacked');
});
