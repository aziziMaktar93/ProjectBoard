<?php

namespace App\Http\Controllers;

use App\Http\Requests\BoardChat\StoreBoardMessageRequest;
use App\Models\Board;
use App\Models\BoardMessage;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BoardChatController extends Controller
{
    public function index(Request $request, Board $board): JsonResponse
    {
        Gate::authorize('view', $board);

        $messages = $board->messages()->with('user:id,name')->oldest()->get();

        $board->members()->updateExistingPivot($request->user()->id, ['chat_last_read_at' => now()]);

        return response()->json(['messages' => $messages]);
    }

    public function store(StoreBoardMessageRequest $request, Board $board): JsonResponse
    {
        $message = $board->messages()->create([
            'user_id' => $request->user()->id,
            'content' => $request->validated('content'),
        ]);

        $this->notifyMentionedMembers($board, $message, $request->user());

        return response()->json(['message' => $message->load('user:id,name')]);
    }

    public function destroy(Request $request, Board $board, BoardMessage $message): JsonResponse
    {
        Gate::authorize('view', $board);

        abort_unless($message->board_id === $board->id, 404);
        abort_unless($message->user_id === $request->user()->id, 403);

        $message->delete();

        return response()->json(['success' => true]);
    }

    private function notifyMentionedMembers(Board $board, BoardMessage $message, User $author): void
    {
        foreach ($board->members as $member) {
            if ($member->id === $author->id || ! str_contains($message->content, '@'.$member->name)) {
                continue;
            }

            Notification::create([
                'user_id' => $member->id,
                'type' => 'board_message_mention',
                'data' => [
                    'board_id' => $board->id,
                    'board_name' => $board->name,
                    'actor_name' => $author->name,
                    'message_preview' => str($message->content)->limit(80)->toString(),
                ],
            ]);
        }
    }
}
