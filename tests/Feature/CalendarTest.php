<?php

use App\Models\Board;
use App\Models\BoardEvent;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\Checklist;
use App\Models\User;
use App\Models\Workspace;

test('the global calendar includes due cards and events from every board the user belongs to', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create(['name' => 'Engineering']);
    $list = BoardList::factory()->for($board)->create();
    Card::factory()->for($list)->create(['name' => 'Ship it', 'due_date' => '2026-09-05']);
    Card::factory()->for($list)->create(['name' => 'No due date']);
    BoardEvent::factory()->for($board)->for($user)->create(['name' => 'Sprint Planning', 'start_date' => '2026-09-10']);

    $response = $this->actingAs($user)->get('/calendar');

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Calendar')
            ->has('cards', 1)
            ->where('cards.0.name', 'Ship it')
            ->where('cards.0.board_id', $board->id)
            ->where('cards.0.is_completed', false)
            ->has('events', 1)
            ->where('events.0.name', 'Sprint Planning')
            ->where('boards.0.name', 'Engineering')
            ->where('boards.0.workspace_name', $board->workspace->name)
            ->has('boards', 1)
    );
});

test('the global calendar flags a due card as completed once its checklist is 100% done', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create(['name' => 'Ship it', 'due_date' => '2026-09-05']);
    $checklist = Checklist::factory()->for($card)->create();
    $checklist->items()->create(['name' => 'Step', 'is_checked' => true, 'position' => 0]);

    $response = $this->actingAs($user)->get('/calendar');

    $response->assertInertia(fn ($page) => $page->where('cards.0.is_completed', true));
});

test('the global calendar excludes boards the user is not a member of', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    Card::factory()->for($list)->create(['due_date' => '2026-09-05']);

    $otherBoard = Board::factory()->create();
    $otherList = BoardList::factory()->for($otherBoard)->create();
    Card::factory()->for($otherList)->create(['due_date' => '2026-09-06']);
    BoardEvent::factory()->for($otherBoard)->create();

    $response = $this->actingAs($user)->get('/calendar');

    $response->assertInertia(
        fn ($page) => $page
            ->has('cards', 1)
            ->has('events', 0)
            ->has('boards', 1)
    );
});

test('a general event is visible to everyone the creator shares a workspace with', function () {
    $creator = User::factory()->create();
    $workspace = Workspace::factory()->for($creator, 'owner')->create();
    $teammate = User::factory()->create();
    $workspace->members()->attach($teammate->id);
    BoardEvent::factory()->for($creator)->create(['board_id' => null, 'name' => 'CUTI', 'start_date' => '2026-09-01']);

    $response = $this->actingAs($teammate)->get('/calendar');

    $response->assertInertia(
        fn ($page) => $page
            ->has('events', 1)
            ->where('events.0.name', 'CUTI')
    );
});

test('a general event is not visible to a user who shares no workspace with the creator', function () {
    $creator = User::factory()->create();
    Workspace::factory()->for($creator, 'owner')->create();
    $stranger = User::factory()->create();
    Workspace::factory()->for($stranger, 'owner')->create();
    BoardEvent::factory()->for($creator)->create(['board_id' => null, 'name' => 'CUTI', 'start_date' => '2026-09-01']);

    $response = $this->actingAs($stranger)->get('/calendar');

    $response->assertInertia(fn ($page) => $page->has('events', 0));
});

test('a guest cannot view the calendar', function () {
    $response = $this->get('/calendar');

    $response->assertRedirect('/login');
});
