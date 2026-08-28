<?php

use App\Http\Controllers\AiChatController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\BoardEventController;
use App\Http\Controllers\BoardFavouriteController;
use App\Http\Controllers\BoardLabelController;
use App\Http\Controllers\BoardListController;
use App\Http\Controllers\BoardMemberController;
use App\Http\Controllers\CardActivityController;
use App\Http\Controllers\CardAttachmentController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\CardLabelController;
use App\Http\Controllers\CardMemberController;
use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\ChecklistItemController;
use App\Http\Controllers\ChecklistItemMemberController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('boards/{board}', [BoardController::class, 'show'])->name('boards.show');
    Route::get('boards/{board}/calendar', [BoardController::class, 'calendar'])->name('boards.calendar');
    Route::patch('boards/{board}', [BoardController::class, 'update'])->name('boards.update');
    Route::patch('boards/{board}/archive', [BoardController::class, 'archive'])->name('boards.archive');
    Route::patch('boards/{board}/restore', [BoardController::class, 'restore'])->name('boards.restore');
    Route::delete('boards/{board}', [BoardController::class, 'destroy'])->name('boards.destroy');
    Route::patch('boards/{board}/favourite', [BoardFavouriteController::class, 'toggle'])->name('boards.favourite');

    Route::get('boards/{board}/ai/conversation', [AiChatController::class, 'show'])->name('ai-chat.show');
    Route::post('boards/{board}/ai/messages', [AiChatController::class, 'sendMessage'])->name('ai-chat.messages.store')->middleware('throttle:20,1');
    Route::post('boards/{board}/ai/messages/{message}/apply', [AiChatController::class, 'applyAction'])->name('ai-chat.messages.apply');

    Route::post('boards/{board}/members', [BoardMemberController::class, 'store'])->name('board-members.store');
    Route::patch('boards/{board}/members/{user}/role', [BoardMemberController::class, 'updateRole'])->name('board-members.update-role');
    Route::delete('boards/{board}/members/{user}', [BoardMemberController::class, 'destroy'])->name('board-members.destroy');

    Route::post('boards/{board}/labels', [BoardLabelController::class, 'store'])->name('board-labels.store');
    Route::patch('labels/{label}', [BoardLabelController::class, 'update'])->name('board-labels.update');
    Route::delete('labels/{label}', [BoardLabelController::class, 'destroy'])->name('board-labels.destroy');

    Route::post('boards/{board}/events', [BoardEventController::class, 'store'])->name('board-events.store');
    Route::post('events', [BoardEventController::class, 'storeGeneral'])->name('events.store');
    Route::patch('events/{event}', [BoardEventController::class, 'update'])->name('board-events.update');
    Route::delete('events/{event}', [BoardEventController::class, 'destroy'])->name('board-events.destroy');

    Route::post('boards/{board}/lists', [BoardListController::class, 'store'])->name('board-lists.store');
    Route::patch('boards/{board}/lists/reorder', [BoardListController::class, 'reorder'])->name('board-lists.reorder');
    Route::patch('lists/{boardList}', [BoardListController::class, 'update'])->name('board-lists.update');
    Route::post('lists/{boardList}/duplicate', [BoardListController::class, 'duplicate'])->name('board-lists.duplicate');
    Route::patch('lists/{boardList}/archive', [BoardListController::class, 'archive'])->name('board-lists.archive');
    Route::patch('lists/{boardList}/restore', [BoardListController::class, 'restore'])->name('board-lists.restore');
    Route::delete('lists/{boardList}', [BoardListController::class, 'destroy'])->name('board-lists.destroy');

    Route::post('lists/{boardList}/cards', [CardController::class, 'store'])->name('cards.store');
    Route::patch('boards/{board}/cards/reorder', [CardController::class, 'reorder'])->name('cards.reorder');
    Route::post('boards/{board}/cards/bulk-archive', [CardController::class, 'bulkArchive'])->name('cards.bulk-archive');
    Route::post('boards/{board}/cards/bulk-move', [CardController::class, 'bulkMove'])->name('cards.bulk-move');
    Route::post('boards/{board}/cards/bulk-label', [CardController::class, 'bulkAddLabel'])->name('cards.bulk-label');
    Route::patch('cards/{card}', [CardController::class, 'update'])->name('cards.update');
    Route::post('cards/{card}/duplicate', [CardController::class, 'duplicate'])->name('cards.duplicate');
    Route::patch('cards/{card}/archive', [CardController::class, 'archive'])->name('cards.archive');
    Route::patch('cards/{card}/restore', [CardController::class, 'restore'])->name('cards.restore');
    Route::delete('cards/{card}', [CardController::class, 'destroy'])->name('cards.destroy');

    Route::post('cards/{card}/checklists', [ChecklistController::class, 'store'])->name('checklists.store');
    Route::patch('checklists/{checklist}', [ChecklistController::class, 'update'])->name('checklists.update');
    Route::post('checklists/{checklist}/duplicate', [ChecklistController::class, 'duplicate'])->name('checklists.duplicate');
    Route::delete('checklists/{checklist}', [ChecklistController::class, 'destroy'])->name('checklists.destroy');

    Route::post('checklists/{checklist}/items', [ChecklistItemController::class, 'store'])->name('checklist-items.store');
    Route::patch('checklist-items/{checklistItem}', [ChecklistItemController::class, 'update'])->name('checklist-items.update');
    Route::delete('checklist-items/{checklistItem}', [ChecklistItemController::class, 'destroy'])->name('checklist-items.destroy');

    Route::post('checklist-items/{checklistItem}/members', [ChecklistItemMemberController::class, 'store'])->name('checklist-item-members.store');
    Route::delete('checklist-items/{checklistItem}/members/{user}', [ChecklistItemMemberController::class, 'destroy'])->name('checklist-item-members.destroy');

    Route::post('cards/{card}/members', [CardMemberController::class, 'store'])->name('card-members.store');
    Route::delete('cards/{card}/members/{user}', [CardMemberController::class, 'destroy'])->name('card-members.destroy');

    Route::post('cards/{card}/labels', [CardLabelController::class, 'store'])->name('card-labels.store');
    Route::delete('cards/{card}/labels/{label}', [CardLabelController::class, 'destroy'])->name('card-labels.destroy');

    Route::post('cards/{card}/activities', [CardActivityController::class, 'store'])->name('card-activities.store');

    Route::post('cards/{card}/attachments', [CardAttachmentController::class, 'store'])->name('card-attachments.store');
    Route::get('attachments/{attachment}/view', [CardAttachmentController::class, 'view'])->name('card-attachments.view');
    Route::get('attachments/{attachment}/download', [CardAttachmentController::class, 'download'])->name('card-attachments.download');
    Route::delete('attachments/{attachment}', [CardAttachmentController::class, 'destroy'])->name('card-attachments.destroy');
});
