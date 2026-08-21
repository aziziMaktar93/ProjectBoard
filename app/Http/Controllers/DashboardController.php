<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\CardActivity;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $allBoardIds = $user->boardMemberships()->pluck('boards.id');
        $workspaces = $user->workspaces()->orderBy('name')->get(['workspaces.id', 'workspaces.name']);
        $memberWorkspaceIds = $workspaces->pluck('id');

        $selectedBoardId = $request->integer('board_id') ?: null;
        $selectedWorkspaceId = $request->integer('workspace_id') ?: null;

        $boardIds = $allBoardIds;

        if ($selectedBoardId && $allBoardIds->contains($selectedBoardId)) {
            $boardIds = collect([$selectedBoardId]);
        } elseif ($selectedWorkspaceId && $memberWorkspaceIds->contains($selectedWorkspaceId)) {
            $boardIds = Board::whereIn('id', $allBoardIds)->where('workspace_id', $selectedWorkspaceId)->pluck('id');
        }

        $cards = Card::query()
            ->whereHas('boardList', fn ($query) => $query->whereIn('board_id', $boardIds)->whereNull('archived_at'))
            ->whereNull('archived_at')
            ->with(['boardList.board', 'checklists.items', 'members'])
            ->get();

        $today = now()->toDateString();
        $weekAhead = now()->addDays(7)->toDateString();

        $allChecklistItems = $cards->flatMap(fn (Card $card) => $card->checklists)->flatMap(fn ($checklist) => $checklist->items);

        $isCompleted = function (Card $card): bool {
            $items = $card->checklists->flatMap(fn ($checklist) => $checklist->items);

            return $items->isNotEmpty() && $items->every(fn ($item) => $item->is_checked);
        };

        $stats = [
            'total' => $cards->count(),
            'completed' => $cards->filter($isCompleted)->count(),
            'overdue' => $cards->filter(fn (Card $card) => $card->due_date && $card->due_date < $today)->count(),
            'dueSoon' => $cards->filter(fn (Card $card) => $card->due_date && $card->due_date >= $today && $card->due_date <= $weekAhead)->count(),
            'checklistProgress' => $allChecklistItems->isEmpty()
                ? null
                : (int) round($allChecklistItems->filter(fn ($item) => $item->is_checked)->count() / $allChecklistItems->count() * 100),
        ];

        $tasksByBoard = $cards
            ->groupBy(fn (Card $card) => $card->boardList->board->name)
            ->map(fn ($group) => $group->count())
            ->sortDesc()
            ->take(8)
            ->map(fn ($count, $name) => ['name' => $name, 'count' => $count])
            ->values();

        // Only meaningful once scoped to a single board — list names aren't
        // comparable across different boards, unlike the board/member
        // breakdowns above. Includes every active list, even ones with zero
        // cards, so the shape always matches the board's real columns.
        $tasksByList = null;

        if ($selectedBoardId && $allBoardIds->contains($selectedBoardId)) {
            $tasksByList = BoardList::where('board_id', $selectedBoardId)
                ->whereNull('archived_at')
                ->orderBy('position')
                ->get()
                ->map(fn (BoardList $list) => [
                    'name' => $list->name,
                    'count' => $cards->where('board_list_id', $list->id)->count(),
                ])
                ->values();
        }

        $workload = $cards
            ->flatMap(fn (Card $card) => $card->members)
            ->groupBy('id')
            ->map(fn ($group) => ['user' => $group->first(), 'count' => $group->count()])
            ->sortByDesc('count')
            ->take(8)
            ->values();

        $recentActivity = CardActivity::query()
            ->whereHas('card.boardList', fn ($query) => $query->whereIn('board_id', $boardIds)->whereNull('archived_at'))
            ->with(['user', 'card.boardList.board'])
            ->latest()
            ->limit(10)
            ->get();

        $boards = Board::whereIn('id', $allBoardIds)->orderBy('name')->get(['id', 'name', 'workspace_id']);

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'tasksByBoard' => $tasksByBoard,
            'tasksByList' => $tasksByList,
            'workload' => $workload,
            'recentActivity' => $recentActivity,
            'hasBoards' => $allBoardIds->isNotEmpty(),
            'workspaces' => $workspaces,
            'boards' => $boards,
            'filters' => [
                'workspace_id' => $selectedWorkspaceId,
                'board_id' => $selectedBoardId,
            ],
        ]);
    }
}
