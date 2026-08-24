<?php

use App\Models\User;
use App\Models\Workspace;

test('the members page lists everyone the user shares a workspace with', function () {
    $user = User::factory()->create(['name' => 'Zack Owner']);
    $teammate = User::factory()->create(['name' => 'Dana Malik']);
    $workspace = Workspace::factory()->for($user, 'owner')->create(['name' => 'Marketing']);
    $workspace->members()->attach($teammate->id);

    $response = $this->actingAs($user)->get('/members');

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Members')
            ->has('members', 2)
            ->where('members.0.name', 'Dana Malik')
            ->where('members.0.workspaces.0.name', 'Marketing')
    );
});

test('the members page excludes people from workspaces the user does not belong to', function () {
    $user = User::factory()->create();
    Workspace::factory()->for($user, 'owner')->create();

    $strangerWorkspace = Workspace::factory()->create();
    $stranger = User::factory()->create();
    $strangerWorkspace->members()->attach($stranger->id);

    $response = $this->actingAs($user)->get('/members');

    $response->assertInertia(fn ($page) => $page->has('members', 1)->where('members.0.id', $user->id));
});

test('a member shared across two workspaces is listed once with both workspaces', function () {
    $user = User::factory()->create();
    $teammate = User::factory()->create();
    $workspaceA = Workspace::factory()->for($user, 'owner')->create(['name' => 'Alpha']);
    $workspaceB = Workspace::factory()->for($user, 'owner')->create(['name' => 'Beta']);
    $workspaceA->members()->attach($teammate->id);
    $workspaceB->members()->attach($teammate->id);

    $response = $this->actingAs($user)->get('/members');

    $response->assertInertia(
        fn ($page) => $page
            ->has('members', 2)
            ->has('members.0.workspaces', 2)
            ->has('members.1.workspaces', 2)
    );
});

test('the members page includes the workspaces the user belongs to for filtering', function () {
    $user = User::factory()->create();
    Workspace::factory()->for($user, 'owner')->create(['name' => 'Alpha']);
    Workspace::factory()->for($user, 'owner')->create(['name' => 'Beta']);

    $response = $this->actingAs($user)->get('/members');

    $response->assertInertia(
        fn ($page) => $page
            ->has('workspaces', 2)
            ->where('workspaces.0.name', 'Alpha')
            ->where('workspaces.1.name', 'Beta')
    );
});

test('a guest cannot view the members page', function () {
    $response = $this->get('/members');

    $response->assertRedirect('/login');
});
