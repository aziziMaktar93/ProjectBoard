<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\Card;
use App\Models\CardActivity;
use App\Models\ChecklistItem;
use App\Services\CardActivityDescriber;
use Barryvdh\Snappy\Facades\SnappyPdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $allBoardIds = $user->boardMemberships()->pluck('boards.id');

        return Inertia::render('Reports', [
            'workspaces' => $user->workspaces()->orderBy('name')->get(['workspaces.id', 'workspaces.name']),
            'boards' => Board::whereIn('id', $allBoardIds)->orderBy('name')->get(['id', 'name', 'workspace_id']),
        ]);
    }

    public function onTimeCompletion(Request $request): HttpResponse
    {
        $scope = $this->resolveScope($request);

        $items = ChecklistItem::query()
            ->whereHas('checklist.card', fn ($query) => $query->whereNull('archived_at'))
            ->whereHas('checklist.card.boardList', fn ($query) => $query->whereIn('board_id', $scope['boardIds'])->whereNull('archived_at'))
            ->whereNotNull('due_date')
            ->whereNotNull('completed_at')
            ->with(['checklist.card.boardList.board.workspace', 'members'])
            ->get();

        $onTime = $items->filter(fn (ChecklistItem $item) => $item->completed_at->toDateString() <= $item->due_date);
        $late = $items->filter(fn (ChecklistItem $item) => $item->completed_at->toDateString() > $item->due_date);

        $mapItem = fn (ChecklistItem $item) => [
            'item_name' => $item->name,
            'checklist_name' => $item->checklist->name,
            'workspace_name' => $item->checklist->card->boardList->board->workspace->name,
            'board_name' => $item->checklist->card->boardList->board->name,
            'assignees' => $item->members->pluck('name')->implode(', '),
            'due_date' => $item->due_date,
            'completed_at' => $item->completed_at,
        ];

        $lateDetails = $late->map(fn (ChecklistItem $item) => [
            ...$mapItem($item),
            'days_late' => Carbon::parse($item->due_date)->diffInDays($item->completed_at->toDateString()),
        ])->sortByDesc('days_late')->values();

        $onTimeDetails = $onTime->map($mapItem)->sortByDesc('completed_at')->values();

        return SnappyPdf::loadView('reports.on-time-completion', [
            'scopeLabel' => $scope['scopeLabel'],
            'totalCompleted' => $items->count(),
            'onTimeCount' => $onTime->count(),
            'lateCount' => $late->count(),
            'onTimePercent' => $items->isEmpty() ? null : (int) round($onTime->count() / $items->count() * 100),
            'lateDetails' => $lateDetails,
            'onTimeDetails' => $onTimeDetails,
            'generatedAt' => now()->timezone('Asia/Kuala_Lumpur'),
        ])->download('on-time-completion-report-'.now()->format('Y-m-d').'.pdf');
    }

    public function memberPerformance(Request $request): HttpResponse
    {
        $scope = $this->resolveScope($request);

        $cards = Card::query()
            ->whereHas('boardList', fn ($query) => $query->whereIn('board_id', $scope['boardIds'])->whereNull('archived_at'))
            ->whereNull('archived_at')
            ->with(['checklists.items.members', 'members', 'boardList.board.workspace'])
            ->get();

        $today = now()->toDateString();
        $memberStats = [];

        foreach ($cards as $card) {
            $items = $card->checklists->flatMap(fn ($checklist) => $checklist->items);
            $cardComplete = $items->isNotEmpty() && $items->every(fn ($item) => $item->is_checked);
            $cardStatus = $cardComplete ? 'Done' : ($card->due_date && $card->due_date < $today ? 'Overdue' : 'Pending');

            foreach ($card->members as $member) {
                $memberStats[$member->id] ??= ['user' => $member, 'lateDays' => [], 'tasks' => []];

                $memberStats[$member->id]['tasks'][] = [
                    'name' => $card->name,
                    'type' => 'Card',
                    'workspace_name' => $card->boardList->board->workspace->name,
                    'board_name' => $card->boardList->board->name,
                    'due_date' => $card->due_date,
                    'completed_at' => null,
                    'status' => $cardStatus,
                ];
            }

            foreach ($items as $item) {
                $itemStatus = $item->is_checked ? 'Done' : ($item->due_date && $item->due_date < $today ? 'Overdue' : 'Pending');

                foreach ($item->members as $member) {
                    $memberStats[$member->id] ??= ['user' => $member, 'lateDays' => [], 'tasks' => []];

                    if ($item->is_checked && $item->due_date && $item->completed_at && $item->completed_at->toDateString() > $item->due_date) {
                        $memberStats[$member->id]['lateDays'][] = Carbon::parse($item->due_date)->diffInDays($item->completed_at->toDateString());
                    }

                    $memberStats[$member->id]['tasks'][] = [
                        'name' => $item->name,
                        'type' => 'Checklist Item',
                        'workspace_name' => $card->boardList->board->workspace->name,
                        'board_name' => $card->boardList->board->name,
                        'due_date' => $item->due_date,
                        'completed_at' => $item->completed_at,
                        'status' => $itemStatus,
                    ];
                }
            }
        }

        $statusOrder = ['Overdue' => 0, 'Pending' => 1, 'Done' => 2];

        $rows = collect($memberStats)->map(function (array $stat) use ($statusOrder) {
            $tasks = collect($stat['tasks']);
            $counts = $tasks->countBy('status');

            return [
                'user' => $stat['user'],
                'completed' => $counts->get('Done', 0),
                'pending' => $counts->get('Pending', 0),
                'overdue' => $counts->get('Overdue', 0),
                'avg_days_late' => count($stat['lateDays']) ? round(array_sum($stat['lateDays']) / count($stat['lateDays']), 1) : null,
                'tasks' => $tasks->sortBy(fn (array $task) => $statusOrder[$task['status']])->values(),
            ];
        })->sortByDesc('completed')->values();

        return SnappyPdf::loadView('reports.member-performance', [
            'scopeLabel' => $scope['scopeLabel'],
            'rows' => $rows,
            'generatedAt' => now()->timezone('Asia/Kuala_Lumpur'),
        ])->download('member-performance-report-'.now()->format('Y-m-d').'.pdf');
    }

    public function activityLog(Request $request): HttpResponse
    {
        $scope = $this->resolveScope($request);
        $activities = $this->activitiesInScope($scope['boardIds']);
        $describer = app(CardActivityDescriber::class);

        return SnappyPdf::loadView('reports.activity-log', [
            'scopeLabel' => $scope['scopeLabel'],
            'activities' => $activities,
            'describer' => $describer,
            'generatedAt' => now()->timezone('Asia/Kuala_Lumpur'),
        ])->download('activity-log-report-'.now()->format('Y-m-d').'.pdf');
    }

    public function activityLogCsv(Request $request): StreamedResponse
    {
        $scope = $this->resolveScope($request);
        $activities = $this->activitiesInScope($scope['boardIds']);
        $describer = app(CardActivityDescriber::class);

        return response()->streamDownload(function () use ($activities, $describer) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date', 'Workspace', 'Board', 'User', 'Activity']);

            foreach ($activities as $activity) {
                $board = $activity->card->boardList->board ?? null;

                fputcsv($handle, [
                    $activity->created_at->timezone('Asia/Kuala_Lumpur')->format('Y-m-d H:i:s'),
                    $this->sanitizeCsvCell($board?->workspace->name ?? ''),
                    $this->sanitizeCsvCell($board?->name ?? ''),
                    $this->sanitizeCsvCell($activity->user->name),
                    $this->sanitizeCsvCell($describer->describe($activity)),
                ]);
            }

            fclose($handle);
        }, 'activity-log-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Neutralize CSV formula injection by prefixing risky leading characters
     * with a single quote, per OWASP's CSV injection guidance.
     */
    private function sanitizeCsvCell(string $value): string
    {
        if (preg_match('/^[=+\-@\t\r]/', $value)) {
            return "'".$value;
        }

        return $value;
    }

    public function checklistTimeline(Request $request): HttpResponse
    {
        $scope = $this->resolveScope($request);
        $today = now()->toDateString();

        $items = ChecklistItem::query()
            ->whereHas('checklist.card', fn ($query) => $query->whereNull('archived_at'))
            ->whereHas('checklist.card.boardList', fn ($query) => $query->whereIn('board_id', $scope['boardIds'])->whereNull('archived_at'))
            ->where(fn ($query) => $query->whereNotNull('due_date')->orWhereNotNull('completed_at'))
            ->with(['checklist.card.boardList.board.workspace', 'members'])
            ->get();

        $grouped = $items
            ->groupBy(fn (ChecklistItem $item) => $item->checklist->card->boardList->board->workspace->name)
            ->map(fn ($workspaceItems) => $workspaceItems
                ->groupBy(fn (ChecklistItem $item) => $item->checklist->card->boardList->board->name)
                ->map(fn ($boardItems) => $boardItems
                    ->groupBy(fn (ChecklistItem $item) => $item->checklist->card->name)
                    ->map(fn ($cardItems) => $cardItems
                        ->groupBy(fn (ChecklistItem $item) => $item->checklist->name)
                        ->map(fn ($checklistItems) => $checklistItems->map(fn (ChecklistItem $item) => [
                            'name' => $item->name,
                            'due_date' => $item->due_date,
                            'completed_at' => $item->completed_at,
                            'assignees' => $item->members->pluck('name')->implode(', '),
                            'status' => $item->is_checked
                                ? 'Done'
                                : ($item->due_date && $item->due_date < $today ? 'Overdue' : 'Pending'),
                        ])))));

        return SnappyPdf::loadView('reports.checklist-timeline', [
            'scopeLabel' => $scope['scopeLabel'],
            'grouped' => $grouped,
            'generatedAt' => now()->timezone('Asia/Kuala_Lumpur'),
        ])->download('checklist-timeline-report-'.now()->format('Y-m-d').'.pdf');
    }

    public function progress(Request $request): HttpResponse
    {
        $scope = $this->resolveScope($request);

        $cards = Card::query()
            ->whereHas('boardList', fn ($query) => $query->whereIn('board_id', $scope['boardIds'])->whereNull('archived_at'))
            ->whereNull('archived_at')
            ->with(['checklists.items', 'boardList.board.workspace'])
            ->get();

        $cardProgress = fn (Card $card): array => (function ($items) use ($card) {
            $total = $items->count();
            $checked = $items->where('is_checked', true)->count();

            return [
                'name' => $card->name,
                'checked' => $checked,
                'total' => $total,
                'percent' => $total > 0 ? (int) round($checked / $total * 100) : null,
            ];
        })($card->checklists->flatMap(fn ($checklist) => $checklist->items));

        $grouped = $cards
            ->groupBy(fn (Card $card) => $card->boardList->board->workspace->name)
            ->map(function ($workspaceCards) use ($cardProgress) {
                $boards = $workspaceCards
                    ->groupBy(fn (Card $card) => $card->boardList->board->name)
                    ->map(function ($boardCards) use ($cardProgress) {
                        $items = $boardCards->flatMap(fn (Card $card) => $card->checklists->flatMap(fn ($checklist) => $checklist->items));
                        $total = $items->count();
                        $checked = $items->where('is_checked', true)->count();

                        return [
                            'percent' => $total > 0 ? (int) round($checked / $total * 100) : null,
                            'checked' => $checked,
                            'total' => $total,
                            'cards' => $boardCards->map($cardProgress)->values(),
                        ];
                    });

                $workspaceItems = $workspaceCards->flatMap(fn (Card $card) => $card->checklists->flatMap(fn ($checklist) => $checklist->items));
                $workspaceTotal = $workspaceItems->count();
                $workspaceChecked = $workspaceItems->where('is_checked', true)->count();

                return [
                    'percent' => $workspaceTotal > 0 ? (int) round($workspaceChecked / $workspaceTotal * 100) : null,
                    'checked' => $workspaceChecked,
                    'total' => $workspaceTotal,
                    'boards' => $boards,
                ];
            });

        return SnappyPdf::loadView('reports.progress', [
            'scopeLabel' => $scope['scopeLabel'],
            'grouped' => $grouped,
            'generatedAt' => now()->timezone('Asia/Kuala_Lumpur'),
        ])->download('progress-report-'.now()->format('Y-m-d').'.pdf');
    }

    /**
     * @param  Collection<int, int>  $boardIds
     * @return Collection<int, CardActivity>
     */
    private function activitiesInScope(Collection $boardIds): Collection
    {
        return CardActivity::query()
            ->whereHas('card.boardList', fn ($query) => $query->whereIn('board_id', $boardIds)->whereNull('archived_at'))
            ->with(['user', 'card.boardList.board.workspace'])
            ->latest()
            ->limit(500)
            ->get();
    }

    /**
     * @return array{boardIds: Collection, allBoardIds: Collection, workspaces: Collection, boards: Collection, selectedBoardId: int|null, selectedWorkspaceId: int|null, scopeLabel: string}
     */
    private function resolveScope(Request $request): array
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

        $boards = Board::whereIn('id', $allBoardIds)->orderBy('name')->get(['id', 'name', 'workspace_id']);

        $scopeLabel = 'All boards';

        if ($selectedBoardId) {
            $scopeLabel = $boards->firstWhere('id', $selectedBoardId)->name ?? $scopeLabel;
        } elseif ($selectedWorkspaceId) {
            $scopeLabel = $workspaces->firstWhere('id', $selectedWorkspaceId)->name ?? $scopeLabel;
        }

        return [
            'boardIds' => $boardIds,
            'allBoardIds' => $allBoardIds,
            'workspaces' => $workspaces,
            'boards' => $boards,
            'selectedBoardId' => $selectedBoardId,
            'selectedWorkspaceId' => $selectedWorkspaceId,
            'scopeLabel' => $scopeLabel,
        ];
    }
}
