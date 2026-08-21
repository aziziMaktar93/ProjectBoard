<?php

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\Label;
use App\Models\User;

test('a board member can attach a label to a card', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $label = Label::factory()->for($board)->create(['name' => 'High Priority']);

    $response = $this->actingAs($owner)->post("/cards/{$card->id}/labels", ['label_id' => $label->id]);

    $response->assertRedirect();
    expect($card->labels()->where('labels.id', $label->id)->exists())->toBeTrue();
});

test('a label from another board cannot be attached to a card', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $otherLabel = Label::factory()->create();

    $response = $this->actingAs($owner)->post("/cards/{$card->id}/labels", ['label_id' => $otherLabel->id]);

    $response->assertSessionHasErrors('label_id');
    expect($card->labels()->where('labels.id', $otherLabel->id)->exists())->toBeFalse();
});

test('a non-board-member cannot attach a label to a card', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $label = Label::factory()->for($board)->create();
    $outsider = User::factory()->create();

    $response = $this->actingAs($outsider)->post("/cards/{$card->id}/labels", ['label_id' => $label->id]);

    $response->assertForbidden();
});

test('a board member can detach a label from a card', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $label = Label::factory()->for($board)->create();
    $card->labels()->attach($label->id);

    $response = $this->actingAs($owner)->delete("/cards/{$card->id}/labels/{$label->id}");

    $response->assertRedirect();
    expect($card->labels()->where('labels.id', $label->id)->exists())->toBeFalse();
});

test('a non-board-member cannot detach a label from a card', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $label = Label::factory()->for($board)->create();
    $card->labels()->attach($label->id);
    $outsider = User::factory()->create();

    $response = $this->actingAs($outsider)->delete("/cards/{$card->id}/labels/{$label->id}");

    $response->assertForbidden();
    expect($card->labels()->where('labels.id', $label->id)->exists())->toBeTrue();
});

test('the board show page includes card labels', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $label = Label::factory()->for($board)->create(['name' => 'Design']);
    $card->labels()->attach($label->id);

    $response = $this->actingAs($owner)->get("/boards/{$board->id}");

    $response->assertInertia(
        fn ($page) => $page
            ->component('boards/Show')
            ->where('board.lists.0.cards.0.labels.0.name', 'Design')
    );
});
