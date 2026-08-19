<?php

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\User;

test('the board show page includes the board\'s archived lists and cards', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create();
    $activeList = BoardList::factory()->for($board)->create();
    $archivedList = BoardList::factory()->for($board)->archived()->create(['name' => 'Archived List']);
    $archivedCard = Card::factory()->for($activeList)->archived()->create(['name' => 'Archived Card']);

    $response = $this->actingAs($user)->get("/boards/{$board->id}");

    $response->assertInertia(
        fn ($page) => $page
            ->component('boards/Show')
            ->has('archivedLists', 1)
            ->where('archivedLists.0.id', $archivedList->id)
            ->has('archivedCards', 1)
            ->where('archivedCards.0.id', $archivedCard->id)
    );
});
