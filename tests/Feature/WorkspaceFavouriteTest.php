<?php

use App\Models\User;
use App\Models\Workspace;

test('a workspace member can favourite a workspace', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();

    $response = $this->actingAs($user)->patch("/workspaces/{$workspace->id}/favourite");

    $response->assertRedirect();
    expect((bool) $workspace->members()->where('users.id', $user->id)->first()->pivot->is_favourite)->toBeTrue();
});

test('favouriting a workspace twice unfavourites it', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();

    $this->actingAs($user)->patch("/workspaces/{$workspace->id}/favourite");
    $this->actingAs($user)->patch("/workspaces/{$workspace->id}/favourite");

    expect((bool) $workspace->members()->where('users.id', $user->id)->first()->pivot->is_favourite)->toBeFalse();
});

test('a non-member cannot favourite a workspace', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $outsider = User::factory()->create();

    $response = $this->actingAs($outsider)->patch("/workspaces/{$workspace->id}/favourite");

    $response->assertForbidden();
});

test('favourited workspaces are listed first on the workspaces index', function () {
    $user = User::factory()->create();
    Workspace::factory()->for($user, 'owner')->create(['name' => 'Not favourited']);
    $favourite = Workspace::factory()->for($user, 'owner')->create(['name' => 'Favourited']);

    $this->actingAs($user)->patch("/workspaces/{$favourite->id}/favourite");

    $response = $this->actingAs($user)->get('/workspaces');

    $response->assertInertia(
        fn ($page) => $page
            ->where('workspaces.data.0.id', $favourite->id)
            ->where('workspaces.data.0.is_favourite', true)
            ->where('workspaces.data.1.is_favourite', false)
    );
});
