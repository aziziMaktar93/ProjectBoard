<?php

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\CardActivity;
use App\Models\Label;
use App\Models\User;
use App\Models\Workspace;

test('a user can bulk archive cards on their board', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $first = Card::factory()->for($list)->create();
    $second = Card::factory()->for($list)->create();
    $third = Card::factory()->for($list)->create();

    $response = $this->actingAs($user)->post("/boards/{$board->id}/cards/bulk-archive", [
        'card_ids' => [$first->id, $second->id],
    ]);

    $response->assertRedirect();
    expect($first->fresh()->archived_at)->not->toBeNull();
    expect($second->fresh()->archived_at)->not->toBeNull();
    expect($third->fresh()->archived_at)->toBeNull();
    expect(CardActivity::where('card_id', $first->id)->where('type', 'archived')->count())->toBe(1);
});

test('bulk archive ignores card ids from another board', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();

    $otherBoard = Board::factory()->for($user)->create();
    $otherList = BoardList::factory()->for($otherBoard)->create();
    $otherCard = Card::factory()->for($otherList)->create();

    $this->actingAs($user)->post("/boards/{$board->id}/cards/bulk-archive", [
        'card_ids' => [$card->id, $otherCard->id],
    ]);

    expect($card->fresh()->archived_at)->not->toBeNull();
    expect($otherCard->fresh()->archived_at)->toBeNull();
});

test('a viewer cannot bulk archive cards', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $viewer = User::factory()->create();
    $workspace->members()->attach($viewer->id);
    $board->members()->attach($viewer->id, ['role' => 'viewer']);

    $response = $this->actingAs($viewer)->post("/boards/{$board->id}/cards/bulk-archive", [
        'card_ids' => [$card->id],
    ]);

    $response->assertForbidden();
    expect($card->fresh()->archived_at)->toBeNull();
});

test('a user can bulk move cards to another list', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $sourceList = BoardList::factory()->for($board)->create();
    $targetList = BoardList::factory()->for($board)->create();
    Card::factory()->for($targetList)->create(['position' => 0]);
    $first = Card::factory()->for($sourceList)->create(['position' => 0]);
    $second = Card::factory()->for($sourceList)->create(['position' => 1]);

    $response = $this->actingAs($user)->post("/boards/{$board->id}/cards/bulk-move", [
        'card_ids' => [$first->id, $second->id],
        'board_list_id' => $targetList->id,
    ]);

    $response->assertRedirect();
    expect($first->fresh()->board_list_id)->toBe($targetList->id);
    expect($second->fresh()->board_list_id)->toBe($targetList->id);
    expect($first->fresh()->position)->toBe(1);
    expect($second->fresh()->position)->toBe(2);
    expect(CardActivity::where('card_id', $first->id)->where('type', 'moved')->count())->toBe(1);
});

test('bulk move rejects a list from another board', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();

    $otherBoard = Board::factory()->for($user)->create();
    $otherList = BoardList::factory()->for($otherBoard)->create();

    $response = $this->actingAs($user)->post("/boards/{$board->id}/cards/bulk-move", [
        'card_ids' => [$card->id],
        'board_list_id' => $otherList->id,
    ]);

    $response->assertSessionHasErrors('board_list_id');
    expect($card->fresh()->board_list_id)->toBe($list->id);
});

test('a viewer cannot bulk move cards', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $targetList = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $viewer = User::factory()->create();
    $workspace->members()->attach($viewer->id);
    $board->members()->attach($viewer->id, ['role' => 'viewer']);

    $response = $this->actingAs($viewer)->post("/boards/{$board->id}/cards/bulk-move", [
        'card_ids' => [$card->id],
        'board_list_id' => $targetList->id,
    ]);

    $response->assertForbidden();
    expect($card->fresh()->board_list_id)->toBe($list->id);
});

test('a user can bulk add a label to cards', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $first = Card::factory()->for($list)->create();
    $second = Card::factory()->for($list)->create();
    $label = Label::factory()->for($board)->create(['name' => 'Urgent']);

    $response = $this->actingAs($user)->post("/boards/{$board->id}/cards/bulk-label", [
        'card_ids' => [$first->id, $second->id],
        'label_id' => $label->id,
    ]);

    $response->assertRedirect();
    expect($first->fresh()->labels()->where('labels.id', $label->id)->exists())->toBeTrue();
    expect($second->fresh()->labels()->where('labels.id', $label->id)->exists())->toBeTrue();
    expect(CardActivity::where('card_id', $first->id)->where('type', 'label_added')->count())->toBe(1);
});

test('bulk add label does not duplicate an already-applied label', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $label = Label::factory()->for($board)->create();
    $card->labels()->attach($label->id);

    $this->actingAs($user)->post("/boards/{$board->id}/cards/bulk-label", [
        'card_ids' => [$card->id],
        'label_id' => $label->id,
    ]);

    expect($card->labels()->where('labels.id', $label->id)->count())->toBe(1);
    expect(CardActivity::where('card_id', $card->id)->where('type', 'label_added')->count())->toBe(0);
});

test('bulk add label rejects a label from another board', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();

    $otherBoard = Board::factory()->for($user)->create();
    $otherLabel = Label::factory()->for($otherBoard)->create();

    $response = $this->actingAs($user)->post("/boards/{$board->id}/cards/bulk-label", [
        'card_ids' => [$card->id],
        'label_id' => $otherLabel->id,
    ]);

    $response->assertSessionHasErrors('label_id');
});

test('a viewer cannot bulk add a label', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $label = Label::factory()->for($board)->create();
    $viewer = User::factory()->create();
    $workspace->members()->attach($viewer->id);
    $board->members()->attach($viewer->id, ['role' => 'viewer']);

    $response = $this->actingAs($viewer)->post("/boards/{$board->id}/cards/bulk-label", [
        'card_ids' => [$card->id],
        'label_id' => $label->id,
    ]);

    $response->assertForbidden();
    expect($card->labels()->where('labels.id', $label->id)->exists())->toBeFalse();
});
