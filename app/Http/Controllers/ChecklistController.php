<?php

namespace App\Http\Controllers;

use App\Http\Requests\Checklists\StoreChecklistRequest;
use App\Http\Requests\Checklists\UpdateChecklistRequest;
use App\Models\Card;
use App\Models\Checklist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ChecklistController extends Controller
{
    public function store(StoreChecklistRequest $request, Card $card): RedirectResponse
    {
        $position = ($card->checklists()->max('position') ?? -1) + 1;

        $card->checklists()->create([
            'name' => $request->validated('name'),
            'position' => $position,
        ]);

        return back();
    }

    public function update(UpdateChecklistRequest $request, Checklist $checklist): RedirectResponse
    {
        $checklist->update($request->validated());

        return back();
    }

    public function duplicate(Request $request, Checklist $checklist): RedirectResponse
    {
        Gate::authorize('update', $checklist->card->boardList->board);

        DB::transaction(function () use ($checklist) {
            $card = $checklist->card;

            $card->checklists()
                ->where('position', '>', $checklist->position)
                ->increment('position');

            $newChecklist = $card->checklists()->create([
                'name' => "{$checklist->name} (copy)",
                'position' => $checklist->position + 1,
            ]);

            foreach ($checklist->items()->orderBy('position')->get() as $item) {
                $newChecklist->items()->create([
                    'name' => $item->name,
                    'is_checked' => $item->is_checked,
                    'position' => $item->position,
                ]);
            }
        });

        return back();
    }

    public function destroy(Request $request, Checklist $checklist): RedirectResponse
    {
        Gate::authorize('update', $checklist->card->boardList->board);

        $checklist->delete();

        return back();
    }
}
