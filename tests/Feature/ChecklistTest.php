<?php

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\User;

test('a user can add a checklist to a card on their board', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();

    $response = $this->actingAs($user)->post("/cards/{$card->id}/checklists");

    $response->assertRedirect();
    $checklist = $card->checklists()->first();
    expect($checklist)->not->toBeNull();
    expect($checklist->position)->toBe(0);
});

test('a card can only have one checklist', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    Checklist::factory()->for($card)->create();

    $response = $this->actingAs($user)->post("/cards/{$card->id}/checklists");

    $response->assertStatus(422);
    expect($card->checklists()->count())->toBe(1);
});

test('a user can add a new checklist after deleting the card\'s existing one', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $first = Checklist::factory()->for($card)->create();

    $this->actingAs($user)->delete("/checklists/{$first->id}")->assertRedirect();

    $response = $this->actingAs($user)->post("/cards/{$card->id}/checklists");

    $response->assertRedirect();
    expect($card->checklists()->count())->toBe(1);
    expect($card->checklists()->first()->is($first))->toBeFalse();
});

test('a user cannot add a checklist to a card on another user\'s board', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $other = User::factory()->create();

    $response = $this->actingAs($other)->post("/cards/{$card->id}/checklists");

    $response->assertForbidden();
});

test('a user can delete a checklist from their card', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $checklist = Checklist::factory()->for($card)->create();

    $response = $this->actingAs($user)->delete("/checklists/{$checklist->id}");

    $response->assertRedirect();
    expect(Checklist::find($checklist->id))->toBeNull();
});

test('deleting a checklist also deletes its items', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $checklist = Checklist::factory()->for($card)->create();
    $item = ChecklistItem::factory()->for($checklist)->create();

    $this->actingAs($user)->delete("/checklists/{$checklist->id}");

    expect(ChecklistItem::find($item->id))->toBeNull();
});

test('a user cannot delete a checklist on another user\'s board', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $checklist = Checklist::factory()->for($card)->create();
    $other = User::factory()->create();

    $response = $this->actingAs($other)->delete("/checklists/{$checklist->id}");

    $response->assertForbidden();
    expect(Checklist::find($checklist->id))->not->toBeNull();
});

test('a user can add an item to a checklist on their board', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $checklist = Checklist::factory()->for($card)->create();

    $response = $this->actingAs($user)->post("/checklists/{$checklist->id}/items", ['name' => 'UI Design']);

    $response->assertRedirect();
    $item = $checklist->items()->first();
    expect($item->name)->toBe('UI Design');
    expect($item->is_checked)->toBeFalse();
    expect($item->position)->toBe(0);
});

test('a new item is appended after existing items', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $checklist = Checklist::factory()->for($card)->create();
    ChecklistItem::factory()->for($checklist)->create(['position' => 0]);

    $this->actingAs($user)->post("/checklists/{$checklist->id}/items", ['name' => 'Dashboard']);

    expect($checklist->items()->where('name', 'Dashboard')->first()->position)->toBe(1);
});

test('a user cannot add an item to a checklist on another user\'s board', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $checklist = Checklist::factory()->for($card)->create();
    $other = User::factory()->create();

    $response = $this->actingAs($other)->post("/checklists/{$checklist->id}/items", ['name' => 'UI Design']);

    $response->assertForbidden();
});

test('a user can check and uncheck a checklist item', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $checklist = Checklist::factory()->for($card)->create();
    $item = ChecklistItem::factory()->for($checklist)->create(['is_checked' => false]);

    $this->actingAs($user)->patch("/checklist-items/{$item->id}", ['is_checked' => true])->assertRedirect();
    expect($item->fresh()->is_checked)->toBeTrue();

    $this->actingAs($user)->patch("/checklist-items/{$item->id}", ['is_checked' => false])->assertRedirect();
    expect($item->fresh()->is_checked)->toBeFalse();
});

test('a user can rename a checklist item', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $checklist = Checklist::factory()->for($card)->create();
    $item = ChecklistItem::factory()->for($checklist)->create(['name' => 'Old']);

    $response = $this->actingAs($user)->patch("/checklist-items/{$item->id}", ['name' => 'New']);

    $response->assertRedirect();
    expect($item->fresh()->name)->toBe('New');
});

test('a user cannot update a checklist item on another user\'s board', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $checklist = Checklist::factory()->for($card)->create();
    $item = ChecklistItem::factory()->for($checklist)->create(['is_checked' => false]);
    $other = User::factory()->create();

    $response = $this->actingAs($other)->patch("/checklist-items/{$item->id}", ['is_checked' => true]);

    $response->assertForbidden();
    expect($item->fresh()->is_checked)->toBeFalse();
});

test('a user can delete a checklist item', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $checklist = Checklist::factory()->for($card)->create();
    $item = ChecklistItem::factory()->for($checklist)->create();

    $response = $this->actingAs($user)->delete("/checklist-items/{$item->id}");

    $response->assertRedirect();
    expect(ChecklistItem::find($item->id))->toBeNull();
});

test('a user cannot delete a checklist item on another user\'s board', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $checklist = Checklist::factory()->for($card)->create();
    $item = ChecklistItem::factory()->for($checklist)->create();
    $other = User::factory()->create();

    $response = $this->actingAs($other)->delete("/checklist-items/{$item->id}");

    $response->assertForbidden();
    expect(ChecklistItem::find($item->id))->not->toBeNull();
});

test('the board show page includes checklists and items nested under cards', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $checklist = Checklist::factory()->for($card)->create();
    ChecklistItem::factory()->for($checklist)->create(['name' => 'UI Design', 'is_checked' => true]);

    $response = $this->actingAs($user)->get("/boards/{$board->id}");

    $response->assertInertia(
        fn ($page) => $page
            ->component('boards/Show')
            ->where('board.lists.0.cards.0.checklists.0.id', $checklist->id)
            ->where('board.lists.0.cards.0.checklists.0.items.0.name', 'UI Design')
            ->where('board.lists.0.cards.0.checklists.0.items.0.is_checked', true)
    );
});
