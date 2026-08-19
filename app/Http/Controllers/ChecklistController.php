<?php

namespace App\Http\Controllers;

use App\Http\Requests\Checklists\StoreChecklistRequest;
use App\Models\Card;
use App\Models\Checklist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ChecklistController extends Controller
{
    public function store(StoreChecklistRequest $request, Card $card): RedirectResponse
    {
        abort_if($card->checklists()->exists(), 422, 'This card already has a checklist.');

        $card->checklists()->create(['position' => 0]);

        return back();
    }

    public function destroy(Request $request, Checklist $checklist): RedirectResponse
    {
        Gate::authorize('update', $checklist->card->boardList->board);

        $checklist->delete();

        return back();
    }
}
