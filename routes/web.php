<?php

use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DashboardChatController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReportsController;
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

Route::get('dashboard/ai/conversation', [DashboardChatController::class, 'show'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard-chat.show');

Route::post('dashboard/ai/messages', [DashboardChatController::class, 'sendMessage'])
    ->middleware(['auth', 'verified', 'throttle:20,1'])
    ->name('dashboard-chat.messages.store');

Route::get('calendar', [CalendarController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('calendar');

Route::get('members', [MemberController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('members');

Route::get('search', [SearchController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('search');

Route::get('notifications', [NotificationController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('notifications.index');

Route::patch('notifications/read-all', [NotificationController::class, 'markAllAsRead'])
    ->middleware(['auth', 'verified'])
    ->name('notifications.read-all');

Route::get('notifications/{notification}/open', [NotificationController::class, 'open'])
    ->middleware(['auth', 'verified'])
    ->name('notifications.open');

Route::patch('notifications/{notification}/read', [NotificationController::class, 'markAsRead'])
    ->middleware(['auth', 'verified'])
    ->name('notifications.read');

Route::get('reports', [ReportsController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('reports.index');

Route::get('reports/on-time-completion', [ReportsController::class, 'onTimeCompletion'])
    ->middleware(['auth', 'verified'])
    ->name('reports.on-time-completion');

Route::get('reports/member-performance', [ReportsController::class, 'memberPerformance'])
    ->middleware(['auth', 'verified'])
    ->name('reports.member-performance');

Route::get('reports/activity-log', [ReportsController::class, 'activityLog'])
    ->middleware(['auth', 'verified'])
    ->name('reports.activity-log');

Route::get('reports/activity-log/csv', [ReportsController::class, 'activityLogCsv'])
    ->middleware(['auth', 'verified'])
    ->name('reports.activity-log-csv');

Route::get('reports/checklist-timeline', [ReportsController::class, 'checklistTimeline'])
    ->middleware(['auth', 'verified'])
    ->name('reports.checklist-timeline');

require __DIR__.'/settings.php';
require __DIR__.'/workspaces.php';
require __DIR__.'/boards.php';
require __DIR__.'/auth.php';
