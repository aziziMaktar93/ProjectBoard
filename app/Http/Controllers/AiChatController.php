<?php

namespace App\Http\Controllers;

use App\Exceptions\GeminiApiException;
use App\Http\Requests\AiChat\StoreAiMessageRequest;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Board;
use App\Services\GeminiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class AiChatController extends Controller
{
    public function show(Request $request, Board $board): JsonResponse
    {
        Gate::authorize('view', $board);

        $conversation = AiConversation::firstOrCreate([
            'board_id' => $board->id,
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'messages' => $conversation->messages,
        ]);
    }

    public function sendMessage(StoreAiMessageRequest $request, Board $board): JsonResponse
    {
        $conversation = AiConversation::firstOrCreate([
            'board_id' => $board->id,
            'user_id' => $request->user()->id,
        ]);

        $userMessage = $conversation->messages()->create([
            'role' => 'user',
            'content' => $request->validated('content'),
        ]);

        $lists = $board->lists()
            ->whereNull('archived_at')
            ->withCount(['cards' => fn ($query) => $query->whereNull('archived_at')])
            ->orderBy('position')
            ->get()
            ->map(fn ($list) => ['name' => $list->name, 'card_count' => $list->cards_count])
            ->all();

        $history = $conversation->messages()
            ->get(['role', 'content'])
            ->map(fn (AiMessage $message) => ['role' => $message->role, 'content' => $message->content])
            ->all();

        try {
            $result = app(GeminiClient::class)->reply($board->name, $lists, $history);
        } catch (GeminiApiException $exception) {
            report($exception);

            return response()->json([
                'message' => $userMessage,
                'error' => "AI couldn't respond, try again.",
            ], 502);
        }

        $assistantMessage = $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $result['content'],
            'tool_action' => $result['tool_action'],
        ]);

        return response()->json([
            'message' => $userMessage,
            'reply' => $assistantMessage,
        ]);
    }

    public function applyAction(Request $request, Board $board, AiMessage $message): JsonResponse
    {
        Gate::authorize('update', $board);

        abort_unless($message->conversation->board_id === $board->id, 404);
        abort_unless($message->conversation->user_id === $request->user()->id, 404);

        if ($message->applied_at !== null) {
            return response()->json(['error' => 'This suggestion was already applied.'], 422);
        }

        $action = $message->tool_action;

        if (! is_array($action) || ! isset($action['type'])) {
            return response()->json(['error' => 'This message has no action to apply.'], 422);
        }

        if ($action['type'] === 'create_lists') {
            $names = array_values(array_filter($action['names'] ?? [], fn ($name) => trim((string) $name) !== ''));

            if ($names === []) {
                return response()->json(['error' => 'No list names to create.'], 422);
            }

            $validator = Validator::make(['names' => $names], [
                'names' => ['required', 'array', 'max:20'],
                'names.*' => ['required', 'string', 'max:255'],
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => 'One or more list names are invalid.'], 422);
            }

            $position = ($board->lists()->max('position') ?? -1) + 1;

            foreach ($names as $name) {
                $board->lists()->create(['name' => $name, 'position' => $position++]);
            }
        } elseif ($action['type'] === 'create_cards') {
            $list = $board->lists()
                ->whereNull('archived_at')
                ->whereRaw('LOWER(name) = ?', [mb_strtolower((string) ($action['list_name'] ?? ''))])
                ->first();

            if (! $list) {
                return response()->json(['error' => "No list named \"{$action['list_name']}\" found on this board."], 422);
            }

            $cardNames = array_values(array_filter($action['card_names'] ?? [], fn ($name) => trim((string) $name) !== ''));

            if ($cardNames === []) {
                return response()->json(['error' => 'No card names to create.'], 422);
            }

            $validator = Validator::make(['card_names' => $cardNames], [
                'card_names' => ['required', 'array', 'max:20'],
                'card_names.*' => ['required', 'string', 'max:255'],
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => 'One or more card names are invalid.'], 422);
            }

            $position = ($list->cards()->max('position') ?? -1) + 1;

            foreach ($cardNames as $name) {
                $list->cards()->create(['name' => $name, 'position' => $position++]);
            }
        } else {
            return response()->json(['error' => "Unknown action type \"{$action['type']}\"."], 422);
        }

        $message->update(['applied_at' => now()]);

        return response()->json(['success' => true]);
    }
}
