<?php

use App\Exceptions\GeminiApiException;
use App\Services\GeminiClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

test('reply returns plain text when Gemini responds with text only', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                ['content' => ['role' => 'model', 'parts' => [['text' => 'Here are some ideas.']]]],
            ],
        ], 200),
    ]);

    $client = new GeminiClient('fake-key', 'gemini-2.0-flash');
    $result = $client->reply('My Board', [], [['role' => 'user', 'content' => 'help me plan']]);

    expect($result)->toBe(['content' => 'Here are some ideas.', 'tool_action' => null]);
});

test('reply returns a create_lists tool action when Gemini calls that function', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                ['content' => ['role' => 'model', 'parts' => [
                    ['functionCall' => ['name' => 'create_lists', 'args' => ['names' => ['Research', 'Design']]]],
                ]]],
            ],
        ], 200),
    ]);

    $client = new GeminiClient('fake-key', 'gemini-2.0-flash');
    $result = $client->reply('My Board', [], [['role' => 'user', 'content' => 'suggest lists']]);

    expect($result['tool_action'])->toBe(['type' => 'create_lists', 'names' => ['Research', 'Design']]);
    expect($result['content'])->toContain('Research');
});

test('reply returns a create_cards tool action when Gemini calls that function', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                ['content' => ['role' => 'model', 'parts' => [
                    ['functionCall' => [
                        'name' => 'create_cards',
                        'args' => ['list_name' => 'Research', 'card_names' => ['Competitor audit']],
                    ]],
                ]]],
            ],
        ], 200),
    ]);

    $client = new GeminiClient('fake-key', 'gemini-2.0-flash');
    $result = $client->reply('My Board', [], [['role' => 'user', 'content' => 'add a card']]);

    expect($result['tool_action'])->toBe([
        'type' => 'create_cards',
        'list_name' => 'Research',
        'card_names' => ['Competitor audit'],
    ]);
});

test('reply throws GeminiApiException when the HTTP request fails', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(['error' => 'boom'], 500),
    ]);

    $client = new GeminiClient('fake-key', 'gemini-2.0-flash');

    expect(fn () => $client->reply('My Board', [], [['role' => 'user', 'content' => 'hi']]))
        ->toThrow(GeminiApiException::class);
});
