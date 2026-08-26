<?php

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\User;

test('search returns matching boards and cards the user can see', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create(['name' => 'Marketing Launch']);
    $list = BoardList::factory()->for($board)->create();
    Card::factory()->for($list)->create(['name' => 'Launch checklist']);
    Card::factory()->for($list)->create(['name' => 'Unrelated card']);

    $response = $this->actingAs($user)->getJson('/search?q=launch');

    $response->assertOk();
    $response->assertJson(
        fn ($json) => $json
            ->has('boards', 1)
            ->where('boards.0.name', 'Marketing Launch')
            ->has('cards', 1)
            ->where('cards.0.name', 'Launch checklist')
    );
});

test('search excludes boards and cards the user is not a member of', function () {
    $user = User::factory()->create();
    $otherBoard = Board::factory()->create(['name' => 'Launch Secrets']);
    $otherList = BoardList::factory()->for($otherBoard)->create();
    Card::factory()->for($otherList)->create(['name' => 'Launch plan']);

    $response = $this->actingAs($user)->getJson('/search?q=launch');

    $response->assertOk();
    $response->assertJson(fn ($json) => $json->has('boards', 0)->has('cards', 0));
});

test('search requires at least two characters', function () {
    $user = User::factory()->create();
    Board::factory()->for($user)->create(['name' => 'A']);

    $response = $this->actingAs($user)->getJson('/search?q=a');

    $response->assertOk();
    $response->assertJson(['boards' => [], 'cards' => []]);
});

test('search excludes archived boards and cards', function () {
    $user = User::factory()->create();
    $board = Board::factory()->for($user)->create(['name' => 'Launch Archived Board', 'archived_at' => now()]);
    $activeBoard = Board::factory()->for($user)->create(['name' => 'Launch Active Board']);
    $list = BoardList::factory()->for($activeBoard)->create();
    Card::factory()->for($list)->create(['name' => 'Launch archived card', 'archived_at' => now()]);

    $response = $this->actingAs($user)->getJson('/search?q=launch');

    $response->assertOk();
    $response->assertJson(
        fn ($json) => $json
            ->has('boards', 1)
            ->where('boards.0.name', 'Launch Active Board')
            ->has('cards', 0)
    );
});

test('a guest cannot search', function () {
    $response = $this->get('/search?q=launch');

    $response->assertRedirect('/login');
});
