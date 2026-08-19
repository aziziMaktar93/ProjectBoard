<?php

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\User;

test('a user can add a card to a list on their board', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();

    $response = $this->actingAs($user)->post("/lists/{$list->id}/cards", ['name' => 'Write tests']);

    $response->assertRedirect();
    $card = $list->cards()->first();
    expect($card->name)->toBe('Write tests');
    expect($card->position)->toBe(0);
});

test('a user cannot add a card to a list on another user\'s board', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $other = User::factory()->create();

    $response = $this->actingAs($other)->post("/lists/{$list->id}/cards", ['name' => 'Write tests']);

    $response->assertForbidden();
});

test('a user can update a card\'s name and description', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();

    $response = $this->actingAs($user)->patch("/cards/{$card->id}", [
        'name' => 'Updated name',
        'description' => 'Updated description',
    ]);

    $response->assertRedirect();
    expect($card->fresh()->name)->toBe('Updated name');
    expect($card->fresh()->description)->toBe('Updated description');
});

test('a user can reorder cards within a list', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $first = Card::factory()->for($list)->create(['position' => 0]);
    $second = Card::factory()->for($list)->create(['position' => 1]);

    $response = $this->actingAs($user)->patch("/boards/{$board->id}/cards/reorder", [
        'target_list_id' => $list->id,
        'target_ordered_ids' => [$second->id, $first->id],
    ]);

    $response->assertRedirect();
    expect($second->fresh()->position)->toBe(0);
    expect($first->fresh()->position)->toBe(1);
});

test('a user can move a card to a different list', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $sourceList = BoardList::factory()->for($board)->create();
    $targetList = BoardList::factory()->for($board)->create();
    $movedCard = Card::factory()->for($sourceList)->create(['position' => 0]);
    $remainingCard = Card::factory()->for($sourceList)->create(['position' => 1]);
    $existingTargetCard = Card::factory()->for($targetList)->create(['position' => 0]);

    $response = $this->actingAs($user)->patch("/boards/{$board->id}/cards/reorder", [
        'source_list_id' => $sourceList->id,
        'target_list_id' => $targetList->id,
        'target_ordered_ids' => [$movedCard->id, $existingTargetCard->id],
        'source_ordered_ids' => [$remainingCard->id],
    ]);

    $response->assertRedirect();
    expect($movedCard->fresh()->board_list_id)->toBe($targetList->id);
    expect($movedCard->fresh()->position)->toBe(0);
    expect($existingTargetCard->fresh()->position)->toBe(1);
    expect($remainingCard->fresh()->position)->toBe(0);
});

test('reorder rejects a target list id that does not belong to the board', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $foreignList = BoardList::factory()->create();

    $response = $this->actingAs($user)->patch("/boards/{$board->id}/cards/reorder", [
        'target_list_id' => $foreignList->id,
        'target_ordered_ids' => [$card->id],
    ]);

    $response->assertSessionHasErrors('target_list_id');
});

test('reorder rejects a card id that does not belong to the source or target list', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $ownCard = Card::factory()->for($list)->create();
    $foreignCard = Card::factory()->create();

    $response = $this->actingAs($user)->patch("/boards/{$board->id}/cards/reorder", [
        'target_list_id' => $list->id,
        'target_ordered_ids' => [$ownCard->id, $foreignCard->id],
    ]);

    $response->assertSessionHasErrors('target_ordered_ids.1');
    expect($foreignCard->fresh()->board_list_id)->not->toBe($list->id);
});

test('reorder rejects a target_ordered_ids array with a duplicate id', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();

    $response = $this->actingAs($user)->patch("/boards/{$board->id}/cards/reorder", [
        'target_list_id' => $list->id,
        'target_ordered_ids' => [$card->id, $card->id],
    ]);

    $response->assertSessionHasErrors('target_ordered_ids.1');
});

test('a user can archive and restore a card', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();

    $this->actingAs($user)->patch("/cards/{$card->id}/archive")->assertRedirect();
    expect($card->fresh()->archived_at)->not->toBeNull();

    $this->actingAs($user)->patch("/cards/{$card->id}/restore")->assertRedirect();
    expect($card->fresh()->archived_at)->toBeNull();
});

test('archived cards are excluded from the board show payload', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    Card::factory()->for($list)->create(['name' => 'Visible']);
    Card::factory()->for($list)->archived()->create(['name' => 'Hidden']);

    $response = $this->actingAs($user)->get("/boards/{$board->id}");

    $response->assertInertia(
        fn ($page) => $page
            ->component('boards/Show')
            ->has('board.lists.0.cards', 1)
            ->where('board.lists.0.cards.0.name', 'Visible')
    );
});

test('a non archived card cannot be permanently deleted', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();

    $response = $this->actingAs($user)->delete("/cards/{$card->id}");

    $response->assertStatus(422);
    expect(Card::find($card->id))->not->toBeNull();
});

test('an archived card can be permanently deleted', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->archived()->create();

    $response = $this->actingAs($user)->delete("/cards/{$card->id}");

    $response->assertRedirect();
    expect(Card::find($card->id))->toBeNull();
});

test('a user cannot modify a card on another user\'s board', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $other = User::factory()->create();

    $this->actingAs($other)->patch("/cards/{$card->id}", ['name' => 'Hacked'])->assertForbidden();
    $this->actingAs($other)->patch("/cards/{$card->id}/archive")->assertForbidden();
    expect($card->fresh()->name)->not->toBe('Hacked');
});

test('a user cannot reorder cards on another user\'s board', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create(['position' => 0]);
    $other = User::factory()->create();

    $response = $this->actingAs($other)->patch("/boards/{$board->id}/cards/reorder", [
        'target_list_id' => $list->id,
        'target_ordered_ids' => [$card->id],
    ]);

    $response->assertForbidden();
});

test('a user cannot restore another user\'s archived card', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->archived()->create();
    $other = User::factory()->create();

    $response = $this->actingAs($other)->patch("/cards/{$card->id}/restore");

    $response->assertForbidden();
    expect($card->fresh()->archived_at)->not->toBeNull();
});

test('a user cannot permanently delete another user\'s archived card', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->archived()->create();
    $other = User::factory()->create();

    $response = $this->actingAs($other)->delete("/cards/{$card->id}");

    $response->assertForbidden();
    expect(Card::find($card->id))->not->toBeNull();
});
