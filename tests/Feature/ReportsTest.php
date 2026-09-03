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
