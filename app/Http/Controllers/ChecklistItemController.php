<?php

namespace App\Http\Controllers;

use App\Http\Requests\Checklists\StoreChecklistItemRequest;
use App\Http\Requests\Checklists\UpdateChecklistItemRequest;
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
        $checklistItem->update($request->validated());

        return back();
    }

    public function destroy(Request $request, ChecklistItem $checklistItem): RedirectResponse
    {
        Gate::authorize('update', $checklistItem->checklist->card->boardList->board);

        $checklistItem->delete();

        return back();
    }
}
