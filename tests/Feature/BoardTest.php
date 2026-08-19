<?php

use App\Models\Board;
use App\Models\User;

test('index lists only the authenticated user\'s active boards', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $activeBoard = Board::factory()->for($user)->create(['name' => 'Active Board']);
    Board::factory()->for($user)->archived()->create(['name' => 'Archived Board']);
    Board::factory()->for($otherUser)->create(['name' => 'Other User Board']);

    $response = $this->actingAs($user)->get('/boards');

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('boards/Index')
            ->has('boards', 1)
            ->where('boards.0.id', $activeBoard->id)
    );
});

test('archived lists only the authenticated user\'s archived boards', function () {
    $user = User::factory()->create();
    Board::factory()->for($user)->create();
    $archivedBoard = Board::factory()->for($user)->archived()->create();

    $response = $this->actingAs($user)->get('/boards/archived');

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('boards/Archived')
            ->has('boards', 1)
            ->where('boards.0.id', $archivedBoard->id)
    );
});

test('a user can create a board', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/boards', [
        'name' => 'My New Board',
        'background_color' => '#0079BF',
    ]);

    $board = Board::first();

    $response->assertRedirect("/boards/{$board->id}");
    expect($board->user_id)->toBe($user->id);
    expect($board->name)->toBe('My New Board');
});

test('creating a board requires a name', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/boards', ['name' => '']);

    $response->assertSessionHasErrors('name');
});

test('a user can view their own board', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();

    $response = $this->actingAs($user)->get("/boards/{$board->id}");

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('boards/Show')
            ->where('board.id', $board->id)
    );
});

test('a user cannot view another user\'s board', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $other = User::factory()->create();

    $response = $this->actingAs($other)->get("/boards/{$board->id}");

    $response->assertForbidden();
});

test('a user can rename their board', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create(['name' => 'Old name']);

    $response = $this->actingAs($user)->patch("/boards/{$board->id}", [
        'name' => 'New name',
    ]);

    $response->assertRedirect();
    expect($board->fresh()->name)->toBe('New name');
});

test('a user cannot rename another user\'s board', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create(['name' => 'Old name']);
    $other = User::factory()->create();

    $response = $this->actingAs($other)->patch("/boards/{$board->id}", [
        'name' => 'New name',
    ]);

    $response->assertForbidden();
    expect($board->fresh()->name)->toBe('Old name');
});

test('a user can archive and restore their board', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();

    $this->actingAs($user)->patch("/boards/{$board->id}/archive")->assertRedirect('/boards');
    expect($board->fresh()->archived_at)->not->toBeNull();

    $this->actingAs($user)->patch("/boards/{$board->id}/restore")->assertRedirect('/boards/archived');
    expect($board->fresh()->archived_at)->toBeNull();
});

test('a non archived board cannot be permanently deleted', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();

    $response = $this->actingAs($user)->delete("/boards/{$board->id}");

    $response->assertStatus(422);
    expect(Board::find($board->id))->not->toBeNull();
});

test('an archived board can be permanently deleted', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->archived()->create();

    $response = $this->actingAs($user)->delete("/boards/{$board->id}");

    $response->assertRedirect('/boards/archived');
    expect(Board::find($board->id))->toBeNull();
});

test('a user cannot delete another user\'s archived board', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->archived()->create();
    $other = User::factory()->create();

    $response = $this->actingAs($other)->delete("/boards/{$board->id}");

    $response->assertForbidden();
    expect(Board::find($board->id))->not->toBeNull();
});
