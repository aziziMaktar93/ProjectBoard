<?php

use App\Http\Controllers\BoardController;
use App\Http\Controllers\BoardListController;
use App\Http\Controllers\CardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('boards', [BoardController::class, 'index'])->name('boards.index');
    Route::get('boards/archived', [BoardController::class, 'archived'])->name('boards.archived');
    Route::post('boards', [BoardController::class, 'store'])->name('boards.store');
    Route::get('boards/{board}', [BoardController::class, 'show'])->name('boards.show');
    Route::patch('boards/{board}', [BoardController::class, 'update'])->name('boards.update');
    Route::patch('boards/{board}/archive', [BoardController::class, 'archive'])->name('boards.archive');
    Route::patch('boards/{board}/restore', [BoardController::class, 'restore'])->name('boards.restore');
    Route::delete('boards/{board}', [BoardController::class, 'destroy'])->name('boards.destroy');

    Route::post('boards/{board}/lists', [BoardListController::class, 'store'])->name('board-lists.store');
    Route::patch('boards/{board}/lists/reorder', [BoardListController::class, 'reorder'])->name('board-lists.reorder');
    Route::patch('lists/{boardList}', [BoardListController::class, 'update'])->name('board-lists.update');
    Route::patch('lists/{boardList}/archive', [BoardListController::class, 'archive'])->name('board-lists.archive');
    Route::patch('lists/{boardList}/restore', [BoardListController::class, 'restore'])->name('board-lists.restore');
    Route::delete('lists/{boardList}', [BoardListController::class, 'destroy'])->name('board-lists.destroy');

    Route::post('lists/{boardList}/cards', [CardController::class, 'store'])->name('cards.store');
    Route::patch('boards/{board}/cards/reorder', [CardController::class, 'reorder'])->name('cards.reorder');
    Route::patch('cards/{card}', [CardController::class, 'update'])->name('cards.update');
    Route::patch('cards/{card}/archive', [CardController::class, 'archive'])->name('cards.archive');
    Route::patch('cards/{card}/restore', [CardController::class, 'restore'])->name('cards.restore');
    Route::delete('cards/{card}', [CardController::class, 'destroy'])->name('cards.destroy');
});
