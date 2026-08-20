<?php

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\User;
use App\Models\Workspace;

test('a board member can assign another board member to a card', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $member = User::factory()->create();
    $workspace->members()->attach($member->id);
    $board->members()->attach($member->id);

    $response = $this->actingAs($owner)->post("/cards/{$card->id}/members", ['user_id' => $member->id]);

    $response->assertRedirect();
    expect($card->members()->where('users.id', $member->id)->exists())->toBeTrue();
});

test('a user who is not a board member cannot be assigned to a card', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $outsider = User::factory()->create();

    $response = $this->actingAs($owner)->post("/cards/{$card->id}/members", ['user_id' => $outsider->id]);

    $response->assertSessionHasErrors('user_id');
    expect($card->members()->where('users.id', $outsider->id)->exists())->toBeFalse();
});

test('a non-board-member cannot assign a card member', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $outsider = User::factory()->create();

    $response = $this->actingAs($outsider)->post("/cards/{$card->id}/members", ['user_id' => $owner->id]);

    $response->assertForbidden();
});

test('a board member can unassign a card member', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $card->members()->attach($owner->id);

    $response = $this->actingAs($owner)->delete("/cards/{$card->id}/members/{$owner->id}");

    $response->assertRedirect();
    expect($card->members()->where('users.id', $owner->id)->exists())->toBeFalse();
});

test('a non-board-member cannot unassign a card member', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $card->members()->attach($owner->id);
    $outsider = User::factory()->create();

    $response = $this->actingAs($outsider)->delete("/cards/{$card->id}/members/{$owner->id}");

    $response->assertForbidden();
    expect($card->members()->where('users.id', $owner->id)->exists())->toBeTrue();
});

test('the board show page includes card members', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $card->members()->attach($owner->id);

    $response = $this->actingAs($owner)->get("/boards/{$board->id}");

    $response->assertInertia(
        fn ($page) => $page
            ->component('boards/Show')
            ->where('board.lists.0.cards.0.members.0.id', $owner->id)
    );
});
