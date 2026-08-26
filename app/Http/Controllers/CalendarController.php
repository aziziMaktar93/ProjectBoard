<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\BoardEvent;
use App\Models\Card;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    public function index(Request $request): Response
    {
        $boardIds = $request->user()->boardMemberships()->pluck('boards.id');

        $workspaceIds = $request->user()->workspaces()->pluck('workspaces.id');
        $teammateIds = User::whereHas('workspaces', fn ($query) => $query->whereIn('workspaces.id', $workspaceIds))
            ->pluck('id');

        $cards = Card::query()
            ->whereHas('boardList', fn ($query) => $query->whereIn('board_id', $boardIds)->whereNull('archived_at'))
            ->whereNull('archived_at')
            ->whereNotNull('due_date')
            ->with(['boardList:id,board_id', 'checklists.items'])
            ->orderBy('due_date')
            ->get(['id', 'board_list_id', 'name', 'due_date', 'color'])
            ->map(fn (Card $card) => [
                'id' => $card->id,
                'name' => $card->name,
                'due_date' => $card->due_date,
                'color' => $card->color,
                'board_id' => $card->boardList->board_id,
                'is_completed' => $this->isCardCompleted($card),
            ]);

        $events = BoardEvent::where(fn ($query) => $query->whereIn('board_id', $boardIds)
            ->orWhere(fn ($query) => $query->whereNull('board_id')->whereIn('user_id', $teammateIds)))
            ->with('user:id,name')
            ->orderBy('start_date')
            ->get();

        $boards = Board::whereIn('id', $boardIds)
            ->with('workspace:id,name')
            ->orderBy('name')
            ->get(['id', 'workspace_id', 'name'])
            ->map(fn (Board $board) => [
                'id' => $board->id,
                'name' => $board->name,
                'workspace_name' => $board->workspace->name,
            ]);

        return Inertia::render('Calendar', [
            'cards' => $cards,
            'events' => $events,
            'boards' => $boards,
        ]);
    }

    private function isCardCompleted(Card $card): bool
    {
        $items = $card->checklists->flatMap(fn ($checklist) => $checklist->items);

        return $items->isNotEmpty() && $items->every(fn ($item) => $item->is_checked);
    }
}
