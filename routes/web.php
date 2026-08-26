<?php

use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('dashboard/report', [DashboardController::class, 'report'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard.report');

Route::get('calendar', [CalendarController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('calendar');

Route::get('members', [MemberController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('members');

Route::get('search', [SearchController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('search');

Route::patch('notifications/read-all', [NotificationController::class, 'markAllAsRead'])
    ->middleware(['auth', 'verified'])
    ->name('notifications.read-all');

Route::patch('notifications/{notification}/read', [NotificationController::class, 'markAsRead'])
    ->middleware(['auth', 'verified'])
    ->name('notifications.read');

require __DIR__.'/settings.php';
require __DIR__.'/workspaces.php';
require __DIR__.'/boards.php';
require __DIR__.'/auth.php';
