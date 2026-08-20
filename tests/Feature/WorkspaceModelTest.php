<?php

use App\Models\Board;
use App\Models\User;
use App\Models\Workspace;

test('a workspace has an owner and members', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $other = User::factory()->create();
    $workspace->members()->attach($other->id);

    expect($workspace->owner->is($owner))->toBeTrue();
    expect($workspace->members)->toHaveCount(2);
    expect($workspace->members->pluck('id'))->toContain($owner->id, $other->id);
});

test('a board belongs to a workspace and gets its creator as a member', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();

    expect($board->workspace)->not->toBeNull();
    expect($board->workspace->owner_id)->toBe($user->id);
    expect($board->members->pluck('id'))->toContain($user->id);
    expect($board->workspace->members->pluck('id'))->toContain($user->id);
});

test('a board created with an explicit workspace does not get a second one', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();

    $board = Board::factory()->for($workspace)->for($owner)->create();

    expect($board->workspace_id)->toBe($workspace->id);
    expect(Workspace::count())->toBe(1);
});

test('registering a new user creates a personal workspace', function () {
    $response = $this->post('/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertRedirect('/dashboard');

    $user = User::where('email', 'jane@example.com')->first();

    expect($user->ownedWorkspaces)->toHaveCount(1);
    expect($user->ownedWorkspaces->first()->name)->toBe("Jane Doe's Workspace");
    expect($user->workspaces)->toHaveCount(1);
});
