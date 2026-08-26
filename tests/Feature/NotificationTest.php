<?php

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\Notification;
use App\Models\User;
use App\Models\Workspace;

test('assigning a card to another member creates a notification for them', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create(['name' => 'Ship it']);
    $member = User::factory()->create();
    $workspace->members()->attach($member->id);
    $board->members()->attach($member->id);

    $this->actingAs($owner)->post("/cards/{$card->id}/members", ['user_id' => $member->id]);

    expect(Notification::where('user_id', $member->id)->where('type', 'card_assigned')->count())->toBe(1);
    $notification = Notification::where('user_id', $member->id)->first();
    expect($notification->data)->toMatchArray([
        'card_id' => $card->id,
        'card_name' => 'Ship it',
        'board_id' => $board->id,
        'actor_name' => $owner->name,
    ]);
});

test('assigning a card to yourself does not create a notification', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();

    $this->actingAs($owner)->post("/cards/{$card->id}/members", ['user_id' => $owner->id]);

    expect(Notification::where('user_id', $owner->id)->count())->toBe(0);
});

test('mentioning a board member in a comment creates a notification for them', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create(['name' => 'Ship it']);
    $member = User::factory()->create(['name' => 'Bob Tan']);
    $workspace->members()->attach($member->id);
    $board->members()->attach($member->id);

    $this->actingAs($owner)->post("/cards/{$card->id}/activities", ['body' => 'Hey @Bob Tan, can you review this?']);

    expect(Notification::where('user_id', $member->id)->where('type', 'mention')->count())->toBe(1);
});

test('a comment with no matching mention creates no notification', function () {
    $owner = User::factory()->create();
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();

    $this->actingAs($owner)->post("/cards/{$card->id}/activities", ['body' => 'Just a regular comment']);

    expect(Notification::count())->toBe(0);
});

test('mentioning yourself does not create a notification', function () {
    $owner = User::factory()->create(['name' => 'Alice']);
    $board = Board::factory()->for($owner)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();

    $this->actingAs($owner)->post("/cards/{$card->id}/activities", ['body' => 'Note to self @Alice']);

    expect(Notification::count())->toBe(0);
});

test('opening a notification marks it read and redirects to the card in one request', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $list = BoardList::factory()->for($board)->create();
    $card = Card::factory()->for($list)->create();
    $notification = Notification::factory()->for($user)->create([
        'data' => ['card_id' => $card->id, 'card_name' => $card->name, 'board_id' => $board->id, 'actor_name' => 'Someone'],
    ]);

    $response = $this->actingAs($user)->get("/notifications/{$notification->id}/open");

    $response->assertRedirect(route('boards.show', ['board' => $board->id, 'card' => $card->id]));
    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('a user cannot open another user\'s notification', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $notification = Notification::factory()->for($other)->create();

    $response = $this->actingAs($user)->get("/notifications/{$notification->id}/open");

    $response->assertForbidden();
    expect($notification->fresh()->read_at)->toBeNull();
});

test('a user can mark a notification as read', function () {
    $user = User::factory()->create();
    $notification = Notification::factory()->for($user)->create();

    $response = $this->actingAs($user)->patch("/notifications/{$notification->id}/read");

    $response->assertRedirect();
    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('a user cannot mark another user\'s notification as read', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $notification = Notification::factory()->for($other)->create();

    $response = $this->actingAs($user)->patch("/notifications/{$notification->id}/read");

    $response->assertForbidden();
    expect($notification->fresh()->read_at)->toBeNull();
});

test('a user can view their notifications page', function () {
    $user = User::factory()->create();
    Notification::factory()->for($user)->count(3)->create();
    $other = User::factory()->create();
    Notification::factory()->for($other)->create();

    $response = $this->actingAs($user)->get('/notifications');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Notifications')->has('notifications.data', 3));
});

test('a guest cannot view the notifications page', function () {
    $response = $this->get('/notifications');

    $response->assertRedirect('/login');
});

test('the notifications page paginates results', function () {
    $user = User::factory()->create();
    Notification::factory()->for($user)->count(20)->create();

    $response = $this->actingAs($user)->get('/notifications');

    $response->assertInertia(
        fn ($page) => $page
            ->component('Notifications')
            ->has('notifications.data', 15)
            ->where('notifications.total', 20)
            ->where('notifications.last_page', 2)
    );
});

test('the notifications page can be filtered by read status', function () {
    $user = User::factory()->create();
    Notification::factory()->for($user)->count(2)->create();
    Notification::factory()->for($user)->read()->count(3)->create();

    $unread = $this->actingAs($user)->get('/notifications?status=unread');
    $unread->assertInertia(fn ($page) => $page->has('notifications.data', 2));

    $read = $this->actingAs($user)->get('/notifications?status=read');
    $read->assertInertia(fn ($page) => $page->has('notifications.data', 3));
});

test('the notifications page can be searched by actor or card name', function () {
    $user = User::factory()->create();
    Notification::factory()->for($user)->create([
        'data' => ['card_id' => 1, 'card_name' => 'Ship the release', 'board_id' => 1, 'actor_name' => 'Bob Tan'],
    ]);
    Notification::factory()->for($user)->create([
        'data' => ['card_id' => 2, 'card_name' => 'Write docs', 'board_id' => 1, 'actor_name' => 'Alice Wong'],
    ]);

    $byCard = $this->actingAs($user)->get('/notifications?search=release');
    $byCard->assertInertia(fn ($page) => $page->has('notifications.data', 1));

    $byActor = $this->actingAs($user)->get('/notifications?search=Alice');
    $byActor->assertInertia(fn ($page) => $page->has('notifications.data', 1));

    $noMatch = $this->actingAs($user)->get('/notifications?search=nothingmatches');
    $noMatch->assertInertia(fn ($page) => $page->has('notifications.data', 0));
});

test('a user can mark all of their notifications as read', function () {
    $user = User::factory()->create();
    Notification::factory()->for($user)->count(3)->create();
    $otherUser = User::factory()->create();
    $otherNotification = Notification::factory()->for($otherUser)->create();

    $response = $this->actingAs($user)->patch('/notifications/read-all');

    $response->assertRedirect();
    expect($user->appNotifications()->whereNull('read_at')->count())->toBe(0);
    expect($otherNotification->fresh()->read_at)->toBeNull();
});
