<?php

use App\Models\AiConversation;
use App\Models\Board;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Http;

function fakeGeminiTextReply(string $text): void
{
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                ['content' => ['role' => 'model', 'parts' => [['text' => $text]]]],
            ],
        ], 200),
    ]);
}

function fakeGeminiFunctionCall(string $name, array $args): void
{
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                ['content' => ['role' => 'model', 'parts' => [
                    ['functionCall' => ['name' => $name, 'args' => $args]],
                ]]],
            ],
        ], 200),
    ]);
}

test('a board member can open the ai conversation for a board, created lazily', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($user)->create();

    expect(AiConversation::count())->toBe(0);

    $response = $this->actingAs($user)->getJson("/boards/{$board->id}/ai/conversation");

    $response->assertOk()->assertJson(['messages' => []]);
    expect(AiConversation::where('board_id', $board->id)->where('user_id', $user->id)->exists())->toBeTrue();
});

test('two board members get separate ai conversations', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create();
    $member = User::factory()->create();
    $board->members()->attach($member->id);

    $this->actingAs($owner)->getJson("/boards/{$board->id}/ai/conversation")->assertOk();
    $this->actingAs($member)->getJson("/boards/{$board->id}/ai/conversation")->assertOk();

    expect(AiConversation::where('board_id', $board->id)->count())->toBe(2);
});

test('a non-member cannot open or send messages to a board ai conversation', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($owner)->create();
    $outsider = User::factory()->create();

    $this->actingAs($outsider)->getJson("/boards/{$board->id}/ai/conversation")->assertForbidden();
    $this->actingAs($outsider)->postJson("/boards/{$board->id}/ai/messages", ['content' => 'hi'])->assertForbidden();
});

test('sending a message stores it and stores a plain-text assistant reply', function () {
    fakeGeminiTextReply('Here are some ideas for your board.');

    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($user)->create();

    $response = $this->actingAs($user)->postJson("/boards/{$board->id}/ai/messages", ['content' => 'help me plan']);

    $response->assertOk();
    $response->assertJsonPath('reply.content', 'Here are some ideas for your board.');
    $response->assertJsonPath('reply.tool_action', null);

    $conversation = AiConversation::where('board_id', $board->id)->where('user_id', $user->id)->first();
    expect($conversation->messages)->toHaveCount(2);
    expect($conversation->messages->first()->role)->toBe('user');
    expect($conversation->messages->last()->role)->toBe('assistant');
});

test('sending a message that triggers create_lists stores the tool action', function () {
    fakeGeminiFunctionCall('create_lists', ['names' => ['Research', 'Design']]);

    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($user)->create();

    $response = $this->actingAs($user)->postJson("/boards/{$board->id}/ai/messages", ['content' => 'suggest lists']);

    $response->assertOk();
    $response->assertJsonPath('reply.tool_action', ['type' => 'create_lists', 'names' => ['Research', 'Design']]);
});

test('a failed gemini call keeps the user message but does not create a bogus assistant reply', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(['error' => 'boom'], 500),
    ]);

    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($user)->create();

    $response = $this->actingAs($user)->postJson("/boards/{$board->id}/ai/messages", ['content' => 'help me plan']);

    $response->assertStatus(502);
    $response->assertJsonPath('error', "AI couldn't respond, try again.");

    $conversation = AiConversation::where('board_id', $board->id)->where('user_id', $user->id)->first();
    expect($conversation->messages)->toHaveCount(1);
    expect($conversation->messages->first()->role)->toBe('user');
});
