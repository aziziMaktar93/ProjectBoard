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
}
