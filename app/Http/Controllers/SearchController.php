<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\Card;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = trim((string) $request->string('q'));

        if (mb_strlen($query) < 2) {
            return response()->json(['boards' => [], 'cards' => []]);
        }

        $boardIds = $request->user()->boardMemberships()->pluck('boards.id');

        $boards = Board::query()
            ->whereIn('id', $boardIds)
            ->whereNull('archived_at')
            ->where('name', 'like', '%'.$query.'%')
            ->with('workspace:id,name')
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'workspace_id', 'name'])
            ->map(fn (Board $board) => [
                'id' => $board->id,
                'name' => $board->name,
                'workspace_name' => $board->workspace->name,
            ]);

        $cards = Card::query()
            ->whereHas('boardList', fn ($listQuery) => $listQuery->whereIn('board_id', $boardIds)->whereNull('archived_at'))
            ->whereNull('archived_at')
            ->where('name', 'like', '%'.$query.'%')
            ->with('boardList.board:id,name')
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'board_list_id', 'name'])
            ->map(fn (Card $card) => [
                'id' => $card->id,
                'name' => $card->name,
                'board_id' => $card->boardList->board_id,
                'board_name' => $card->boardList->board->name,
            ]);

        return response()->json(['boards' => $boards, 'cards' => $cards]);
    }
}
