<?php

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
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

test('a user can change a list\'s color', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create(['color' => null]);

    $response = $this->actingAs($user)->patch("/lists/{$list->id}", ['color' => '#4bce97']);

    $response->assertRedirect();
    expect($list->fresh()->color)->toBe('#4bce97');
});

test('a user can clear a list\'s color', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create(['color' => '#4bce97']);

    $response = $this->actingAs($user)->patch("/lists/{$list->id}", ['color' => null]);

    $response->assertRedirect();
    expect($list->fresh()->color)->toBeNull();
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

test('reorder rejects a duplicate list id', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $first = BoardList::factory()->for($board)->create(['position' => 0]);
    $second = BoardList::factory()->for($board)->create(['position' => 1]);

    $response = $this->actingAs($user)->patch("/boards/{$board->id}/lists/reorder", [
        'ordered_ids' => [$first->id, $first->id],
    ]);

    $response->assertSessionHasErrors('ordered_ids.0');
    expect($first->fresh()->position)->toBe(0);
    expect($second->fresh()->position)->toBe(1);
});

test('a user can duplicate a list', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create(['name' => 'To Do', 'color' => '#4bce97', 'position' => 0]);

    $response = $this->actingAs($user)->post("/lists/{$list->id}/duplicate");

    $response->assertRedirect();
    $duplicate = $board->lists()->where('name', 'To Do (copy)')->first();
    expect($duplicate)->not->toBeNull();
    expect($duplicate->color)->toBe('#4bce97');
    expect($duplicate->position)->toBe(1);
});

test('duplicating a list copies its active cards, checklist, and items', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create(['position' => 0]);
    $card = Card::factory()->for($list)->create([
        'name' => 'Fix bug',
        'description' => 'Details here',
        'color' => '#eb5a46',
        'due_date' => '2026-09-01',
        'position' => 0,
    ]);
    Card::factory()->for($list)->archived()->create(['name' => 'Old card']);
    $checklist = $card->checklists()->create(['position' => 0]);
    $checklist->items()->create(['name' => 'Step 1', 'is_checked' => true, 'position' => 0]);
    $checklist->items()->create(['name' => 'Step 2', 'is_checked' => false, 'position' => 1]);

    $this->actingAs($user)->post("/lists/{$list->id}/duplicate");

    $duplicateList = $board->lists()->where('id', '!=', $list->id)->first();
    expect($duplicateList->cards)->toHaveCount(1);

    $duplicateCard = $duplicateList->cards->first();
    expect($duplicateCard->name)->toBe('Fix bug');
    expect($duplicateCard->description)->toBe('Details here');
    expect($duplicateCard->color)->toBe('#eb5a46');
    expect($duplicateCard->due_date)->toBe('2026-09-01');

    $duplicateChecklist = $duplicateCard->checklists()->first();
    expect($duplicateChecklist)->not->toBeNull();
    expect($duplicateChecklist->items)->toHaveCount(2);
    expect($duplicateChecklist->items()->where('name', 'Step 1')->first()->is_checked)->toBeTrue();
});

test('duplicating a list shifts the position of lists after it', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $first = BoardList::factory()->for($board)->create(['position' => 0]);
    $second = BoardList::factory()->for($board)->create(['position' => 1]);

    $this->actingAs($user)->post("/lists/{$first->id}/duplicate");

    expect($second->fresh()->position)->toBe(2);
});

test('a user cannot duplicate a list on another user\'s board', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $other = User::factory()->create();

    $response = $this->actingAs($other)->post("/lists/{$list->id}/duplicate");

    $response->assertForbidden();
    expect($board->lists()->count())->toBe(1);
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

test('permanently deleting a list also deletes all of its cards, archived or not', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->archived()->create();
    $activeCard = Card::factory()->for($list)->create();
    $archivedCard = Card::factory()->for($list)->archived()->create();

    $response = $this->actingAs($user)->delete("/lists/{$list->id}");

    $response->assertRedirect();
    expect(BoardList::find($list->id))->toBeNull();
    expect(Card::find($activeCard->id))->toBeNull();
    expect(Card::find($archivedCard->id))->toBeNull();
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

test('a user cannot reorder lists on another user\'s board', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $first = BoardList::factory()->for($board)->create(['position' => 0]);
    $second = BoardList::factory()->for($board)->create(['position' => 1]);
    $other = User::factory()->create();

    $response = $this->actingAs($other)->patch("/boards/{$board->id}/lists/reorder", [
        'ordered_ids' => [$second->id, $first->id],
    ]);

    $response->assertForbidden();
    expect($first->fresh()->position)->toBe(0);
    expect($second->fresh()->position)->toBe(1);
});

test('a user cannot restore an archived list on another user\'s board', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->archived()->create();
    $other = User::factory()->create();

    $response = $this->actingAs($other)->patch("/lists/{$list->id}/restore");

    $response->assertForbidden();
    expect($list->fresh()->archived_at)->not->toBeNull();
});

test('a user cannot permanently delete an archived list on another user\'s board', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->archived()->create();
    $other = User::factory()->create();

    $response = $this->actingAs($other)->delete("/lists/{$list->id}");

    $response->assertForbidden();
    expect(BoardList::find($list->id))->not->toBeNull();
});
