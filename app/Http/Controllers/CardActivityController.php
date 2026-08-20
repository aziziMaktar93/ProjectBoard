<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cards\StoreCardActivityCommentRequest;
use App\Models\Card;
use Illuminate\Http\RedirectResponse;

class CardActivityController extends Controller
{
    public function store(StoreCardActivityCommentRequest $request, Card $card): RedirectResponse
    {
        $card->activities()->create([
            'user_id' => $request->user()->id,
            'type' => 'comment',
            'body' => $request->validated('body'),
        ]);

        return back();
    }
}
