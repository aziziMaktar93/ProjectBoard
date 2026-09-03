<?php

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\User;
use App\Models\Workspace;
use Barryvdh\Snappy\Facades\SnappyPdf;

test('a guest cannot access any reports route', function () {
    $this->get('/reports')->assertRedirect('/login');
    $this->get('/reports/on-time-completion')->assertRedirect('/login');
});

test('the on-time-completion report separates on-time from late items', function () {
    SnappyPdf::fake();

    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create(['name' => 'Engineering']);
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $checklist = Checklist::factory()->for($card)->create(['name' => 'Launch Checklist']);

    ChecklistItem::factory()->for($checklist)->create([
        'name' => 'On time item',
        'due_date' => '2026-09-05',
        'completed_at' => '2026-09-04 10:00:00',
        'is_checked' => true,
    ]);

    ChecklistItem::factory()->for($checklist)->create([
        'name' => 'Late item',
        'due_date' => '2026-09-01',
        'completed_at' => '2026-09-05 10:00:00',
        'is_checked' => true,
    ]);

    ChecklistItem::factory()->for($checklist)->create([
        'name' => 'Never completed',
        'due_date' => '2026-09-01',
        'completed_at' => null,
        'is_checked' => false,
    ]);

    $response = $this->actingAs($user)->get('/reports/on-time-completion');

    $response->assertOk();
    SnappyPdf::assertViewIs('reports.on-time-completion');
    SnappyPdf::assertViewHas('onTimeCount', 1);
    SnappyPdf::assertViewHas('lateCount', 1);
    SnappyPdf::assertViewHas('totalCompleted', 2);
    SnappyPdf::assertSee('Late item');
    SnappyPdf::assertSee('On time item');
    SnappyPdf::assertDontSee('Never completed');
});

test('the on-time-completion report respects the board filter', function () {
    SnappyPdf::fake();

    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create(['name' => 'Engineering']);
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $checklist = Checklist::factory()->for($card)->create();
    ChecklistItem::factory()->for($checklist)->create([
        'name' => 'Engineering late item',
        'due_date' => '2026-09-01',
        'completed_at' => '2026-09-05 10:00:00',
        'is_checked' => true,
    ]);

    $otherBoard = Board::factory()->for($user)->create(['workspace_id' => $board->workspace_id, 'name' => 'Marketing']);
    $otherList = BoardList::factory()->for($otherBoard)->create();
    $otherCard = Card::factory()->for($otherList)->create();
    $otherChecklist = Checklist::factory()->for($otherCard)->create();
    ChecklistItem::factory()->for($otherChecklist)->create([
        'name' => 'Marketing late item',
        'due_date' => '2026-09-01',
        'completed_at' => '2026-09-05 10:00:00',
        'is_checked' => true,
    ]);

    $response = $this->actingAs($user)->get("/reports/on-time-completion?board_id={$board->id}");

    $response->assertOk();
    SnappyPdf::assertSee('Engineering late item');
    SnappyPdf::assertDontSee('Marketing late item');
});

test('the on-time-completion report excludes items on archived cards', function () {
    SnappyPdf::fake();

    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create(['name' => 'Engineering']);
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->archived()->create();
    $checklist = Checklist::factory()->for($card)->create();

    ChecklistItem::factory()->for($checklist)->create([
        'name' => 'Archived card item',
        'due_date' => '2026-09-01',
        'completed_at' => '2026-09-05 10:00:00',
        'is_checked' => true,
    ]);

    $response = $this->actingAs($user)->get('/reports/on-time-completion');

    $response->assertOk();
    SnappyPdf::assertViewHas('totalCompleted', 0);
    SnappyPdf::assertDontSee('Archived card item');
});

test('the member-performance report ranks members by completed count', function () {
    SnappyPdf::fake();

    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();

    $topPerformer = User::factory()->create(['name' => 'Priya']);
    $board->workspace->members()->attach($topPerformer->id);
    $board->members()->attach($topPerformer->id);

    $card1 = Card::factory()->for($list)->create();
    $checklist1 = Checklist::factory()->for($card1)->create();
    $item1 = ChecklistItem::factory()->for($checklist1)->create(['is_checked' => true]);
    $item1->members()->attach($topPerformer->id);

    $card2 = Card::factory()->for($list)->create();
    $checklist2 = Checklist::factory()->for($card2)->create();
    $item2 = ChecklistItem::factory()->for($checklist2)->create(['is_checked' => true]);
    $item2->members()->attach($topPerformer->id);

    $slower = User::factory()->create(['name' => 'Sam']);
    $board->workspace->members()->attach($slower->id);
    $board->members()->attach($slower->id);
    $card3 = Card::factory()->for($list)->create();
    $checklist3 = Checklist::factory()->for($card3)->create();
    $item3 = ChecklistItem::factory()->for($checklist3)->create(['is_checked' => false, 'due_date' => '2020-01-01']);
    $item3->members()->attach($slower->id);

    $response = $this->actingAs($owner)->get('/reports/member-performance');

    $response->assertOk();
    SnappyPdf::assertViewIs('reports.member-performance');
    SnappyPdf::assertViewHas('rows', function ($rows) use ($topPerformer, $slower) {
        $first = $rows->first();

        return $first['user']->id === $topPerformer->id
            && $first['completed'] === 2
            && $rows->firstWhere(fn ($row) => $row['user']->id === $slower->id)['overdue'] === 1;
    });
});

test('the member-performance report counts pending tasks separately from overdue and completed', function () {
    SnappyPdf::fake();

    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $member = User::factory()->create(['name' => 'Priya']);
    $board->workspace->members()->attach($member->id);
    $board->members()->attach($member->id);

    $card = Card::factory()->for($list)->create();
    $checklist = Checklist::factory()->for($card)->create();
    $item = ChecklistItem::factory()->for($checklist)->create([
        'is_checked' => false,
        'due_date' => now()->addDays(5)->toDateString(),
    ]);
    $item->members()->attach($member->id);

    $response = $this->actingAs($owner)->get('/reports/member-performance');

    $response->assertOk();
    SnappyPdf::assertViewHas('rows', function ($rows) use ($member) {
        $row = $rows->firstWhere(fn ($row) => $row['user']->id === $member->id);

        return $row['pending'] === 1 && $row['completed'] === 0 && $row['overdue'] === 0;
    });
});

test('the member-performance report computes average days late', function () {
    SnappyPdf::fake();

    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $member = User::factory()->create(['name' => 'Priya']);
    $board->workspace->members()->attach($member->id);
    $board->members()->attach($member->id);

    $card = Card::factory()->for($list)->create();
    $checklist = Checklist::factory()->for($card)->create();
    $item = ChecklistItem::factory()->for($checklist)->create([
        'is_checked' => true,
        'due_date' => '2026-09-01',
        'completed_at' => '2026-09-05 10:00:00',
    ]);
    $item->members()->attach($member->id);

    $response = $this->actingAs($owner)->get('/reports/member-performance');

    $response->assertOk();
    SnappyPdf::assertViewHas('rows', fn ($rows) => $rows->first()['avg_days_late'] === 4.0);
});

test('the member-performance report lists each members assigned tasks with details', function () {
    SnappyPdf::fake();

    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create(['name' => 'Team Alpha']);
    $board = Board::factory()->for($workspace)->for($owner)->create(['name' => 'Engineering']);
    $list = BoardList::factory()->for($board)->create();

    $member = User::factory()->create(['name' => 'Priya']);
    $workspace->members()->attach($member->id);
    $board->members()->attach($member->id);

    $card = Card::factory()->for($list)->create(['name' => 'Ship feature']);
    $card->members()->attach($member->id);

    $checklist = Checklist::factory()->for($card)->create();
    $item = ChecklistItem::factory()->for($checklist)->create([
        'name' => 'Write tests',
        'due_date' => '2026-09-01',
        'completed_at' => '2026-09-05 10:00:00',
        'is_checked' => true,
    ]);
    $item->members()->attach($member->id);

    $response = $this->actingAs($owner)->get('/reports/member-performance');

    $response->assertOk();
    SnappyPdf::assertViewHas('rows', function ($rows) use ($member) {
        $row = $rows->firstWhere(fn ($row) => $row['user']->id === $member->id);

        return $row['tasks']->count() === 2
            && $row['tasks']->firstWhere('type', 'Card')['name'] === 'Ship feature'
            && $row['tasks']->firstWhere('type', 'Checklist Item')['name'] === 'Write tests';
    });
    SnappyPdf::assertSee('Ship feature');
    SnappyPdf::assertSee('Write tests');
    SnappyPdf::assertSee('Team Alpha');
});

test('the activity-log report lists activities newest first', function () {
    SnappyPdf::fake();

    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create(['name' => 'Engineering']);
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create(['name' => 'Fix bug']);
    $card->activities()->create(['user_id' => $user->id, 'type' => 'archived']);
    $card->activities()->create(['user_id' => $user->id, 'type' => 'restored']);

    $response = $this->actingAs($user)->get('/reports/activity-log');

    $response->assertOk();
    SnappyPdf::assertViewIs('reports.activity-log');
    SnappyPdf::assertSee('archived Fix bug');
    SnappyPdf::assertSee('restored Fix bug');
});

test('the activity-log csv export has a header row and one row per activity', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create(['name' => 'Engineering']);
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create(['name' => 'Fix bug']);
    $card->activities()->create(['user_id' => $user->id, 'type' => 'archived']);
    $card->activities()->create(['user_id' => $user->id, 'type' => 'restored']);

    $response = $this->actingAs($user)->get('/reports/activity-log/csv');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $lines = array_filter(explode("\n", $response->streamedContent()));
    expect($lines)->toHaveCount(3);
    expect($lines[0])->toBe('Date,Workspace,Board,User,Activity');
});

test('the progress report computes checklist completion percentage per board and per card', function () {
    SnappyPdf::fake();

    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create(['name' => 'Team Alpha']);
    $board = Board::factory()->for($workspace)->for($user)->create(['name' => 'Engineering']);
    $list = BoardList::factory()->for($board)->create();

    $card1 = Card::factory()->for($list)->create(['name' => 'Fully done']);
    $checklist1 = Checklist::factory()->for($card1)->create();
    ChecklistItem::factory()->for($checklist1)->create(['is_checked' => true]);
    ChecklistItem::factory()->for($checklist1)->create(['is_checked' => true]);

    $card2 = Card::factory()->for($list)->create(['name' => 'Half done']);
    $checklist2 = Checklist::factory()->for($card2)->create();
    ChecklistItem::factory()->for($checklist2)->create(['is_checked' => true]);
    ChecklistItem::factory()->for($checklist2)->create(['is_checked' => false]);

    $card3 = Card::factory()->for($list)->create(['name' => 'No checklist']);

    $response = $this->actingAs($user)->get('/reports/progress');

    $response->assertOk();
    SnappyPdf::assertViewIs('reports.progress');
    SnappyPdf::assertViewHas('grouped', function ($grouped) {
        $workspace = $grouped['Team Alpha'];
        $board = $workspace['boards']['Engineering'];

        return $workspace['percent'] === 75
            && $board['percent'] === 75
            && $board['cards']->firstWhere('name', 'Fully done')['percent'] === 100
            && $board['cards']->firstWhere('name', 'Half done')['percent'] === 50
            && $board['cards']->firstWhere('name', 'No checklist')['percent'] === null;
    });
    SnappyPdf::assertSee('Team Alpha');
    SnappyPdf::assertSee('Engineering');
    SnappyPdf::assertSee('Fully done');
    SnappyPdf::assertSee('No checklist');
});

test('the checklist-timeline report groups items by board, card, and checklist', function () {
    SnappyPdf::fake();

    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create(['name' => 'Engineering']);
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create(['name' => 'Sprint 1']);

    $checklistA = Checklist::factory()->for($card)->create(['name' => 'GPPK100']);
    ChecklistItem::factory()->for($checklistA)->create(['name' => 'UPDATE', 'due_date' => '2026-08-26', 'is_checked' => false]);

    $checklistB = Checklist::factory()->for($card)->create(['name' => 'GPPK200']);
    ChecklistItem::factory()->for($checklistB)->create(['name' => 'UPDATE', 'due_date' => '2026-09-01', 'completed_at' => '2026-08-30', 'is_checked' => true]);

    $response = $this->actingAs($user)->get('/reports/checklist-timeline');

    $response->assertOk();
    SnappyPdf::assertViewIs('reports.checklist-timeline');
    SnappyPdf::assertSee('Engineering');
    SnappyPdf::assertSee('Sprint 1');
    SnappyPdf::assertSee('GPPK100');
    SnappyPdf::assertSee('GPPK200');
});

test('the checklist-timeline report shows assigned members, or "Unassigned" when none', function () {
    SnappyPdf::fake();

    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create(['name' => 'Engineering']);
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create(['name' => 'Sprint 1']);
    $checklist = Checklist::factory()->for($card)->create(['name' => 'GPPK100']);

    $assignee = User::factory()->create(['name' => 'Priya Sharma']);
    $board->workspace->members()->attach($assignee->id);
    $assignedItem = ChecklistItem::factory()->for($checklist)->create(['name' => 'FORM', 'due_date' => '2026-08-31', 'is_checked' => true, 'completed_at' => '2026-08-30']);
    $assignedItem->members()->attach($assignee->id);

    ChecklistItem::factory()->for($checklist)->create(['name' => 'DELETE', 'due_date' => '2026-08-26', 'is_checked' => false]);

    $response = $this->actingAs($user)->get('/reports/checklist-timeline');

    $response->assertOk();
    SnappyPdf::assertSee('Priya Sharma');
    SnappyPdf::assertSee('Unassigned');
});

test('the activity-log csv export neutralizes formula injection in user-controlled cells', function () {
    $user = User::factory()->create(['name' => '=cmd|/C calc']);
    $board = Board::factory()->for($user)->create(['name' => 'Engineering']);
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create(['name' => 'Fix bug']);
    $card->activities()->create(['user_id' => $user->id, 'type' => 'archived']);

    $response = $this->actingAs($user)->get('/reports/activity-log/csv');

    $response->assertOk();

    $lines = array_filter(explode("\n", $response->streamedContent()));
    expect($lines)->toHaveCount(2);
    expect($lines[1])->toContain('\'=cmd|/C calc');
    expect($lines[1])->not->toContain(',=cmd|/C calc');
});

test('a users own boards never leak into another users reports', function () {
    SnappyPdf::fake();

    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create(['name' => 'My Board']);
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create(['name' => 'My Card']);
    $checklist = Checklist::factory()->for($card)->create();
    ChecklistItem::factory()->for($checklist)->create([
        'name' => 'My Task',
        'due_date' => '2026-09-01',
        'completed_at' => '2026-09-05 10:00:00',
        'is_checked' => true,
    ]);
    $card->activities()->create(['user_id' => $user->id, 'type' => 'archived']);

    $outsider = User::factory()->create();
    $outsiderBoard = Board::factory()->for($outsider)->create(['name' => 'Outsiders Board']);
    $outsiderList = BoardList::factory()->for($outsiderBoard)->create();
    $outsiderCard = Card::factory()->for($outsiderList)->create(['name' => 'Outsiders Card']);
    $outsiderChecklist = Checklist::factory()->for($outsiderCard)->create();
    ChecklistItem::factory()->for($outsiderChecklist)->create([
        'name' => 'Outsiders Secret Task',
        'due_date' => '2026-09-01',
        'completed_at' => '2026-09-05 10:00:00',
        'is_checked' => true,
    ]);
    $outsiderCard->activities()->create(['user_id' => $outsider->id, 'type' => 'archived']);

    $this->actingAs($user)->get('/reports/on-time-completion')->assertOk();
    SnappyPdf::assertDontSee('Outsiders Secret Task');
    SnappyPdf::assertDontSee('Outsiders Board');

    $this->actingAs($user)->get('/reports/member-performance')->assertOk();
    SnappyPdf::assertDontSee($outsider->name);

    $this->actingAs($user)->get('/reports/activity-log')->assertOk();
    SnappyPdf::assertDontSee('Outsiders Card');
    SnappyPdf::assertDontSee($outsider->name);

    $this->actingAs($user)->get('/reports/checklist-timeline')->assertOk();
    SnappyPdf::assertDontSee('Outsiders Secret Task');
    SnappyPdf::assertDontSee('Outsiders Card');

    $csvResponse = $this->actingAs($user)->get('/reports/activity-log/csv');
    $csvResponse->assertOk();
    expect($csvResponse->streamedContent())->not->toContain('Outsiders Card');
});

test('a user can view the reports index page', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create(['name' => 'Engineering']);

    $response = $this->actingAs($user)->get('/reports');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Reports')
        ->has('boards', 1)
        ->where('boards.0.name', 'Engineering'));
});

test('the checklist-timeline report shows the workspace name above each board', function () {
    SnappyPdf::fake();

    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create(['name' => 'Team Alpha']);
    $board = Board::factory()->for($workspace)->for($user)->create(['name' => 'Engineering']);
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create(['name' => 'Sprint 1']);
    $checklist = Checklist::factory()->for($card)->create(['name' => 'GPPK100']);
    ChecklistItem::factory()->for($checklist)->create(['name' => 'FORM', 'due_date' => '2026-08-31', 'is_checked' => true, 'completed_at' => '2026-08-30']);

    $response = $this->actingAs($user)->get('/reports/checklist-timeline');

    $response->assertOk();
    SnappyPdf::assertSee('Team Alpha');
});

test('the on-time-completion report shows the workspace name for each late item', function () {
    SnappyPdf::fake();

    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create(['name' => 'Team Alpha']);
    $board = Board::factory()->for($workspace)->for($user)->create(['name' => 'Engineering']);
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $checklist = Checklist::factory()->for($card)->create();
    ChecklistItem::factory()->for($checklist)->create([
        'name' => 'Late item',
        'due_date' => '2026-09-01',
        'completed_at' => '2026-09-05 10:00:00',
        'is_checked' => true,
    ]);

    $response = $this->actingAs($user)->get('/reports/on-time-completion');

    $response->assertOk();
    SnappyPdf::assertSee('Team Alpha');
});

test('the on-time-completion report shows assigned members, or "Unassigned" when none', function () {
    SnappyPdf::fake();

    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $checklist = Checklist::factory()->for($card)->create();

    $assignee = User::factory()->create(['name' => 'Priya Sharma']);
    $board->workspace->members()->attach($assignee->id);
    $assignedItem = ChecklistItem::factory()->for($checklist)->create([
        'name' => 'Assigned late item',
        'due_date' => '2026-09-01',
        'completed_at' => '2026-09-05 10:00:00',
        'is_checked' => true,
    ]);
    $assignedItem->members()->attach($assignee->id);

    ChecklistItem::factory()->for($checklist)->create([
        'name' => 'Unassigned late item',
        'due_date' => '2026-09-01',
        'completed_at' => '2026-09-05 10:00:00',
        'is_checked' => true,
    ]);

    $response = $this->actingAs($user)->get('/reports/on-time-completion');

    $response->assertOk();
    SnappyPdf::assertSee('Priya Sharma');
    SnappyPdf::assertSee('Unassigned');
});

test('the activity-log report and csv export both show the workspace name', function () {
    SnappyPdf::fake();

    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create(['name' => 'Team Alpha']);
    $board = Board::factory()->for($workspace)->for($user)->create(['name' => 'Engineering']);
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create(['name' => 'Fix bug']);
    $card->activities()->create(['user_id' => $user->id, 'type' => 'archived']);

    $this->actingAs($user)->get('/reports/activity-log')->assertOk();
    SnappyPdf::assertSee('Team Alpha');

    $csvResponse = $this->actingAs($user)->get('/reports/activity-log/csv');
    $csvResponse->assertOk();
    $lines = array_filter(explode("\n", $csvResponse->streamedContent()));
    expect($lines[0])->toBe('Date,Workspace,Board,User,Activity');
    expect($lines[1])->toContain('Team Alpha');
});
