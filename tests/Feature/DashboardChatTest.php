<?php

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\DashboardConversation;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Http;

function fakeDashboardGeminiTextReply(string $text): void
{
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                ['content' => ['role' => 'model', 'parts' => [['text' => $text]]]],
            ],
        ], 200),
    ]);
}

test('a user can open their dashboard conversation, created lazily', function () {
    $user = User::factory()->create();

    expect(DashboardConversation::count())->toBe(0);

    $response = $this->actingAs($user)->getJson('/dashboard/ai/conversation');

    $response->assertOk()->assertJson(['messages' => []]);
    expect(DashboardConversation::where('user_id', $user->id)->exists())->toBeTrue();
});

test('two users get separate dashboard conversations', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $this->actingAs($userA)->getJson('/dashboard/ai/conversation')->assertOk();
    $this->actingAs($userB)->getJson('/dashboard/ai/conversation')->assertOk();

    expect(DashboardConversation::count())->toBe(2);
});

test('a guest cannot open or send messages to the dashboard conversation', function () {
    $this->getJson('/dashboard/ai/conversation')->assertUnauthorized();
    $this->postJson('/dashboard/ai/messages', ['content' => 'hi'])->assertUnauthorized();
});

test('sending a message stores it and stores a plain-text assistant reply', function () {
    fakeDashboardGeminiTextReply('You have 2 overdue tasks.');

    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/dashboard/ai/messages', ['content' => 'how many overdue tasks?']);

    $response->assertOk();
    $response->assertJsonPath('reply.content', 'You have 2 overdue tasks.');

    $conversation = DashboardConversation::where('user_id', $user->id)->first();
    expect($conversation->messages)->toHaveCount(2);
    expect($conversation->messages->first()->role)->toBe('user');
    expect($conversation->messages->last()->role)->toBe('assistant');
});

test('a failed gemini call keeps the user message but does not create a bogus assistant reply', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(['error' => 'boom'], 500),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/dashboard/ai/messages', ['content' => 'how am I doing?']);

    $response->assertStatus(502);
    $response->assertJsonPath('error', "AI couldn't respond, try again.");

    $conversation = DashboardConversation::where('user_id', $user->id)->first();
    expect($conversation->messages)->toHaveCount(1);
    expect($conversation->messages->first()->role)->toBe('user');
});

test('the ai context reflects the users full board set, not any dashboard filter', function () {
    $capturedBody = null;
    Http::fake(function ($request) use (&$capturedBody) {
        $capturedBody = $request->data();

        return Http::response([
            'candidates' => [['content' => ['role' => 'model', 'parts' => [['text' => 'ok']]]]],
        ], 200);
    });

    $user = User::factory()->create();
    $workspaceA = Workspace::factory()->for($user, 'owner')->create();
    $boardA = Board::factory()->for($workspaceA)->for($user)->create(['name' => 'Board A']);
    $listA = BoardList::factory()->for($boardA)->create();
    Card::factory()->for($listA)->create();

    $workspaceB = Workspace::factory()->for($user, 'owner')->create();
    $boardB = Board::factory()->for($workspaceB)->for($user)->create(['name' => 'Board B']);
    $listB = BoardList::factory()->for($boardB)->create();
    Card::factory()->for($listB)->create();

    $this->actingAs($user)->postJson('/dashboard/ai/messages?workspace_id='.$workspaceA->id.'&board_id='.$boardA->id, ['content' => 'summarize my boards']);

    $systemText = $capturedBody['system_instruction']['parts'][0]['text'];
    expect($systemText)->toContain('Board A');
    expect($systemText)->toContain('Board B');
});

test('the ai context includes overdue and due-soon task names, not just counts', function () {
    $capturedBody = null;
    Http::fake(function ($request) use (&$capturedBody) {
        $capturedBody = $request->data();

        return Http::response([
            'candidates' => [['content' => ['role' => 'model', 'parts' => [['text' => 'ok']]]]],
        ], 200);
    });

    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user, 'owner')->create();
    $board = Board::factory()->for($workspace)->for($user)->create(['name' => 'Engineering']);
    $list = BoardList::factory()->for($board)->create();
    Card::factory()->for($list)->overdue()->create(['name' => 'Fix bug']);

    $this->actingAs($user)->postJson('/dashboard/ai/messages', ['content' => 'what is overdue?']);

    $systemText = $capturedBody['system_instruction']['parts'][0]['text'];
    expect($systemText)->toContain('Fix bug');
});
