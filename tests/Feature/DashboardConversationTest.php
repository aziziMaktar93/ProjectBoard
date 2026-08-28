<?php

use App\Models\DashboardConversation;
use App\Models\User;
use Illuminate\Database\QueryException;

test('a dashboard conversation belongs to a user and lists its messages oldest first', function () {
    $user = User::factory()->create();

    $conversation = DashboardConversation::create(['user_id' => $user->id]);
    $conversation->messages()->create(['role' => 'user', 'content' => 'first']);
    $conversation->messages()->create(['role' => 'assistant', 'content' => 'second']);

    expect($conversation->user->id)->toBe($user->id);
    expect($conversation->fresh()->messages->pluck('content')->all())->toBe(['first', 'second']);
});

test('a dashboard conversation is unique per user', function () {
    $user = User::factory()->create();
    DashboardConversation::create(['user_id' => $user->id]);

    expect(fn () => DashboardConversation::create(['user_id' => $user->id]))
        ->toThrow(QueryException::class);
});
