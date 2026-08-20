<?php

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\User;
use App\Models\Workspace;

test('the workspace owner can search for a user to add', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $candidate = User::factory()->create(['name' => 'Jane Searchable']);

    $response = $this->actingAs($owner)->get("/workspaces/{$workspace->id}/members/search?q=Searchable");

    $response->assertOk();
    $response->assertJsonFragment(['id' => $candidate->id]);
});

test('search excludes users who are already members', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $member = User::factory()->create(['name' => 'Already Member']);
    $workspace->members()->attach($member->id);

    $response = $this->actingAs($owner)->get("/workspaces/{$workspace->id}/members/search?q=Already");

    $response->assertJsonMissing(['id' => $member->id]);
});

test('a non-owner cannot search workspace members', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $member = User::factory()->create();
    $workspace->members()->attach($member->id);

    $response = $this->actingAs($member)->get("/workspaces/{$workspace->id}/members/search?q=x");

    $response->assertForbidden();
});

test('the workspace owner can add an existing user as a member', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $candidate = User::factory()->create();

    $response = $this->actingAs($owner)->post("/workspaces/{$workspace->id}/members", ['user_id' => $candidate->id]);

    $response->assertRedirect();
    expect($workspace->members()->where('users.id', $candidate->id)->exists())->toBeTrue();
});

test('a non-owner cannot add a workspace member', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $member = User::factory()->create();
    $workspace->members()->attach($member->id);
    $candidate = User::factory()->create();

    $response = $this->actingAs($member)->post("/workspaces/{$workspace->id}/members", ['user_id' => $candidate->id]);

    $response->assertForbidden();
    expect($workspace->members()->where('users.id', $candidate->id)->exists())->toBeFalse();
});

test('the workspace owner can remove another member', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $member = User::factory()->create();
    $workspace->members()->attach($member->id);

    $response = $this->actingAs($owner)->delete("/workspaces/{$workspace->id}/members/{$member->id}");

    $response->assertRedirect();
    expect($workspace->members()->where('users.id', $member->id)->exists())->toBeFalse();
});

test('a member can remove themselves from the workspace', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $member = User::factory()->create();
    $workspace->members()->attach($member->id);

    $response = $this->actingAs($member)->delete("/workspaces/{$workspace->id}/members/{$member->id}");

    $response->assertRedirect();
    expect($workspace->members()->where('users.id', $member->id)->exists())->toBeFalse();
});

test('a member cannot remove another member', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $memberA = User::factory()->create();
    $memberB = User::factory()->create();
    $workspace->members()->attach([$memberA->id, $memberB->id]);

    $response = $this->actingAs($memberA)->delete("/workspaces/{$workspace->id}/members/{$memberB->id}");

    $response->assertForbidden();
    expect($workspace->members()->where('users.id', $memberB->id)->exists())->toBeTrue();
});

test('removing a workspace member revokes their board and card membership in that workspace', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();

    $member = User::factory()->create();
    $workspace->members()->attach($member->id);
    $board->members()->attach($member->id);
    $card->members()->attach($member->id);

    expect($this->actingAs($member)->get("/boards/{$board->id}")->status())->toBe(200);

    $response = $this->actingAs($owner)->delete("/workspaces/{$workspace->id}/members/{$member->id}");

    $response->assertRedirect();

    $this->assertDatabaseMissing('board_user', ['board_id' => $board->id, 'user_id' => $member->id]);
    $this->assertDatabaseMissing('card_user', ['card_id' => $card->id, 'user_id' => $member->id]);

    $this->actingAs($member)->get("/boards/{$board->id}")->assertForbidden();
});

test('removing a workspace member leaves other users board and card membership intact', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $card->members()->attach($owner->id);

    $member = User::factory()->create();
    $workspace->members()->attach($member->id);
    $board->members()->attach($member->id);

    $this->actingAs($owner)->delete("/workspaces/{$workspace->id}/members/{$member->id}");

    $this->assertDatabaseHas('board_user', ['board_id' => $board->id, 'user_id' => $owner->id]);
    $this->assertDatabaseHas('card_user', ['card_id' => $card->id, 'user_id' => $owner->id]);
});

test('the owner cannot be removed from the workspace, even by themselves', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();

    $response = $this->actingAs($owner)->delete("/workspaces/{$workspace->id}/members/{$owner->id}");

    $response->assertStatus(422);
    expect($workspace->members()->where('users.id', $owner->id)->exists())->toBeTrue();
});
