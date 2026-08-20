<?php

namespace App\Http\Controllers;

use App\Http\Requests\BoardLists\ReorderBoardListsRequest;
use App\Http\Requests\BoardLists\StoreBoardListRequest;
use App\Http\Requests\BoardLists\UpdateBoardListRequest;
use App\Models\Board;
use App\Models\BoardList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class BoardListController extends Controller
{
    public function store(StoreBoardListRequest $request, Board $board): RedirectResponse
    {
        $position = ($board->lists()->max('position') ?? -1) + 1;

        $board->lists()->create([
            ...$request->validated(),
            'position' => $position,
        ]);

        return back();
    }

    public function update(UpdateBoardListRequest $request, BoardList $boardList): RedirectResponse
    {
        $boardList->update($request->validated());

        return back();
    }

    public function reorder(ReorderBoardListsRequest $request, Board $board): RedirectResponse
    {
        DB::transaction(function () use ($request, $board) {
            foreach ($request->validated('ordered_ids') as $position => $id) {
                BoardList::where('id', $id)->where('board_id', $board->id)->update(['position' => $position]);
            }
        });

        return back();
    }

    public function duplicate(Request $request, BoardList $boardList): RedirectResponse
    {
        Gate::authorize('update', $boardList->board);

        DB::transaction(function () use ($boardList) {
            $board = $boardList->board;

            $board->lists()
                ->whereNull('archived_at')
                ->where('position', '>', $boardList->position)
                ->increment('position');

            $newList = $board->lists()->create([
                'name' => "{$boardList->name} (copy)",
                'color' => $boardList->color,
                'position' => $boardList->position + 1,
            ]);

            foreach ($boardList->cards()->whereNull('archived_at')->orderBy('position')->get() as $card) {
                $newCard = $newList->cards()->create([
                    'name' => $card->name,
                    'description' => $card->description,
                    'color' => $card->color,
                    'due_date' => $card->due_date,
                    'position' => $card->position,
                ]);

                $checklist = $card->checklists()->first();

                if ($checklist) {
                    $newChecklist = $newCard->checklists()->create(['position' => $checklist->position]);

                    foreach ($checklist->items()->orderBy('position')->get() as $item) {
                        $newChecklist->items()->create([
                            'name' => $item->name,
                            'is_checked' => $item->is_checked,
                            'position' => $item->position,
                        ]);
                    }
                }
            }
        });

        return back();
    }

    public function archive(Request $request, BoardList $boardList): RedirectResponse
    {
        Gate::authorize('update', $boardList->board);

        $boardList->update(['archived_at' => now()]);

        return back();
    }

    public function restore(Request $request, BoardList $boardList): RedirectResponse
    {
        Gate::authorize('update', $boardList->board);

        $boardList->update(['archived_at' => null]);

        return back();
    }

    public function destroy(Request $request, BoardList $boardList): RedirectResponse
    {
        Gate::authorize('delete', $boardList->board);

        abort_if($boardList->archived_at === null, 422, 'Only archived lists can be permanently deleted.');

        $boardList->delete();

        return back();
    }
}
