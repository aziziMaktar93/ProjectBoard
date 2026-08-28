<?php

use App\Http\Controllers\BoardController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\WorkspaceFavouriteController;
use App\Http\Controllers\WorkspaceMemberController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::post('workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
    Route::get('workspaces/{workspace}', [WorkspaceController::class, 'show'])->name('workspaces.show');
    Route::patch('workspaces/{workspace}', [WorkspaceController::class, 'update'])->name('workspaces.update');
    Route::delete('workspaces/{workspace}', [WorkspaceController::class, 'destroy'])->name('workspaces.destroy');
    Route::patch('workspaces/{workspace}/favourite', [WorkspaceFavouriteController::class, 'toggle'])->name('workspaces.favourite');

    Route::get('workspaces/{workspace}/members/search', [WorkspaceMemberController::class, 'search'])->name('workspace-members.search');
    Route::post('workspaces/{workspace}/members', [WorkspaceMemberController::class, 'store'])->name('workspace-members.store');
    Route::delete('workspaces/{workspace}/members/{user}', [WorkspaceMemberController::class, 'destroy'])->name('workspace-members.destroy');

    Route::post('workspaces/{workspace}/boards', [BoardController::class, 'store'])->name('workspaces.boards.store');
    Route::get('workspaces/{workspace}/boards/archived', [BoardController::class, 'archived'])->name('boards.archived');
});
