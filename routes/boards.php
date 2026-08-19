<?php

use App\Http\Controllers\BoardController;
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
});
