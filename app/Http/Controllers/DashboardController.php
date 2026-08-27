<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\CardActivity;
use Barryvdh\Snappy\Facades\SnappyPdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $data = $this->buildReportData($request);

        return Inertia::render('Dashboard', [
            'stats' => $data['stats'],
            'tasksByBoard' => $data['tasksByBoard'],
            'tasksByList' => $data['tasksByList'],
            'workload' => $data['workload'],
            'recentActivity' => $data['recentActivity'],
            'hasBoards' => $data['allBoardIds']->isNotEmpty(),
            'workspaces' => $data['workspaces'],
            'boards' => $data['boards'],
            'filters' => [
                'workspace_id' => $data['selectedWorkspaceId'],
                'board_id' => $data['selectedBoardId'],
            ],
        ]);
    }

    public function report(Request $request): HttpResponse
    {
        $data = $this->buildReportData($request);

        $scopeLabel = 'All boards';

        if ($data['selectedBoardId']) {
            $scopeLabel = $data['boards']->firstWhere('id', $data['selectedBoardId'])->name ?? $scopeLabel;
        } elseif ($data['selectedWorkspaceId']) {
            $scopeLabel = $data['workspaces']->firstWhere('id', $data['selectedWorkspaceId'])->name ?? $scopeLabel;
        }

        return SnappyPdf::loadView('reports.dashboard', [
            'stats' => $data['stats'],
            'tasksByBoard' => $data['tasksByBoard'],
            'tasksByList' => $data['tasksByList'],
            'workload' => $data['workload'],
            'recentActivity' => $data['recentActivity']->map(fn (CardActivity $activity) => [
                'description' => $this->describeActivity($activity),
                'user_name' => $activity->user->name,
                'board_name' => $activity->card->boardList->board->name ?? null,
                'created_at' => $activity->created_at,
            ]),
            'scopeLabel' => $scopeLabel,
            'generatedAt' => now(),
        ])->download('dashboard-report-'.now()->format('Y-m-d').'.pdf');
    }

    private function describeActivity(CardActivity $activity): string
    {
        $cardName = $activity->card->name ?? 'a card';
        $data = $activity->data ?? [];

        return match ($activity->type) {
            'comment' => "commented on {$cardName}",
            'moved' => "moved {$cardName} from {$data['from_list']} to {$data['to_list']}",
            'checklist_item_completed' => "completed {$data['item_name']} on {$cardName}",
            'checklist_item_uncompleted' => "marked {$data['item_name']} incomplete on {$cardName}",
            'member_added' => "added {$data['member_name']} to {$cardName}",
            'member_removed' => "removed {$data['member_name']} from {$cardName}",
            'label_added' => "added the {$data['label_name']} label to {$cardName}",
            'label_removed' => "removed the {$data['label_name']} label from {$cardName}",
            'attachment_added' => "added {$data['attachment_name']} to {$cardName}",
            'attachment_removed' => "removed {$data['attachment_name']} from {$cardName}",
            'due_date_changed' => "set the due date on {$cardName} to ".Carbon::parse($data['due_date'])->format('M j, Y'),
            'due_date_removed' => "removed the due date from {$cardName}",
            'archived' => "archived {$cardName}",
            'restored' => "restored {$cardName}",
            default => "updated {$cardName}",
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function buildReportData(Request $request): array
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
            ->with(['boardList.board', 'checklists.items.members', 'members'])
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
            'overdue' => $cards->filter(fn (Card $card) => $card->due_date && $card->due_date < $today && ! $isCompleted($card))->count(),
            'dueSoon' => $cards->filter(fn (Card $card) => $card->due_date && $card->due_date >= $today && $card->due_date <= $weekAhead)->count(),
            'checklistProgress' => $allChecklistItems->isEmpty()
                ? null
                : (int) round($allChecklistItems->filter(fn ($item) => $item->is_checked)->count() / $allChecklistItems->count() * 100),
            'checklistItemsOverdue' => $allChecklistItems->filter(fn ($item) => $item->due_date && $item->due_date < $today && ! $item->is_checked)->count(),
            'checklistItemsDueSoon' => $allChecklistItems->filter(fn ($item) => $item->due_date && ! $item->is_checked && $item->due_date >= $today && $item->due_date <= $weekAhead)->count(),
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
            ->merge($allChecklistItems->flatMap(fn ($item) => $item->members))
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

        return [
            'stats' => $stats,
            'tasksByBoard' => $tasksByBoard,
            'tasksByList' => $tasksByList,
            'workload' => $workload,
            'recentActivity' => $recentActivity,
            'allBoardIds' => $allBoardIds,
            'workspaces' => $workspaces,
            'boards' => $boards,
            'selectedWorkspaceId' => $selectedWorkspaceId,
            'selectedBoardId' => $selectedBoardId,
        ];
    }
}
