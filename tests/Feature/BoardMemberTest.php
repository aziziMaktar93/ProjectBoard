<?php

use App\Models\Board;
use App\Models\User;
use App\Models\Workspace;

test('a board member can add another workspace member to the board', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create();
    $candidate = User::factory()->create();
    $workspace->members()->attach($candidate->id);

    $response = $this->actingAs($owner)->post("/boards/{$board->id}/members", ['user_id' => $candidate->id]);

    $response->assertRedirect();
    expect($board->members()->where('users.id', $candidate->id)->exists())->toBeTrue();
});

test('a user who is not yet a workspace member cannot be added to the board', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create();
    $outsider = User::factory()->create();

    $response = $this->actingAs($owner)->post("/boards/{$board->id}/members", ['user_id' => $outsider->id]);

    $response->assertSessionHasErrors('user_id');
    expect($board->members()->where('users.id', $outsider->id)->exists())->toBeFalse();
});

test('a non-board-member cannot add a board member', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create();
    $candidate = User::factory()->create();
    $workspace->members()->attach($candidate->id);
    $outsider = User::factory()->create();

    $response = $this->actingAs($outsider)->post("/boards/{$board->id}/members", ['user_id' => $candidate->id]);

    $response->assertForbidden();
});

test('a board member can remove another member', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create();
    $member = User::factory()->create();
    $workspace->members()->attach($member->id);
    $board->members()->attach($member->id);

    $response = $this->actingAs($owner)->delete("/boards/{$board->id}/members/{$member->id}");

    $response->assertRedirect();
    expect($board->members()->where('users.id', $member->id)->exists())->toBeFalse();
});

test('a board member can remove themselves (leave)', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create();
    $member = User::factory()->create();
    $workspace->members()->attach($member->id);
    $board->members()->attach($member->id);

    $response = $this->actingAs($member)->delete("/boards/{$board->id}/members/{$member->id}");

    $response->assertRedirect();
    expect($board->members()->where('users.id', $member->id)->exists())->toBeFalse();
});

test('the board creator cannot be removed', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();

    $response = $this->actingAs($owner)->delete("/boards/{$board->id}/members/{$owner->id}");

    $response->assertStatus(422);
    expect($board->members()->where('users.id', $owner->id)->exists())->toBeTrue();
});
