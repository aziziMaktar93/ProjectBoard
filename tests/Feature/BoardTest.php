<?php

use App\Models\Board;
use App\Models\BoardEvent;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\Checklist;
use App\Models\User;
use App\Models\Workspace;

test('a workspace shows only its own active boards', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();
    $activeBoard = Board::factory()->for($workspace)->for($user)->create(['name' => 'Active Board']);
    Board::factory()->for($workspace)->for($user)->archived()->create(['name' => 'Archived Board']);
    Board::factory()->for($user)->create(['name' => 'Different Workspace Board']);

    $response = $this->actingAs($user)->get("/workspaces/{$workspace->id}");

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('workspaces/Show')
            ->has('boards.data', 1)
            ->where('boards.data.0.id', $activeBoard->id)
    );
});

test('workspace boards can be searched by name, case-insensitively', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();
    $sprintBoard = Board::factory()->for($workspace)->for($user)->create(['name' => 'Sprint Planning']);
    Board::factory()->for($workspace)->for($user)->create(['name' => 'Marketing Calendar']);

    $response = $this->actingAs($user)->get("/workspaces/{$workspace->id}?search=sprint");

    $response->assertInertia(
        fn ($page) => $page
            ->has('boards.data', 1)
            ->where('boards.data.0.id', $sprintBoard->id)
            ->where('filters.search', 'sprint')
    );
});

test('workspace boards are paginated', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();
    Board::factory()->for($workspace)->for($user)->count(13)->create();

    $response = $this->actingAs($user)->get("/workspaces/{$workspace->id}");

    $response->assertInertia(
        fn ($page) => $page
            ->has('boards.data', 12)
            ->where('boards.last_page', 2)
            ->where('boards.total', 13)
    );
});

test('archived lists only the workspace\'s archived boards', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();
    Board::factory()->for($workspace)->for($user)->create();
    $archivedBoard = Board::factory()->for($workspace)->for($user)->archived()->create();

    $response = $this->actingAs($user)->get("/workspaces/{$workspace->id}/boards/archived");

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('boards/Archived')
            ->has('boards', 1)
            ->where('boards.0.id', $archivedBoard->id)
    );
});

test('a workspace member can create a board in the workspace', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();

    $response = $this->actingAs($user)->post("/workspaces/{$workspace->id}/boards", [
        'name' => 'My New Board',
        'background_color' => '#0079BF',
    ]);

    $board = Board::where('name', 'My New Board')->first();

    $response->assertRedirect("/boards/{$board->id}");
    expect($board->workspace_id)->toBe($workspace->id);
    expect($board->user_id)->toBe($user->id);
    expect($board->members()->where('users.id', $user->id)->exists())->toBeTrue();
});

test('creating a board requires a name', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();

    $response = $this->actingAs($user)->post("/workspaces/{$workspace->id}/boards", ['name' => '']);

    $response->assertSessionHasErrors('name');
});

test('a user who is not a workspace member cannot create a board in it', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $other = User::factory()->create();

    $response = $this->actingAs($other)->post("/workspaces/{$workspace->id}/boards", ['name' => 'Sneaky Board']);

    $response->assertForbidden();
});

test('a board member can view the board', function () {
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

test('a user who is not a board member cannot view the board', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $other = User::factory()->create();

    $response = $this->actingAs($other)->get("/boards/{$board->id}");

    $response->assertForbidden();
});

test('a workspace member who is not a board member cannot view the board', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create();
    $workspaceOnlyMember = User::factory()->create();
    $workspace->members()->attach($workspaceOnlyMember->id);

    $response = $this->actingAs($workspaceOnlyMember)->get("/boards/{$board->id}");

    $response->assertForbidden();
});

test('an added board member can view and update the board', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create(['name' => 'Old name']);
    $member = User::factory()->create();
    $workspace->members()->attach($member->id);
    $board->members()->attach($member->id);

    $this->actingAs($member)->get("/boards/{$board->id}")->assertOk();

    $response = $this->actingAs($member)->patch("/boards/{$board->id}", ['name' => 'New name']);
    $response->assertRedirect();
    expect($board->fresh()->name)->toBe('New name');
});

test('a user can rename their board', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create(['name' => 'Old name']);

    $response = $this->actingAs($user)->patch("/boards/{$board->id}", ['name' => 'New name']);

    $response->assertRedirect();
    expect($board->fresh()->name)->toBe('New name');
});

test('a non-member cannot rename the board', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create(['name' => 'Old name']);
    $other = User::factory()->create();

    $response = $this->actingAs($other)->patch("/boards/{$board->id}", ['name' => 'New name']);

    $response->assertForbidden();
    expect($board->fresh()->name)->toBe('Old name');
});

test('a user can archive and restore their board', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();

    $this->actingAs($user)->patch("/boards/{$board->id}/archive")->assertRedirect("/workspaces/{$board->workspace_id}");
    expect($board->fresh()->archived_at)->not->toBeNull();

    $this->actingAs($user)->patch("/boards/{$board->id}/restore")->assertRedirect("/workspaces/{$board->workspace_id}/boards/archived");
    expect($board->fresh()->archived_at)->toBeNull();
});

test('a non archived board cannot be permanently deleted', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();

    $response = $this->actingAs($user)->delete("/boards/{$board->id}");

    $response->assertStatus(422);
    expect(Board::find($board->id))->not->toBeNull();
});

test('an archived board can be permanently deleted by its creator', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->archived()->create();

    $response = $this->actingAs($user)->delete("/boards/{$board->id}");

    $response->assertRedirect("/workspaces/{$board->workspace_id}/boards/archived");
    expect(Board::find($board->id))->toBeNull();
});

test('a board member who is not the creator cannot permanently delete the board', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->archived()->create();
    $member = User::factory()->create();
    $workspace->members()->attach($member->id);
    $board->members()->attach($member->id);

    $response = $this->actingAs($member)->delete("/boards/{$board->id}");

    $response->assertForbidden();
    expect(Board::find($board->id))->not->toBeNull();
});

test('a user cannot delete another user\'s archived board', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->archived()->create();
    $other = User::factory()->create();

    $response = $this->actingAs($other)->delete("/boards/{$board->id}");

    $response->assertForbidden();
    expect(Board::find($board->id))->not->toBeNull();
});

test('a board member can view the calendar with due cards and events', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    Card::factory()->for($list)->create(['name' => 'Ship it', 'due_date' => '2026-09-05']);
    Card::factory()->for($list)->create(['name' => 'No due date']);
    BoardEvent::factory()->for($board)->for($owner)->create(['name' => 'Sprint Planning', 'start_date' => '2026-09-10']);

    $response = $this->actingAs($owner)->get("/boards/{$board->id}/calendar");

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('boards/Calendar')
            ->has('cards', 1)
            ->where('cards.0.name', 'Ship it')
            ->where('cards.0.is_completed', false)
            ->has('events', 1)
            ->where('events.0.name', 'Sprint Planning')
    );
});

test('a board calendar flags a due card as completed once its checklist is 100% done', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create(['name' => 'Ship it', 'due_date' => '2026-09-05']);
    $checklist = Checklist::factory()->for($card)->create();
    $checklist->items()->create(['name' => 'Step', 'is_checked' => true, 'position' => 0]);

    $response = $this->actingAs($owner)->get("/boards/{$board->id}/calendar");

    $response->assertInertia(fn ($page) => $page->where('cards.0.is_completed', true));
});

test('a board calendar includes checklist item due dates as their own entries', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create(['name' => 'Ship it']);
    $checklist = Checklist::factory()->for($card)->create(['name' => 'Launch Checklist']);
    $checklist->items()->create(['name' => 'Write tests', 'is_checked' => false, 'due_date' => '2026-09-12', 'position' => 0]);
    $checklist->items()->create(['name' => 'No due date', 'is_checked' => false, 'position' => 1]);

    $response = $this->actingAs($owner)->get("/boards/{$board->id}/calendar");

    $response->assertInertia(
        fn ($page) => $page
            ->component('boards/Calendar')
            ->has('checklistItems', 1)
            ->where('checklistItems.0.name', 'Write tests')
            ->where('checklistItems.0.card_id', $card->id)
            ->where('checklistItems.0.card_name', 'Ship it')
            ->where('checklistItems.0.checklist_name', 'Launch Checklist')
            ->where('checklistItems.0.is_checked', false)
    );
});

test('a user who is not a board member cannot view the calendar', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $other = User::factory()->create();

    $response = $this->actingAs($other)->get("/boards/{$board->id}/calendar");

    $response->assertForbidden();
});

test('the board show page passes through a card query param to auto-open it', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();

    $response = $this->actingAs($owner)->get("/boards/{$board->id}?card={$card->id}");

    $response->assertInertia(fn ($page) => $page->where('initialCardId', $card->id));
});

test('aiEnabled is true only when a gemini api key is configured', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($user)->create();

    config(['services.gemini.key' => null]);
    $this->actingAs($user)->get("/boards/{$board->id}")
        ->assertInertia(fn ($page) => $page->where('aiEnabled', false));

    config(['services.gemini.key' => 'fake-key']);
    $this->actingAs($user)->get("/boards/{$board->id}")
        ->assertInertia(fn ($page) => $page->where('aiEnabled', true));
});
