<?php

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\Checklist;
use App\Models\User;
use App\Models\Workspace;

test('a board member can post a comment on a card', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();

    $response = $this->actingAs($user)->post("/cards/{$card->id}/activities", ['body' => 'Looks good to me']);

    $response->assertRedirect();
    $activity = $card->activities()->first();
    expect($activity->type)->toBe('comment');
    expect($activity->body)->toBe('Looks good to me');
    expect($activity->user_id)->toBe($user->id);
});

test('a comment requires a body', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();

    $response = $this->actingAs($user)->post("/cards/{$card->id}/activities", ['body' => '']);

    $response->assertSessionHasErrors('body');
});

test('a non-board-member cannot post a comment', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $other = User::factory()->create();

    $response = $this->actingAs($other)->post("/cards/{$card->id}/activities", ['body' => 'Hacked']);

    $response->assertForbidden();
    expect($card->activities()->count())->toBe(0);
});

test('moving a card between lists logs a moved activity', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $listA = BoardList::factory()->for($board)->create(['name' => 'To Do']);
    $listB = BoardList::factory()->for($board)->create(['name' => 'Done']);
    $card = Card::factory()->for($listA)->create(['position' => 0]);

    $this->actingAs($user)->patch("/boards/{$board->id}/cards/reorder", [
        'source_list_id' => $listA->id,
        'source_ordered_ids' => [],
        'target_list_id' => $listB->id,
        'target_ordered_ids' => [$card->id],
    ]);

    $activity = $card->activities()->where('type', 'moved')->first();
    expect($activity)->not->toBeNull();
    expect($activity->data)->toBe(['from_list' => 'To Do', 'to_list' => 'Done']);
});

test('reordering cards within the same list does not log a moved activity', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $first = Card::factory()->for($list)->create(['position' => 0]);
    $second = Card::factory()->for($list)->create(['position' => 1]);

    $this->actingAs($user)->patch("/boards/{$board->id}/cards/reorder", [
        'target_list_id' => $list->id,
        'target_ordered_ids' => [$second->id, $first->id],
    ]);

    expect($first->activities()->where('type', 'moved')->count())->toBe(0);
    expect($second->activities()->where('type', 'moved')->count())->toBe(0);
});

test('checking a checklist item logs a completed activity', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $checklist = Checklist::factory()->for($card)->create(['name' => 'Launch Checklist']);
    $item = $checklist->items()->create(['name' => 'Write tests', 'is_checked' => false, 'position' => 0]);

    $this->actingAs($user)->patch("/checklist-items/{$item->id}", ['is_checked' => true]);

    $activity = $card->activities()->where('type', 'checklist_item_completed')->first();
    expect($activity)->not->toBeNull();
    expect($activity->data)->toBe(['item_name' => 'Write tests', 'checklist_name' => 'Launch Checklist']);
});

test('unchecking a checklist item logs an uncompleted activity', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $checklist = Checklist::factory()->for($card)->create();
    $item = $checklist->items()->create(['name' => 'Write tests', 'is_checked' => true, 'position' => 0]);

    $this->actingAs($user)->patch("/checklist-items/{$item->id}", ['is_checked' => false]);

    $activity = $card->activities()->where('type', 'checklist_item_uncompleted')->first();
    expect($activity)->not->toBeNull();
});

test('renaming a checklist item without changing is_checked does not log an activity', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $checklist = Checklist::factory()->for($card)->create();
    $item = $checklist->items()->create(['name' => 'Old name', 'is_checked' => false, 'position' => 0]);

    $this->actingAs($user)->patch("/checklist-items/{$item->id}", ['name' => 'New name']);

    expect($card->activities()->count())->toBe(0);
});

test('adding a card member logs a member_added activity', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $member = User::factory()->create(['name' => 'Bob Tan']);
    $workspace->members()->attach($member->id);
    $board->members()->attach($member->id);

    $this->actingAs($owner)->post("/cards/{$card->id}/members", ['user_id' => $member->id]);

    $activity = $card->activities()->where('type', 'member_added')->first();
    expect($activity)->not->toBeNull();
    expect($activity->data)->toBe(['member_name' => 'Bob Tan']);
});

test('removing a card member logs a member_removed activity', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $member = User::factory()->create(['name' => 'Bob Tan']);
    $card->members()->attach($member->id);

    $this->actingAs($owner)->delete("/cards/{$card->id}/members/{$member->id}");

    $activity = $card->activities()->where('type', 'member_removed')->first();
    expect($activity)->not->toBeNull();
    expect($activity->data)->toBe(['member_name' => 'Bob Tan']);
});

test('archiving and restoring a card logs activities', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();

    $this->actingAs($user)->patch("/cards/{$card->id}/archive");
    expect($card->activities()->where('type', 'archived')->count())->toBe(1);

    $this->actingAs($user)->patch("/cards/{$card->id}/restore");
    expect($card->activities()->where('type', 'restored')->count())->toBe(1);
});

test('the board show page includes card activities with the acting user', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $card->activities()->create(['user_id' => $user->id, 'type' => 'comment', 'body' => 'Hello']);

    $response = $this->actingAs($user)->get("/boards/{$board->id}");

    $response->assertInertia(
        fn ($page) => $page
            ->component('boards/Show')
            ->where('board.lists.0.cards.0.activities.0.body', 'Hello')
            ->where('board.lists.0.cards.0.activities.0.user.id', $user->id)
    );
});
