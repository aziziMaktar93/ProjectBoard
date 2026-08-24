<?php

use App\Models\Board;
use App\Models\BoardEvent;
use App\Models\User;

test('a board member can create an event', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();

    $response = $this->actingAs($owner)->post("/boards/{$board->id}/events", [
        'name' => 'Sprint Planning',
        'start_date' => '2026-09-01',
        'color' => '#579dff',
    ]);

    $response->assertRedirect();
    $event = $board->events()->where('name', 'Sprint Planning')->first();
    expect($event)->not->toBeNull();
    expect($event->start_date)->toBe('2026-09-01');
    expect($event->user_id)->toBe($owner->id);
});

test('a board member can create a multi-day event', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();

    $response = $this->actingAs($owner)->post("/boards/{$board->id}/events", [
        'name' => 'Feature Freeze Period',
        'start_date' => '2026-09-10',
        'end_date' => '2026-09-12',
    ]);

    $response->assertRedirect();
    $event = $board->events()->where('name', 'Feature Freeze Period')->first();
    expect($event->end_date)->toBe('2026-09-12');
});

test('an event end date cannot be before its start date', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();

    $response = $this->actingAs($owner)->post("/boards/{$board->id}/events", [
        'name' => 'Invalid',
        'start_date' => '2026-09-10',
        'end_date' => '2026-09-01',
    ]);

    $response->assertSessionHasErrors('end_date');
    expect($board->events()->count())->toBe(0);
});

test('a non-board-member cannot create an event', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $outsider = User::factory()->create();

    $response = $this->actingAs($outsider)->post("/boards/{$board->id}/events", [
        'name' => 'Sprint Planning',
        'start_date' => '2026-09-01',
    ]);

    $response->assertForbidden();
    expect($board->events()->count())->toBe(0);
});

test('a board member can update an event', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $event = BoardEvent::factory()->for($board)->for($owner)->create(['name' => 'Old Name']);

    $response = $this->actingAs($owner)->patch("/events/{$event->id}", [
        'name' => 'New Name',
        'start_date' => '2026-10-01',
    ]);

    $response->assertRedirect();
    expect($event->fresh()->name)->toBe('New Name');
    expect($event->fresh()->start_date)->toBe('2026-10-01');
});

test('a non-board-member cannot update an event', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $event = BoardEvent::factory()->for($board)->for($owner)->create(['name' => 'Old Name']);
    $outsider = User::factory()->create();

    $response = $this->actingAs($outsider)->patch("/events/{$event->id}", [
        'name' => 'New Name',
        'start_date' => '2026-10-01',
    ]);

    $response->assertForbidden();
    expect($event->fresh()->name)->toBe('Old Name');
});

test('a board member can delete an event', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $event = BoardEvent::factory()->for($board)->for($owner)->create();

    $response = $this->actingAs($owner)->delete("/events/{$event->id}");

    $response->assertRedirect();
    expect(BoardEvent::find($event->id))->toBeNull();
});

test('a non-board-member cannot delete an event', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $event = BoardEvent::factory()->for($board)->for($owner)->create();
    $outsider = User::factory()->create();

    $response = $this->actingAs($outsider)->delete("/events/{$event->id}");

    $response->assertForbidden();
    expect(BoardEvent::find($event->id))->not->toBeNull();
});
