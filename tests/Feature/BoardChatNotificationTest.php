<?php

use App\Models\Board;
use App\Models\Notification;
use App\Models\User;
use App\Models\Workspace;

test('opening a board_message_mention notification redirects to the board without a card param', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($user)->create();

    $notification = Notification::create([
        'user_id' => $user->id,
        'type' => 'board_message_mention',
        'data' => [
            'board_id' => $board->id,
            'board_name' => $board->name,
            'actor_name' => 'Jamie Lee',
            'message_preview' => 'hey there',
        ],
    ]);

    $response = $this->actingAs($user)->get("/notifications/{$notification->id}/open");

    $response->assertRedirect(route('boards.show', $board->id));
    expect($notification->fresh()->read_at)->not->toBeNull();
});
