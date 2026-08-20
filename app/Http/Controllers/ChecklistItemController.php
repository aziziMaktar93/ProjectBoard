<?php

namespace App\Http\Controllers;

use App\Http\Requests\Checklists\StoreChecklistItemRequest;
use App\Http\Requests\Checklists\UpdateChecklistItemRequest;
use App\Models\CardActivity;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ChecklistItemController extends Controller
{
    public function store(StoreChecklistItemRequest $request, Checklist $checklist): RedirectResponse
    {
        $position = ($checklist->items()->max('position') ?? -1) + 1;

        $checklist->items()->create([
            ...$request->validated(),
            'position' => $position,
        ]);

        return back();
    }

    public function update(UpdateChecklistItemRequest $request, ChecklistItem $checklistItem): RedirectResponse
    {
        $validated = $request->validated();

        if (array_key_exists('is_checked', $validated) && $validated['is_checked'] !== $checklistItem->is_checked) {
            CardActivity::create([
                'card_id' => $checklistItem->checklist->card_id,
                'user_id' => $request->user()->id,
                'type' => $validated['is_checked'] ? 'checklist_item_completed' : 'checklist_item_uncompleted',
                'data' => ['item_name' => $checklistItem->name],
            ]);
        }

        $checklistItem->update($validated);

        return back();
    }

    public function destroy(Request $request, ChecklistItem $checklistItem): RedirectResponse
    {
        Gate::authorize('update', $checklistItem->checklist->card->boardList->board);

        $checklistItem->delete();

        return back();
    }
}
