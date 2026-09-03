<?php

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\User;
use App\Models\Workspace;

test('a user can create a workspace and becomes its owner and member', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/workspaces', ['name' => 'Acme Team']);

    $workspace = Workspace::first();
    $response->assertRedirect("/workspaces/{$workspace->id}");
    expect($workspace->owner_id)->toBe($user->id);
    expect($workspace->members()->where('users.id', $user->id)->exists())->toBeTrue();
});

test('a user can set a color when creating a workspace', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/workspaces', ['name' => 'Acme Team', 'background_color' => '#0079BF']);

    expect(Workspace::first()->background_color)->toBe('#0079BF');
});

test('creating a workspace requires a name', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/workspaces', ['name' => '']);

    $response->assertSessionHasErrors('name');
});

test('a user can view a workspace they are a member of', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();

    $response = $this->actingAs($owner)->get("/workspaces/{$workspace->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('workspaces/Show')->where('workspace.id', $workspace->id));
});

test('a user cannot view a workspace they are not a member of', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $other = User::factory()->create();

    $response = $this->actingAs($other)->get("/workspaces/{$workspace->id}");

    $response->assertForbidden();
});

test('the workspace owner can rename it', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create(['name' => 'Old']);

    $response = $this->actingAs($owner)->patch("/workspaces/{$workspace->id}", ['name' => 'New']);

    $response->assertRedirect();
    expect($workspace->fresh()->name)->toBe('New');
});

test('the workspace owner can change its color', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();

    $response = $this->actingAs($owner)->patch("/workspaces/{$workspace->id}", ['background_color' => '#61BD4F']);

    $response->assertRedirect();
    expect($workspace->fresh()->background_color)->toBe('#61BD4F');
});

test('a non-owner member cannot rename the workspace', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create(['name' => 'Old']);
    $member = User::factory()->create();
    $workspace->members()->attach($member->id);

    $response = $this->actingAs($member)->patch("/workspaces/{$workspace->id}", ['name' => 'New']);

    $response->assertForbidden();
    expect($workspace->fresh()->name)->toBe('Old');
});

test('the workspace owner can delete it', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();

    $response = $this->actingAs($owner)->delete("/workspaces/{$workspace->id}");

    $response->assertRedirect('/workspaces');
    expect(Workspace::find($workspace->id))->toBeNull();
});

test('a non-owner member cannot delete the workspace', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $member = User::factory()->create();
    $workspace->members()->attach($member->id);

    $response = $this->actingAs($member)->delete("/workspaces/{$workspace->id}");

    $response->assertForbidden();
    expect(Workspace::find($workspace->id))->not->toBeNull();
});

test('a workspace board tile includes card count, members, and checklist progress', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create();
    $member = User::factory()->create();
    $board->members()->attach($member->id);
    $list = BoardList::factory()->for($board)->create();
    $doneCard = Card::factory()->for($list)->create();
    Card::factory()->for($list)->create();
    $checklist = Checklist::factory()->for($doneCard)->create();
    ChecklistItem::factory()->for($checklist)->create(['is_checked' => true]);
    ChecklistItem::factory()->for($checklist)->create(['is_checked' => false]);

    $response = $this->actingAs($owner)->get("/workspaces/{$workspace->id}");

    $response->assertInertia(
        fn ($page) => $page
            ->component('workspaces/Show')
            ->where('boards.data.0.cards_count', 2)
            ->where('boards.data.0.checklist_progress', 50)
            ->has('boards.data.0.members', 2)
    );
});

test('a workspace tile on the index page includes members and checklist progress', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $member = User::factory()->create();
    $workspace->members()->attach($member->id);

    $board = Board::factory()->for($workspace)->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $doneCard = Card::factory()->for($list)->create();
    $checklist = Checklist::factory()->for($doneCard)->create();
    ChecklistItem::factory()->for($checklist)->create(['is_checked' => true]);
    ChecklistItem::factory()->for($checklist)->create(['is_checked' => false]);

    $response = $this->actingAs($owner)->get('/workspaces');

    $response->assertInertia(
        fn ($page) => $page
            ->component('workspaces/Index')
            ->where('workspaces.data.0.checklist_progress', 50)
            ->where('workspaces.data.0.boards_count', 1)
            ->has('workspaces.data.0.members', 2)
    );
});

test('a workspace tile shows null checklist progress when it has no checklist items', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create();
    BoardList::factory()->for($board)->create();

    $response = $this->actingAs($owner)->get('/workspaces');

    $response->assertInertia(
        fn ($page) => $page->where('workspaces.data.0.checklist_progress', null)
    );
});

test('the workspaces index only lists workspaces the user belongs to', function () {
    $user = User::factory()->create();
    $myWorkspace = Workspace::factory()->for($user, 'owner')->create();
    Workspace::factory()->create();

    $response = $this->actingAs($user)->get('/workspaces');

    $response->assertInertia(
        fn ($page) => $page->component('workspaces/Index')->has('workspaces.data', 1)->where('workspaces.data.0.id', $myWorkspace->id)
    );
});

test('workspaces can be searched by name, case-insensitively', function () {
    $user = User::factory()->create();
    $marketing = Workspace::factory()->for($user, 'owner')->create(['name' => 'Marketing Team']);
    Workspace::factory()->for($user, 'owner')->create(['name' => 'Engineering']);

    $response = $this->actingAs($user)->get('/workspaces?search=marketing');

    $response->assertInertia(
        fn ($page) => $page
            ->has('workspaces.data', 1)
            ->where('workspaces.data.0.id', $marketing->id)
            ->where('filters.search', 'marketing')
    );
});

test('workspaces are paginated', function () {
    $user = User::factory()->create();
    Workspace::factory()->for($user, 'owner')->count(13)->create();

    $response = $this->actingAs($user)->get('/workspaces');

    $response->assertInertia(
        fn ($page) => $page
            ->has('workspaces.data', 12)
            ->where('workspaces.last_page', 2)
            ->where('workspaces.total', 13)
    );
});
