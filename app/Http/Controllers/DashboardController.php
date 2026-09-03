<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\CardActivity;
use App\Services\CardActivityDescriber;
use App\Services\DashboardStatsService;
use Barryvdh\Snappy\Facades\SnappyPdf;
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
            'aiEnabled' => filled(config('services.gemini.key')),
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
                'description' => app(CardActivityDescriber::class)->describe($activity),
                'user_name' => $activity->user->name,
                'board_name' => $activity->card->boardList->board->name ?? null,
                'created_at' => $activity->created_at,
            ]),
            'scopeLabel' => $scopeLabel,
            'generatedAt' => now()->timezone('Asia/Kuala_Lumpur'),
        ])->download('dashboard-report-'.now()->format('Y-m-d').'.pdf');
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

        $statsData = app(DashboardStatsService::class)->build($cards);
        $stats = $statsData['stats'];
        $tasksByBoard = $statsData['tasksByBoard'];
        $workload = $statsData['workload'];

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
