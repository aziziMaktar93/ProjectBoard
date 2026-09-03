<?php

namespace App\Http\Controllers;

use App\Exceptions\GeminiApiException;
use App\Http\Requests\DashboardChat\StoreDashboardMessageRequest;
use App\Models\Card;
use App\Models\DashboardConversation;
use App\Models\DashboardMessage;
use App\Services\DashboardStatsService;
use App\Services\GeminiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DashboardChatController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $conversation = DashboardConversation::firstOrCreate([
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'messages' => $conversation->messages,
        ]);
    }

    public function sendMessage(StoreDashboardMessageRequest $request): JsonResponse
    {
        $user = $request->user();
        $conversation = DashboardConversation::firstOrCreate(['user_id' => $user->id]);

        $userMessage = $conversation->messages()->create([
            'role' => 'user',
            'content' => $request->validated('content'),
        ]);

        $boardIds = $user->boardMemberships()->pluck('boards.id');

        $cards = Card::query()
            ->whereHas('boardList', fn ($query) => $query->whereIn('board_id', $boardIds)->whereNull('archived_at'))
            ->whereNull('archived_at')
            ->with(['boardList.board', 'checklists.items.members', 'members'])
            ->get();

        $statsData = app(DashboardStatsService::class)->build($cards, null);
        $boards = $user->boardMemberships()->get(['boards.id', 'boards.name']);

        $systemInstruction = $this->buildSystemInstruction(
            $statsData['stats'],
            $statsData['tasksByBoard'],
            $statsData['workload'],
            $boards,
            $statsData['overdueCards'],
            $statsData['dueSoonCards'],
        );

        $history = $conversation->messages()
            ->get(['role', 'content'])
            ->map(fn (DashboardMessage $message) => ['role' => $message->role, 'content' => $message->content])
            ->all();

        try {
            $content = app(GeminiClient::class)->converse($systemInstruction, $history);
        } catch (GeminiApiException $exception) {
            report($exception);

            return response()->json([
                'message' => $userMessage,
                'error' => "AI couldn't respond, try again.",
            ], 502);
        }

        $assistantMessage = $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $content,
        ]);

        return response()->json([
            'message' => $userMessage,
            'reply' => $assistantMessage,
        ]);
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function buildSystemInstruction(array $stats, Collection $tasksByBoard, Collection $workload, Collection $boards, Collection $overdueCards, Collection $dueSoonCards): string
    {
        $statsSummary = "Total tasks: {$stats['total']}, Completed: {$stats['completed']}, "
            ."Overdue: {$stats['overdue']}, Due within 7 days: {$stats['dueSoon']}, "
            .'Checklist progress: '.($stats['checklistProgress'] !== null ? "{$stats['checklistProgress']}%" : 'n/a').', '
            ."Checklist items overdue: {$stats['checklistItemsOverdue']}, "
            ."Checklist items due soon: {$stats['checklistItemsDueSoon']}";

        $boardSummary = $tasksByBoard->isEmpty()
            ? '(no active tasks)'
            : $tasksByBoard->map(fn (array $board) => "- {$board['name']}: {$board['count']} task(s)")->implode("\n");

        $workloadSummary = $workload->isEmpty()
            ? '(no assigned members)'
            : $workload->map(fn (array $entry) => "- {$entry['user']->name}: {$entry['count']} task(s)")->implode("\n");

        $boardNames = $boards->pluck('name')->implode(', ');
        $boardNames = $boardNames === '' ? '(no boards)' : $boardNames;

        $overdueSummary = $overdueCards->isEmpty()
            ? '(none)'
            : $overdueCards->map(fn (array $card) => "- {$card['name']} ({$card['board']}, due {$card['due_date']})")->implode("\n");

        $dueSoonSummary = $dueSoonCards->isEmpty()
            ? '(none)'
            : $dueSoonCards->map(fn (array $card) => "- {$card['name']} ({$card['board']}, due {$card['due_date']})")->implode("\n");

        return <<<TEXT
            You are a helpful assistant embedded in the Dashboard of a Trello-style project management app called Trellow.
            Answer questions about the user's overall task progress using only the data below. Do not make up numbers not given here.

            Overall stats: {$statsSummary}

            Tasks by board:
            {$boardSummary}

            Workload by member:
            {$workloadSummary}

            Overdue tasks:
            {$overdueSummary}

            Tasks due within 7 days:
            {$dueSoonSummary}

            When you refer to a specific board by name, wrap it in double square brackets exactly like [[Board Name]], using
            only these exact board names: {$boardNames}. Do not invent board names. You cannot create, edit, or delete
            anything on the board — you can only answer questions and point the user at a board. Keep replies short and practical.

            Reply in the same language the user writes in. If the user writes in Malay, reply in Bahasa Malaysia (Malay),
            not Bahasa Indonesia — they are different languages.
            TEXT;
    }
}
