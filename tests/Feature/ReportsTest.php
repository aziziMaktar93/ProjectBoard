<?php

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\User;
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
    expect($lines[0])->toBe('Date,Board,User,Activity');
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
