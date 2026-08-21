<?php

use App\Http\Controllers\BoardController;
use App\Http\Controllers\BoardListController;
use App\Http\Controllers\BoardMemberController;
use App\Http\Controllers\CardActivityController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\CardMemberController;
use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\ChecklistItemController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('boards/{board}', [BoardController::class, 'show'])->name('boards.show');
    Route::patch('boards/{board}', [BoardController::class, 'update'])->name('boards.update');
    Route::patch('boards/{board}/archive', [BoardController::class, 'archive'])->name('boards.archive');
    Route::patch('boards/{board}/restore', [BoardController::class, 'restore'])->name('boards.restore');
    Route::delete('boards/{board}', [BoardController::class, 'destroy'])->name('boards.destroy');

    Route::post('boards/{board}/members', [BoardMemberController::class, 'store'])->name('board-members.store');
    Route::delete('boards/{board}/members/{user}', [BoardMemberController::class, 'destroy'])->name('board-members.destroy');

    Route::post('boards/{board}/lists', [BoardListController::class, 'store'])->name('board-lists.store');
    Route::patch('boards/{board}/lists/reorder', [BoardListController::class, 'reorder'])->name('board-lists.reorder');
    Route::patch('lists/{boardList}', [BoardListController::class, 'update'])->name('board-lists.update');
    Route::post('lists/{boardList}/duplicate', [BoardListController::class, 'duplicate'])->name('board-lists.duplicate');
    Route::patch('lists/{boardList}/archive', [BoardListController::class, 'archive'])->name('board-lists.archive');
    Route::patch('lists/{boardList}/restore', [BoardListController::class, 'restore'])->name('board-lists.restore');
    Route::delete('lists/{boardList}', [BoardListController::class, 'destroy'])->name('board-lists.destroy');

    Route::post('lists/{boardList}/cards', [CardController::class, 'store'])->name('cards.store');
    Route::patch('boards/{board}/cards/reorder', [CardController::class, 'reorder'])->name('cards.reorder');
    Route::patch('cards/{card}', [CardController::class, 'update'])->name('cards.update');
    Route::post('cards/{card}/duplicate', [CardController::class, 'duplicate'])->name('cards.duplicate');
    Route::patch('cards/{card}/archive', [CardController::class, 'archive'])->name('cards.archive');
    Route::patch('cards/{card}/restore', [CardController::class, 'restore'])->name('cards.restore');
    Route::delete('cards/{card}', [CardController::class, 'destroy'])->name('cards.destroy');

    Route::post('cards/{card}/checklists', [ChecklistController::class, 'store'])->name('checklists.store');
    Route::delete('checklists/{checklist}', [ChecklistController::class, 'destroy'])->name('checklists.destroy');

    Route::post('checklists/{checklist}/items', [ChecklistItemController::class, 'store'])->name('checklist-items.store');
    Route::patch('checklist-items/{checklistItem}', [ChecklistItemController::class, 'update'])->name('checklist-items.update');
    Route::delete('checklist-items/{checklistItem}', [ChecklistItemController::class, 'destroy'])->name('checklist-items.destroy');

    Route::post('cards/{card}/members', [CardMemberController::class, 'store'])->name('card-members.store');
    Route::delete('cards/{card}/members/{user}', [CardMemberController::class, 'destroy'])->name('card-members.destroy');

    Route::post('cards/{card}/activities', [CardActivityController::class, 'store'])->name('card-activities.store');
});
