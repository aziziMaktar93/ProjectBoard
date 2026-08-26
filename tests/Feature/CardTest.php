<?php

use App\Models\Attachment;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\CardActivity;
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

test('a user can duplicate a card', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create(['name' => 'Fix bug', 'color' => '#eb5a46', 'position' => 0]);

    $response = $this->actingAs($user)->post("/cards/{$card->id}/duplicate");

    $response->assertRedirect();
    $duplicate = $list->cards()->where('name', 'Fix bug (copy)')->first();
    expect($duplicate)->not->toBeNull();
    expect($duplicate->color)->toBe('#eb5a46');
    expect($duplicate->position)->toBe(1);
});

test('duplicating a card copies its checklist and items but not its members', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create([
        'name' => 'Fix bug',
        'description' => 'Details here',
        'due_date' => '2026-09-01',
        'position' => 0,
    ]);
    $card->members()->attach($user->id);
    $checklist = $card->checklists()->create(['position' => 0]);
    $checklist->items()->create(['name' => 'Step 1', 'is_checked' => true, 'position' => 0]);
    $checklist->items()->create(['name' => 'Step 2', 'is_checked' => false, 'position' => 1]);

    $this->actingAs($user)->post("/cards/{$card->id}/duplicate");

    $duplicate = $list->cards()->where('name', 'Fix bug (copy)')->first();
    expect($duplicate->description)->toBe('Details here');
    expect($duplicate->due_date)->toBe('2026-09-01');
    expect($duplicate->members)->toHaveCount(0);

    $duplicateChecklist = $duplicate->checklists()->first();
    expect($duplicateChecklist)->not->toBeNull();
    expect($duplicateChecklist->items)->toHaveCount(2);
    expect($duplicateChecklist->items()->where('name', 'Step 1')->first()->is_checked)->toBeTrue();
});

test('duplicating a card copies all of its checklists, not just the first', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $first = $card->checklists()->create(['name' => 'Design Requirements', 'position' => 0]);
    $first->items()->create(['name' => 'Step 1', 'is_checked' => true, 'position' => 0]);
    $second = $card->checklists()->create(['name' => 'Development Tasks', 'position' => 1]);
    $second->items()->create(['name' => 'Step A', 'is_checked' => false, 'position' => 0]);

    $this->actingAs($user)->post("/cards/{$card->id}/duplicate");

    $duplicate = $list->cards()->where('name', $card->name.' (copy)')->first();
    $duplicateChecklists = $duplicate->checklists()->orderBy('position')->with('items')->get();
    expect($duplicateChecklists)->toHaveCount(2);
    expect($duplicateChecklists[0]->name)->toBe('Design Requirements');
    expect($duplicateChecklists[0]->items->first()->name)->toBe('Step 1');
    expect($duplicateChecklists[1]->name)->toBe('Development Tasks');
    expect($duplicateChecklists[1]->items->first()->name)->toBe('Step A');
});

test('duplicating a card shifts the position of cards after it in the same list', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $first = Card::factory()->for($list)->create(['position' => 0]);
    $second = Card::factory()->for($list)->create(['position' => 1]);

    $this->actingAs($user)->post("/cards/{$first->id}/duplicate");

    expect($second->fresh()->position)->toBe(2);
});

test('a user cannot duplicate a card on another user\'s board', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $other = User::factory()->create();

    $response = $this->actingAs($other)->post("/cards/{$card->id}/duplicate");

    $response->assertForbidden();
    expect($list->cards()->count())->toBe(1);
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

test('a user can change and clear a card\'s color', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create(['color' => null]);

    $this->actingAs($user)->patch("/cards/{$card->id}", ['color' => '#f87168'])->assertRedirect();
    expect($card->fresh()->color)->toBe('#f87168');

    $this->actingAs($user)->patch("/cards/{$card->id}", ['color' => null])->assertRedirect();
    expect($card->fresh()->color)->toBeNull();
});

test('a user can set an image attachment as a card\'s cover and remove it', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $image = Attachment::factory()->for($card)->for($user)->create(['mime_type' => 'image/png']);

    $this->actingAs($user)->patch("/cards/{$card->id}", ['cover_attachment_id' => $image->id])->assertRedirect();
    expect($card->fresh()->cover_attachment_id)->toBe($image->id);

    $this->actingAs($user)->patch("/cards/{$card->id}", ['cover_attachment_id' => null])->assertRedirect();
    expect($card->fresh()->cover_attachment_id)->toBeNull();
});

test('a card cannot use a non-image attachment as its cover', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $pdf = Attachment::factory()->for($card)->for($user)->create(['mime_type' => 'application/pdf']);

    $response = $this->actingAs($user)->patch("/cards/{$card->id}", ['cover_attachment_id' => $pdf->id]);

    $response->assertSessionHasErrors('cover_attachment_id');
    expect($card->fresh()->cover_attachment_id)->toBeNull();
});

test('a card cannot use another card\'s attachment as its cover', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $otherCard = Card::factory()->for($list)->create();
    $image = Attachment::factory()->for($otherCard)->for($user)->create(['mime_type' => 'image/png']);

    $response = $this->actingAs($user)->patch("/cards/{$card->id}", ['cover_attachment_id' => $image->id]);

    $response->assertSessionHasErrors('cover_attachment_id');
    expect($card->fresh()->cover_attachment_id)->toBeNull();
});

test('a user can set and clear a card\'s due date', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create(['due_date' => null]);

    $this->actingAs($user)->patch("/cards/{$card->id}", ['due_date' => '2026-09-01'])->assertRedirect();
    expect($card->fresh()->due_date)->toBe('2026-09-01');

    $this->actingAs($user)->patch("/cards/{$card->id}", ['due_date' => null])->assertRedirect();
    expect($card->fresh()->due_date)->toBeNull();
});

test('setting or clearing a due date records a card activity', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create(['due_date' => null]);

    $this->actingAs($user)->patch("/cards/{$card->id}", ['due_date' => '2026-09-01'])->assertRedirect();

    expect($card->activities()->count())->toBe(1);
    $activity = $card->activities()->latest('id')->first();
    expect($activity->type)->toBe('due_date_changed');
    expect($activity->data)->toBe(['due_date' => '2026-09-01']);
    expect($activity->user_id)->toBe($user->id);

    $this->actingAs($user)->patch("/cards/{$card->id}", ['due_date' => '2026-09-01'])->assertRedirect();
    expect($card->activities()->count())->toBe(1);

    $this->actingAs($user)->patch("/cards/{$card->id}", ['due_date' => null])->assertRedirect();
    expect($card->activities()->count())->toBe(2);
    expect($card->activities()->latest('id')->first()->type)->toBe('due_date_removed');

    expect(CardActivity::where('card_id', $card->id)->count())->toBe(2);
});

test('setting a due date requires a valid date', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();

    $response = $this->actingAs($user)->patch("/cards/{$card->id}", ['due_date' => 'not-a-date']);

    $response->assertSessionHasErrors('due_date');
});

test('a user cannot set a due date on a card on another user\'s board', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create(['due_date' => null]);
    $other = User::factory()->create();

    $response = $this->actingAs($other)->patch("/cards/{$card->id}", ['due_date' => '2026-09-01']);

    $response->assertForbidden();
    expect($card->fresh()->due_date)->toBeNull();
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

test('a user can move the last card out of a list, leaving it empty', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $sourceList = BoardList::factory()->for($board)->create();
    $targetList = BoardList::factory()->for($board)->create();
    $movedCard = Card::factory()->for($sourceList)->create(['position' => 0]);

    $response = $this->actingAs($user)->patch("/boards/{$board->id}/cards/reorder", [
        'source_list_id' => $sourceList->id,
        'source_ordered_ids' => [],
        'target_list_id' => $targetList->id,
        'target_ordered_ids' => [$movedCard->id],
    ]);

    $response->assertRedirect();
    expect($movedCard->fresh()->board_list_id)->toBe($targetList->id);
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

test('reorder rejects source_ordered_ids without a source_list_id', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();

    $response = $this->actingAs($user)->patch("/boards/{$board->id}/cards/reorder", [
        'target_list_id' => $list->id,
        'target_ordered_ids' => [$card->id],
        'source_ordered_ids' => [$card->id],
    ]);

    $response->assertSessionHasErrors('source_list_id');
});

test('reorder rejects a null source_list_id when source_ordered_ids is present', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();

    $response = $this->actingAs($user)->patch("/boards/{$board->id}/cards/reorder", [
        'source_list_id' => null,
        'target_list_id' => $list->id,
        'target_ordered_ids' => [$card->id],
        'source_ordered_ids' => [$card->id],
    ]);

    $response->assertStatus(302);
    $response->assertSessionHasErrors('source_list_id');
});

test('reorder rejects target_list_id sent as an array', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();

    $response = $this->actingAs($user)->patch("/boards/{$board->id}/cards/reorder", [
        'target_list_id' => ['1', '2'],
        'target_ordered_ids' => [$card->id],
    ]);

    $response->assertStatus(302);
    $response->assertSessionHasErrors('target_list_id');
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
