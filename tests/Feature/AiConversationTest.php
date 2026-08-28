<?php

use App\Models\AiConversation;
use App\Models\Board;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Carbon;

test('an ai conversation belongs to a board and a user, and lists its messages oldest first', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($user)->create();

    $conversation = AiConversation::create(['board_id' => $board->id, 'user_id' => $user->id]);
    $conversation->messages()->create(['role' => 'user', 'content' => 'first']);
    $conversation->messages()->create(['role' => 'assistant', 'content' => 'second']);

    expect($conversation->board->id)->toBe($board->id);
    expect($conversation->user->id)->toBe($user->id);
    expect($conversation->fresh()->messages->pluck('content')->all())->toBe(['first', 'second']);
});

test('an ai message casts tool_action to an array and applied_at to a datetime', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($user)->create();
    $conversation = AiConversation::create(['board_id' => $board->id, 'user_id' => $user->id]);

    $message = $conversation->messages()->create([
        'role' => 'assistant',
        'content' => 'I will create these lists: Research',
        'tool_action' => ['type' => 'create_lists', 'names' => ['Research']],
        'applied_at' => now(),
    ]);

    expect($message->fresh()->tool_action)->toBe(['type' => 'create_lists', 'names' => ['Research']]);
    expect($message->fresh()->applied_at)->toBeInstanceOf(Carbon::class);
});
