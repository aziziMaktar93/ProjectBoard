<?php

use App\Models\Board;
use App\Models\Label;
use App\Models\User;

test('a board member can create a label', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();

    $response = $this->actingAs($owner)->post("/boards/{$board->id}/labels", [
        'name' => 'High Priority',
        'color' => '#f87168',
    ]);

    $response->assertRedirect();
    $label = $board->labels()->where('name', 'High Priority')->first();
    expect($label)->not->toBeNull();
    expect($label->color)->toBe('#f87168');
});

test('a non-board-member cannot create a label', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $outsider = User::factory()->create();

    $response = $this->actingAs($outsider)->post("/boards/{$board->id}/labels", [
        'name' => 'High Priority',
        'color' => '#f87168',
    ]);

    $response->assertForbidden();
    expect($board->labels()->count())->toBe(0);
});

test('a board member can rename and recolor a label', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $label = Label::factory()->for($board)->create(['name' => 'Design', 'color' => '#579dff']);

    $response = $this->actingAs($owner)->patch("/labels/{$label->id}", [
        'name' => 'Frontend',
        'color' => '#9f8fef',
    ]);

    $response->assertRedirect();
    expect($label->fresh()->name)->toBe('Frontend');
    expect($label->fresh()->color)->toBe('#9f8fef');
});

test('a non-board-member cannot update a label', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $label = Label::factory()->for($board)->create(['name' => 'Design']);
    $outsider = User::factory()->create();

    $response = $this->actingAs($outsider)->patch("/labels/{$label->id}", ['name' => 'Renamed']);

    $response->assertForbidden();
    expect($label->fresh()->name)->toBe('Design');
});

test('a board member can delete a label', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $label = Label::factory()->for($board)->create();

    $response = $this->actingAs($owner)->delete("/labels/{$label->id}");

    $response->assertRedirect();
    expect(Label::find($label->id))->toBeNull();
});

test('a non-board-member cannot delete a label', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $label = Label::factory()->for($board)->create();
    $outsider = User::factory()->create();

    $response = $this->actingAs($outsider)->delete("/labels/{$label->id}");

    $response->assertForbidden();
    expect(Label::find($label->id))->not->toBeNull();
});

test('the board show page includes its labels', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    Label::factory()->for($board)->create(['name' => 'Design']);

    $response = $this->actingAs($owner)->get("/boards/{$board->id}");

    $response->assertInertia(
        fn ($page) => $page
            ->component('boards/Show')
            ->where('board.labels.0.name', 'Design')
    );
});
