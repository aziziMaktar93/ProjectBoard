<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\Card;
use App\Models\ChecklistItem;
use Barryvdh\Snappy\Facades\SnappyPdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

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
            ->with('checklist.card.boardList.board')
            ->get();

        $onTime = $items->filter(fn (ChecklistItem $item) => $item->completed_at->toDateString() <= $item->due_date);
        $late = $items->filter(fn (ChecklistItem $item) => $item->completed_at->toDateString() > $item->due_date);

        $lateDetails = $late->map(fn (ChecklistItem $item) => [
            'item_name' => $item->name,
            'checklist_name' => $item->checklist->name,
            'board_name' => $item->checklist->card->boardList->board->name,
            'due_date' => $item->due_date,
            'completed_at' => $item->completed_at,
            'days_late' => Carbon::parse($item->due_date)->diffInDays($item->completed_at->toDateString()),
        ])->sortByDesc('days_late')->values();

        return SnappyPdf::loadView('reports.on-time-completion', [
            'scopeLabel' => $scope['scopeLabel'],
            'totalCompleted' => $items->count(),
            'onTimeCount' => $onTime->count(),
            'lateCount' => $late->count(),
            'onTimePercent' => $items->isEmpty() ? null : (int) round($onTime->count() / $items->count() * 100),
            'lateDetails' => $lateDetails,
            'generatedAt' => now(),
        ])->download('on-time-completion-report-'.now()->format('Y-m-d').'.pdf');
    }

    public function memberPerformance(Request $request): HttpResponse
    {
        $scope = $this->resolveScope($request);

        $cards = Card::query()
            ->whereHas('boardList', fn ($query) => $query->whereIn('board_id', $scope['boardIds'])->whereNull('archived_at'))
            ->whereNull('archived_at')
            ->with(['checklists.items.members', 'members'])
            ->get();

        $today = now()->toDateString();
        $memberStats = [];

        foreach ($cards as $card) {
            $items = $card->checklists->flatMap(fn ($checklist) => $checklist->items);
            $cardComplete = $items->isNotEmpty() && $items->every(fn ($item) => $item->is_checked);

            foreach ($card->members as $member) {
                $memberStats[$member->id] ??= ['user' => $member, 'completed' => 0, 'overdue' => 0, 'lateDays' => []];

                if ($cardComplete) {
                    $memberStats[$member->id]['completed']++;
                } elseif ($card->due_date && $card->due_date < $today) {
                    $memberStats[$member->id]['overdue']++;
                }
            }

            foreach ($items as $item) {
                foreach ($item->members as $member) {
                    $memberStats[$member->id] ??= ['user' => $member, 'completed' => 0, 'overdue' => 0, 'lateDays' => []];

                    if ($item->is_checked) {
                        $memberStats[$member->id]['completed']++;

                        if ($item->due_date && $item->completed_at && $item->completed_at->toDateString() > $item->due_date) {
                            $memberStats[$member->id]['lateDays'][] = Carbon::parse($item->due_date)->diffInDays($item->completed_at->toDateString());
                        }
                    } elseif ($item->due_date && $item->due_date < $today) {
                        $memberStats[$member->id]['overdue']++;
                    }
                }
            }
        }

        $rows = collect($memberStats)->map(fn (array $stat) => [
            'user' => $stat['user'],
            'completed' => $stat['completed'],
            'overdue' => $stat['overdue'],
            'avg_days_late' => count($stat['lateDays']) ? round(array_sum($stat['lateDays']) / count($stat['lateDays']), 1) : null,
        ])->sortByDesc('completed')->values();

        return SnappyPdf::loadView('reports.member-performance', [
            'scopeLabel' => $scope['scopeLabel'],
            'rows' => $rows,
            'generatedAt' => now(),
        ])->download('member-performance-report-'.now()->format('Y-m-d').'.pdf');
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
