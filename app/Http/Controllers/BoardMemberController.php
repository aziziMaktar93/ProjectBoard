<?php

namespace App\Http\Controllers;

use App\Http\Requests\Boards\StoreBoardMemberRequest;
use App\Models\Board;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class BoardMemberController extends Controller
{
    public function store(StoreBoardMemberRequest $request, Board $board): RedirectResponse
    {
        $board->members()->syncWithoutDetaching([$request->validated('user_id')]);

        return back();
    }

    public function destroy(Request $request, Board $board, User $user): RedirectResponse
    {
        Gate::authorize('update', $board);

        abort_if($user->id === $board->user_id, 422, 'The board creator cannot be removed.');

        DB::transaction(function () use ($board, $user) {
            DB::table('card_user')
                ->whereIn('card_id', function ($query) use ($board) {
                    $query->select('cards.id')
                        ->from('cards')
                        ->join('board_lists', 'board_lists.id', '=', 'cards.board_list_id')
                        ->where('board_lists.board_id', $board->id);
                })
                ->where('user_id', $user->id)
                ->delete();

            $board->members()->detach($user->id);
        });

        return back();
    }
}
