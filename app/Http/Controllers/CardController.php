<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cards\ReorderCardsRequest;
use App\Http\Requests\Cards\StoreCardRequest;
use App\Http\Requests\Cards\UpdateCardRequest;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\CardActivity;
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
        $validated = $request->validated();

        if (array_key_exists('due_date', $validated) && $validated['due_date'] !== $card->due_date) {
            CardActivity::create([
                'card_id' => $card->id,
                'user_id' => $request->user()->id,
                'type' => $validated['due_date'] === null ? 'due_date_removed' : 'due_date_changed',
                'data' => ['due_date' => $validated['due_date']],
            ]);
        }

        $card->update($validated);

        return back();
    }

    public function duplicate(Request $request, Card $card): RedirectResponse
    {
        Gate::authorize('update', $card->boardList->board);

        DB::transaction(function () use ($card) {
            $boardList = $card->boardList;

            $boardList->cards()
                ->whereNull('archived_at')
                ->where('position', '>', $card->position)
                ->increment('position');

            $newCard = $boardList->cards()->create([
                'name' => "{$card->name} (copy)",
                'description' => $card->description,
                'color' => $card->color,
                'due_date' => $card->due_date,
                'position' => $card->position + 1,
            ]);

            foreach ($card->checklists()->orderBy('position')->get() as $checklist) {
                $newChecklist = $newCard->checklists()->create([
                    'name' => $checklist->name,
                    'position' => $checklist->position,
                ]);

                foreach ($checklist->items()->orderBy('position')->get() as $item) {
                    $newChecklist->items()->create([
                        'name' => $item->name,
                        'is_checked' => $item->is_checked,
                        'position' => $item->position,
                    ]);
                }
            }
        });

        return back();
    }

    public function reorder(ReorderCardsRequest $request, Board $board): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $request) {
            $movedCardId = null;
            $fromListName = null;

            if (! empty($data['source_list_id'])) {
                $movedCard = Card::whereIn('id', $data['target_ordered_ids'])
                    ->where('board_list_id', $data['source_list_id'])
                    ->first();

                if ($movedCard) {
                    $movedCardId = $movedCard->id;
                    $fromListName = $movedCard->boardList->name;
                }
            }

            foreach ($data['target_ordered_ids'] as $position => $id) {
                Card::where('id', $id)->update([
                    'board_list_id' => $data['target_list_id'],
                    'position' => $position,
                ]);
            }

            if (! empty($data['source_list_id'])) {
                foreach ($data['source_ordered_ids'] ?? [] as $position => $id) {
                    Card::where('id', $id)->update([
                        'board_list_id' => $data['source_list_id'],
                        'position' => $position,
                    ]);
                }
            }

            if ($movedCardId) {
                CardActivity::create([
                    'card_id' => $movedCardId,
                    'user_id' => $request->user()->id,
                    'type' => 'moved',
                    'data' => [
                        'from_list' => $fromListName,
                        'to_list' => BoardList::find($data['target_list_id'])->name,
                    ],
                ]);
            }
        });

        return back();
    }

    public function archive(Request $request, Card $card): RedirectResponse
    {
        Gate::authorize('update', $card->boardList->board);

        $card->update(['archived_at' => now()]);

        CardActivity::create([
            'card_id' => $card->id,
            'user_id' => $request->user()->id,
            'type' => 'archived',
        ]);

        return back();
    }

    public function restore(Request $request, Card $card): RedirectResponse
    {
        Gate::authorize('update', $card->boardList->board);

        $card->update(['archived_at' => null]);

        CardActivity::create([
            'card_id' => $card->id,
            'user_id' => $request->user()->id,
            'type' => 'restored',
        ]);

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
