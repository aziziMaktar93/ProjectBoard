<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cards\StoreCardLabelRequest;
use App\Models\Card;
use App\Models\CardActivity;
use App\Models\Label;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CardLabelController extends Controller
{
    public function store(StoreCardLabelRequest $request, Card $card): RedirectResponse
    {
        $labelId = $request->validated('label_id');
        $card->labels()->syncWithoutDetaching([$labelId]);

        CardActivity::create([
            'card_id' => $card->id,
            'user_id' => $request->user()->id,
            'type' => 'label_added',
            'data' => ['label_name' => Label::find($labelId)->name],
        ]);

        return back();
    }

    public function destroy(Request $request, Card $card, Label $label): RedirectResponse
    {
        Gate::authorize('update', $card->boardList->board);

        $card->labels()->detach($label->id);

        CardActivity::create([
            'card_id' => $card->id,
            'user_id' => $request->user()->id,
            'type' => 'label_removed',
            'data' => ['label_name' => $label->name],
        ]);

        return back();
    }
}
