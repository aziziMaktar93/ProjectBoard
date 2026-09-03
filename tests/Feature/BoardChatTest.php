<?php

use App\Models\Board;
use App\Models\BoardMessage;
use App\Models\Notification;
use App\Models\User;
use App\Models\Workspace;

test('a board member can list and post chat messages', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($user)->create();

    $this->actingAs($user)->getJson("/boards/{$board->id}/chat/messages")
        ->assertOk()
        ->assertJson(['messages' => []]);

    $response = $this->actingAs($user)->postJson("/boards/{$board->id}/chat/messages", ['content' => 'Hello team']);

    $response->assertOk();
    $response->assertJsonPath('message.content', 'Hello team');
    $response->assertJsonPath('message.user.id', $user->id);
    expect(BoardMessage::where('board_id', $board->id)->count())->toBe(1);
});

test('a board viewer can list and post chat messages', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create();
    $viewer = User::factory()->create();
    $workspace->members()->attach($viewer->id);
    $board->members()->attach($viewer->id, ['role' => 'viewer']);

    $this->actingAs($viewer)->getJson("/boards/{$board->id}/chat/messages")->assertOk();
    $this->actingAs($viewer)->postJson("/boards/{$board->id}/chat/messages", ['content' => 'Just a viewer, saying hi'])
        ->assertOk();

    expect(BoardMessage::where('board_id', $board->id)->count())->toBe(1);
});

test('a non-member cannot list or post chat messages', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create();
    $outsider = User::factory()->create();

    $this->actingAs($outsider)->getJson("/boards/{$board->id}/chat/messages")->assertForbidden();
    $this->actingAs($outsider)->postJson("/boards/{$board->id}/chat/messages", ['content' => 'hi'])->assertForbidden();
});

test('posting an empty message fails validation', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($user)->create();

    $this->actingAs($user)->postJson("/boards/{$board->id}/chat/messages", ['content' => ''])
        ->assertStatus(422);
});

test('mentioning a member by name creates a board_message_mention notification for them only', function () {
    $author = User::factory()->create();
    $workspace = Workspace::factory()->for($author, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($author)->create(['name' => 'Launch Plan']);
    $mentioned = User::factory()->create(['name' => 'Jamie Lee']);
    $notMentioned = User::factory()->create(['name' => 'Alex Kim']);
    $workspace->members()->attach([$mentioned->id, $notMentioned->id]);
    $board->members()->attach([$mentioned->id, $notMentioned->id]);

    $this->actingAs($author)->postJson("/boards/{$board->id}/chat/messages", ['content' => 'Hey @Jamie Lee can you check this?'])
        ->assertOk();

    expect(Notification::where('user_id', $mentioned->id)->where('type', 'board_message_mention')->count())->toBe(1);
    expect(Notification::where('user_id', $notMentioned->id)->count())->toBe(0);
    expect(Notification::where('user_id', $author->id)->count())->toBe(0);

    $notification = Notification::where('user_id', $mentioned->id)->first();
    expect($notification->data['board_id'])->toBe($board->id);
    expect($notification->data['board_name'])->toBe('Launch Plan');
    expect($notification->data['actor_name'])->toBe($author->name);
});

test('a user can delete their own message but not someone else\'s', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create();
    $other = User::factory()->create();
    $workspace->members()->attach($other->id);
    $board->members()->attach($other->id);

    $ownMessage = BoardMessage::factory()->for($board)->for($owner)->create();
    $othersMessage = BoardMessage::factory()->for($board)->for($other)->create();

    $this->actingAs($owner)->deleteJson("/boards/{$board->id}/chat/messages/{$othersMessage->id}")
        ->assertForbidden();
    expect(BoardMessage::find($othersMessage->id))->not->toBeNull();

    $this->actingAs($owner)->deleteJson("/boards/{$board->id}/chat/messages/{$ownMessage->id}")
        ->assertOk();
    expect(BoardMessage::find($ownMessage->id))->toBeNull();
});

test('listing messages updates the requesting users chat_last_read_at pivot value', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($user)->create();

    $this->actingAs($user)->getJson("/boards/{$board->id}/chat/messages")->assertOk();

    $pivot = $board->members()->where('users.id', $user->id)->first()->pivot;
    expect($pivot->chat_last_read_at)->not->toBeNull();
});
