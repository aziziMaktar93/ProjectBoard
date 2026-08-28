<?php

use App\Models\Board;
use App\Models\User;
use App\Models\Workspace;

test('a board member can favourite a board', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($user)->create();

    $response = $this->actingAs($user)->patch("/boards/{$board->id}/favourite");

    $response->assertRedirect();
    expect((bool) $board->members()->where('users.id', $user->id)->first()->pivot->is_favourite)->toBeTrue();
});

test('favouriting a board twice unfavourites it', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($user)->create();

    $this->actingAs($user)->patch("/boards/{$board->id}/favourite");
    $this->actingAs($user)->patch("/boards/{$board->id}/favourite");

    expect((bool) $board->members()->where('users.id', $user->id)->first()->pivot->is_favourite)->toBeFalse();
});

test('a non-member cannot favourite a board', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create();
    $outsider = User::factory()->create();

    $response = $this->actingAs($outsider)->patch("/boards/{$board->id}/favourite");

    $response->assertForbidden();
});

test('favourited boards are listed first on the workspace page', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();
    Board::factory()->for($workspace)->for($user)->create(['name' => 'Not favourited']);
    $favourite = Board::factory()->for($workspace)->for($user)->create(['name' => 'Favourited']);

    $this->actingAs($user)->patch("/boards/{$favourite->id}/favourite");

    $response = $this->actingAs($user)->get("/workspaces/{$workspace->id}");

    $response->assertInertia(
        fn ($page) => $page
            ->where('boards.data.0.id', $favourite->id)
            ->where('boards.data.0.is_favourite', true)
            ->where('boards.data.1.is_favourite', false)
    );
});
