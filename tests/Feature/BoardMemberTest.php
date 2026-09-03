<?php

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
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

test('removing a board member clears their card assignments on that board', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();

    $member = User::factory()->create();
    $workspace->members()->attach($member->id);
    $board->members()->attach($member->id);
    $card->members()->attach($member->id);

    $response = $this->actingAs($owner)->delete("/boards/{$board->id}/members/{$member->id}");

    $response->assertRedirect();
    $this->assertDatabaseMissing('card_user', ['card_id' => $card->id, 'user_id' => $member->id]);
    expect($board->members()->where('users.id', $member->id)->exists())->toBeFalse();
});

test('removing a board member does not touch their card assignments on other boards', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create();
    $otherBoard = Board::factory()->for($workspace)->for($owner)->create();
    $otherCard = Card::factory()->for(BoardList::factory()->for($otherBoard))->create();

    $member = User::factory()->create();
    $workspace->members()->attach($member->id);
    $board->members()->attach($member->id);
    $otherBoard->members()->attach($member->id);
    $otherCard->members()->attach($member->id);

    $this->actingAs($owner)->delete("/boards/{$board->id}/members/{$member->id}");

    $this->assertDatabaseHas('card_user', ['card_id' => $otherCard->id, 'user_id' => $member->id]);
});

test('a non-board-member cannot remove a board member', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create();
    $member = User::factory()->create();
    $workspace->members()->attach($member->id);
    $board->members()->attach($member->id);
    $outsider = User::factory()->create();

    $response = $this->actingAs($outsider)->delete("/boards/{$board->id}/members/{$member->id}");

    $response->assertForbidden();
    expect($board->members()->where('users.id', $member->id)->exists())->toBeTrue();
});

test('a board member is added as an editor by default', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create();
    $candidate = User::factory()->create();
    $workspace->members()->attach($candidate->id);

    $this->actingAs($owner)->post("/boards/{$board->id}/members", ['user_id' => $candidate->id]);

    expect($board->members()->where('users.id', $candidate->id)->first()->pivot->role)->toBe('editor');
});

test('a board member can be added as a viewer', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create();
    $candidate = User::factory()->create();
    $workspace->members()->attach($candidate->id);

    $this->actingAs($owner)->post("/boards/{$board->id}/members", ['user_id' => $candidate->id, 'role' => 'viewer']);

    expect($board->members()->where('users.id', $candidate->id)->first()->pivot->role)->toBe('viewer');
});

test('an editor can change another member\'s role', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create();
    $member = User::factory()->create();
    $workspace->members()->attach($member->id);
    $board->members()->attach($member->id, ['role' => 'editor']);

    $response = $this->actingAs($owner)->patch("/boards/{$board->id}/members/{$member->id}/role", ['role' => 'viewer']);

    $response->assertRedirect();
    expect($board->members()->where('users.id', $member->id)->first()->pivot->role)->toBe('viewer');
});

test('a viewer cannot change another member\'s role', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create();
    $viewer = User::factory()->create();
    $workspace->members()->attach($viewer->id);
    $board->members()->attach($viewer->id, ['role' => 'viewer']);
    $other = User::factory()->create();
    $workspace->members()->attach($other->id);
    $board->members()->attach($other->id, ['role' => 'editor']);

    $response = $this->actingAs($viewer)->patch("/boards/{$board->id}/members/{$other->id}/role", ['role' => 'viewer']);

    $response->assertForbidden();
    expect($board->members()->where('users.id', $other->id)->first()->pivot->role)->toBe('editor');
});

test('the board owner can assign the hod role', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create();
    $member = User::factory()->create();
    $workspace->members()->attach($member->id);
    $board->members()->attach($member->id, ['role' => 'editor']);

    $response = $this->actingAs($owner)->patch("/boards/{$board->id}/members/{$member->id}/role", ['role' => 'hod']);

    $response->assertRedirect();
    expect($board->members()->where('users.id', $member->id)->first()->pivot->role)->toBe('hod');
});

test('a non-owner editor cannot assign the hod role', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create();
    $editor = User::factory()->create();
    $workspace->members()->attach($editor->id);
    $board->members()->attach($editor->id, ['role' => 'editor']);
    $other = User::factory()->create();
    $workspace->members()->attach($other->id);
    $board->members()->attach($other->id, ['role' => 'editor']);

    $response = $this->actingAs($editor)->patch("/boards/{$board->id}/members/{$other->id}/role", ['role' => 'hod']);

    $response->assertForbidden();
    expect($board->members()->where('users.id', $other->id)->first()->pivot->role)->toBe('editor');
});

test('the board creator\'s role cannot be changed', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();

    $response = $this->actingAs($owner)->patch("/boards/{$board->id}/members/{$owner->id}/role", ['role' => 'viewer']);

    $response->assertStatus(422);
});

test('a viewer cannot update the board', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create(['name' => 'Original']);
    $viewer = User::factory()->create();
    $workspace->members()->attach($viewer->id);
    $board->members()->attach($viewer->id, ['role' => 'viewer']);

    $response = $this->actingAs($viewer)->patch("/boards/{$board->id}", ['name' => 'Renamed']);

    $response->assertForbidden();
    expect($board->fresh()->name)->toBe('Original');
});

test('a viewer cannot add a list to the board', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create();
    $viewer = User::factory()->create();
    $workspace->members()->attach($viewer->id);
    $board->members()->attach($viewer->id, ['role' => 'viewer']);

    $response = $this->actingAs($viewer)->post("/boards/{$board->id}/lists", ['name' => 'New list']);

    $response->assertForbidden();
    expect($board->lists()->count())->toBe(0);
});

test('a viewer can still view the board', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create();
    $viewer = User::factory()->create();
    $workspace->members()->attach($viewer->id);
    $board->members()->attach($viewer->id, ['role' => 'viewer']);

    $response = $this->actingAs($viewer)->get("/boards/{$board->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->where('canEdit', false));
});

test('the board show page reports canEdit true for an editor', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create();
    $editor = User::factory()->create();
    $workspace->members()->attach($editor->id);
    $board->members()->attach($editor->id, ['role' => 'editor']);

    $response = $this->actingAs($editor)->get("/boards/{$board->id}");

    $response->assertInertia(fn ($page) => $page->where('canEdit', true));
});

test('a viewer can still remove themselves (leave)', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create();
    $viewer = User::factory()->create();
    $workspace->members()->attach($viewer->id);
    $board->members()->attach($viewer->id, ['role' => 'viewer']);

    $response = $this->actingAs($viewer)->delete("/boards/{$board->id}/members/{$viewer->id}");

    $response->assertRedirect();
    expect($board->members()->where('users.id', $viewer->id)->exists())->toBeFalse();
});

test('the board creator cannot be removed', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();

    $response = $this->actingAs($owner)->delete("/boards/{$board->id}/members/{$owner->id}");

    $response->assertStatus(422);
    expect($board->members()->where('users.id', $owner->id)->exists())->toBeTrue();
});
