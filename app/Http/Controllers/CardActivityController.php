<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cards\StoreCardActivityCommentRequest;
use App\Models\Card;
use App\Models\Notification;
use Illuminate\Http\RedirectResponse;

class CardActivityController extends Controller
{
    public function store(StoreCardActivityCommentRequest $request, Card $card): RedirectResponse
    {
        $body = $request->validated('body');

        $card->activities()->create([
            'user_id' => $request->user()->id,
            'type' => 'comment',
            'body' => $body,
        ]);

        $this->notifyMentionedMembers($card, $body, $request->user()->id, $request->user()->name);

        return back();
    }

    private function notifyMentionedMembers(Card $card, string $body, int $authorId, string $authorName): void
    {
        $board = $card->boardList->board;

        foreach ($board->members as $member) {
            if ($member->id === $authorId || ! str_contains($body, '@'.$member->name)) {
                continue;
            }

            Notification::create([
                'user_id' => $member->id,
                'type' => 'mention',
                'data' => [
                    'card_id' => $card->id,
                    'card_name' => $card->name,
                    'board_id' => $board->id,
                    'actor_name' => $authorName,
                ],
            ]);
        }
    }
}
