<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cards\StoreCardMemberRequest;
use App\Models\Card;
use App\Models\CardActivity;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CardMemberController extends Controller
{
    public function store(StoreCardMemberRequest $request, Card $card): RedirectResponse
    {
        $userId = $request->validated('user_id');
        $member = User::find($userId);
        $card->members()->syncWithoutDetaching([$userId]);

        CardActivity::create([
            'card_id' => $card->id,
            'user_id' => $request->user()->id,
            'type' => 'member_added',
            'data' => ['member_name' => $member->name],
        ]);

        if ($userId !== $request->user()->id) {
            Notification::create([
                'user_id' => $userId,
                'type' => 'card_assigned',
                'data' => [
                    'card_id' => $card->id,
                    'card_name' => $card->name,
                    'board_id' => $card->boardList->board_id,
                    'actor_name' => $request->user()->name,
                ],
            ]);
        }

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
