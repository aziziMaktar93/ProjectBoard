<?php

namespace App\Services;

use App\Exceptions\GeminiApiException;
use Illuminate\Support\Facades\Http;

class GeminiClient
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
    ) {}

    /**
     * @param  array<int, array{name: string, card_count: int}>  $lists
     * @param  array<int, array{role: string, content: string}>  $messages  Oldest first; the last entry is the new user message.
     * @return array{content: string, tool_action: array<string, mixed>|null}
     */
    public function reply(string $boardName, array $lists, array $messages): array
    {
        $response = Http::post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}",
            [
                'system_instruction' => [
                    'parts' => [['text' => $this->systemInstruction($boardName, $lists)]],
                ],
                'contents' => $this->toContents($messages),
                'tools' => [['function_declarations' => $this->toolDeclarations()]],
            ]
        );

        if ($response->failed()) {
            throw new GeminiApiException("Gemini API request failed with status {$response->status()}.");
        }

        $parts = $response->json('candidates.0.content.parts');

        if (! is_array($parts)) {
            throw new GeminiApiException('Gemini API returned an unexpected response shape.');
        }

        foreach ($parts as $part) {
            if (isset($part['functionCall']['name'])) {
                return $this->toolActionFromFunctionCall($part['functionCall']);
            }
        }

        $text = collect($parts)->pluck('text')->filter()->implode("\n");

        if ($text === '') {
            throw new GeminiApiException('Gemini API returned an empty response.');
        }

        return ['content' => $text, 'tool_action' => null];
    }

    /**
     * @param  array<int, array{name: string, card_count: int}>  $lists
     */
    private function systemInstruction(string $boardName, array $lists): string
    {
        $listSummary = collect($lists)
            ->map(fn (array $list) => "- {$list['name']} ({$list['card_count']} card(s))")
            ->implode("\n");

        $listSummary = $listSummary === '' ? '(no lists yet)' : $listSummary;

        return <<<TEXT
            You are a project management assistant embedded in a Trello-style board called "{$boardName}".
            Current lists on this board:
            {$listSummary}

            Help the user brainstorm and plan the board's structure. When they ask you to add lists or cards,
            call the appropriate tool instead of just describing what to do. Keep replies short and practical.
            TEXT;
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array<int, array{role: string, parts: array<int, array{text: string}>}>
     */
    private function toContents(array $messages): array
    {
        return array_map(
            fn (array $message) => [
                'role' => $message['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $message['content']]],
            ],
            $messages
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function toolDeclarations(): array
    {
        return [
            [
                'name' => 'create_lists',
                'description' => 'Create one or more new lists (columns) on the board.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'names' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Names of the lists to create, in order.',
                        ],
                    ],
                    'required' => ['names'],
                ],
            ],
            [
                'name' => 'create_cards',
                'description' => 'Create one or more cards under a named list on the board.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'list_name' => [
                            'type' => 'string',
                            'description' => 'The name of the list to add cards to.',
                        ],
                        'card_names' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Names of the cards to create, in order.',
                        ],
                    ],
                    'required' => ['list_name', 'card_names'],
                ],
            ],
        ];
    }

    /**
     * @param  array{name: string, args: array<string, mixed>}  $functionCall
     * @return array{content: string, tool_action: array<string, mixed>}
     */
    private function toolActionFromFunctionCall(array $functionCall): array
    {
        if ($functionCall['name'] === 'create_lists') {
            $names = $functionCall['args']['names'] ?? [];

            return [
                'content' => 'I\'ll create these lists: '.implode(', ', $names),
                'tool_action' => ['type' => 'create_lists', 'names' => $names],
            ];
        }

        if ($functionCall['name'] === 'create_cards') {
            $listName = $functionCall['args']['list_name'] ?? '';
            $cardNames = $functionCall['args']['card_names'] ?? [];

            return [
                'content' => "I'll add these cards to \"{$listName}\": ".implode(', ', $cardNames),
                'tool_action' => ['type' => 'create_cards', 'list_name' => $listName, 'card_names' => $cardNames],
            ];
        }

        throw new GeminiApiException("Gemini called an unknown tool: {$functionCall['name']}");
    }
}
