<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cards\StoreCardMemberRequest;
use App\Models\Card;
use App\Models\CardActivity;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CardMemberController extends Controller
{
    public function store(StoreCardMemberRequest $request, Card $card): RedirectResponse
    {
        $userId = $request->validated('user_id');
        $card->members()->syncWithoutDetaching([$userId]);

        CardActivity::create([
            'card_id' => $card->id,
            'user_id' => $request->user()->id,
            'type' => 'member_added',
            'data' => ['member_name' => User::find($userId)->name],
        ]);

        return back();
    }

    public function destroy(Request $request, Card $card, User $user): RedirectResponse
    {
        Gate::authorize('update', $card->boardList->board);

        $card->members()->detach($user->id);

        CardActivity::create([
            'card_id' => $card->id,
            'user_id' => $request->user()->id,
            'type' => 'member_removed',
            'data' => ['member_name' => $user->name],
        ]);

        return back();
    }
}
