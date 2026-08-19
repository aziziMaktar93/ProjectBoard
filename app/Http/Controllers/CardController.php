<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cards\ReorderCardsRequest;
use App\Http\Requests\Cards\StoreCardRequest;
use App\Http\Requests\Cards\UpdateCardRequest;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CardController extends Controller
{
    public function store(StoreCardRequest $request, BoardList $boardList): RedirectResponse
    {
        $position = ($boardList->cards()->max('position') ?? -1) + 1;

        $boardList->cards()->create([
            ...$request->validated(),
            'position' => $position,
        ]);

        return back();
    }

    public function update(UpdateCardRequest $request, Card $card): RedirectResponse
    {
        $card->update($request->validated());

        return back();
    }

    public function reorder(ReorderCardsRequest $request, Board $board): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data) {
            foreach ($data['target_ordered_ids'] as $position => $id) {
                Card::where('id', $id)->update([
                    'board_list_id' => $data['target_list_id'],
                    'position' => $position,
                ]);
            }

            foreach ($data['source_ordered_ids'] ?? [] as $position => $id) {
                Card::where('id', $id)->update([
                    'board_list_id' => $data['source_list_id'],
                    'position' => $position,
                ]);
            }
        });

        return back();
    }

    public function archive(Request $request, Card $card): RedirectResponse
    {
        Gate::authorize('update', $card->boardList->board);

        $card->update(['archived_at' => now()]);

        return back();
    }

    public function restore(Request $request, Card $card): RedirectResponse
    {
        Gate::authorize('update', $card->boardList->board);

        $card->update(['archived_at' => null]);

        return back();
    }

    public function destroy(Request $request, Card $card): RedirectResponse
    {
        Gate::authorize('delete', $card->boardList->board);

        abort_if($card->archived_at === null, 422, 'Only archived cards can be permanently deleted.');

        $card->delete();

        return back();
    }
}
