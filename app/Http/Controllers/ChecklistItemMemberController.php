<?php

namespace App\Http\Controllers;

use App\Http\Requests\Checklists\StoreChecklistItemMemberRequest;
use App\Models\ChecklistItem;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ChecklistItemMemberController extends Controller
{
    public function store(StoreChecklistItemMemberRequest $request, ChecklistItem $checklistItem): RedirectResponse
    {
        $userId = $request->validated('user_id');
        $checklistItem->members()->syncWithoutDetaching([$userId]);

        if ($userId !== $request->user()->id) {
            $card = $checklistItem->checklist->card;

            Notification::create([
                'user_id' => $userId,
                'type' => 'checklist_item_assigned',
                'data' => [
                    'card_id' => $card->id,
                    'card_name' => $card->name,
                    'board_id' => $card->boardList->board_id,
                    'actor_name' => $request->user()->name,
                    'item_name' => $checklistItem->name,
                ],
            ]);
        }

        return back();
    }

    public function destroy(Request $request, ChecklistItem $checklistItem, User $user): RedirectResponse
    {
        Gate::authorize('update', $checklistItem->checklist->card->boardList->board);

        $checklistItem->members()->detach($user->id);

        return back();
    }
}
