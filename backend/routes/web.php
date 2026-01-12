<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Auth\TelegramAuthController;
use App\Http\Controllers\Auth\VkAuthController;
use App\Http\Controllers\Auth\YandexAuthController;

use App\Http\Controllers\EventsController;
use App\Http\Controllers\EventRegistrationController;

use App\Http\Controllers\UserDirectoryController;
use App\Http\Controllers\UserPublicController;

use App\Http\Controllers\OrganizerRequestController;

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminRoleController;
use App\Http\Controllers\Admin\AdminAuditController;
use App\Http\Controllers\Admin\OrganizerRequestAdminController;

/*
|--------------------------------------------------------------------------
| Home
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return view('welcome');
})->name('home');

/*
|--------------------------------------------------------------------------
| AUTH: Telegram / VK / Yandex
|--------------------------------------------------------------------------
| Важно:
| - callback'и должны быть доступны без auth middleware
| - Telegram виджет ходит на auth-url (обычно GET)
*/
Route::get('/auth/telegram/redirect', [TelegramAuthController::class, 'redirect'])
    ->name('auth.telegram.redirect');
Route::match(['GET', 'POST'], '/auth/telegram/callback', [TelegramAuthController::class, 'callback'])
    ->name('auth.telegram.callback');

Route::get('/auth/vk/redirect', [VkAuthController::class, 'redirect'])
    ->name('auth.vk.redirect');
Route::get('/auth/vk/callback', [VkAuthController::class, 'callback'])
    ->name('auth.vk.callback');

Route::get('/auth/yandex/redirect', [YandexAuthController::class, 'redirect'])
    ->name('auth.yandex.redirect');
Route::get('/auth/yandex/callback', [YandexAuthController::class, 'callback'])
    ->name('auth.yandex.callback');

/*
|--------------------------------------------------------------------------
| ADMIN
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'can:is-admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        Route::get('/audits', [AdminAuditController::class, 'index'])->name('audits.index');

        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');
        Route::post('/users/{user}/role', [AdminRoleController::class, 'updateUserRole'])->name('users.role.update');

        // Purge: DELETE (не POST)
        Route::delete('/users/{user}/purge', [AdminUserController::class, 'purge'])->name('users.purge');

        Route::get('/organizer-requests', [OrganizerRequestAdminController::class, 'index'])->name('organizer_requests.index');
        Route::post('/organizer-requests/{request}/approve', [OrganizerRequestAdminController::class, 'approve'])->name('organizer_requests.approve');
        Route::post('/organizer-requests/{request}/reject', [OrganizerRequestAdminController::class, 'reject'])->name('organizer_requests.reject');
    });

/*
|--------------------------------------------------------------------------
| Organizer request (public)
|--------------------------------------------------------------------------
*/
Route::post('/organizer/request', [OrganizerRequestController::class, 'store'])
    ->name('organizer.request');

/*
|--------------------------------------------------------------------------
| Events (public)
|--------------------------------------------------------------------------
*/
Route::get('/events', [EventsController::class, 'index'])
    ->name('events.index');

/*
|--------------------------------------------------------------------------
| Event join/leave (auth)
|--------------------------------------------------------------------------
| Если у вас join/leave делается обычными формами с web-сессией,
| можно заменить на ['auth'] вместо sanctum.
*/
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
])->group(function () {
    Route::post('/events/{event}/join', [EventRegistrationController::class, 'store'])->name('events.join');
    Route::delete('/events/{event}/leave', [EventRegistrationController::class, 'destroy'])->name('events.leave');
});

/*
|--------------------------------------------------------------------------
| Profile completion / extra data
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {
    Route::get('/profile/complete', [\App\Http\Controllers\ProfileCompletionController::class, 'show'])
        ->name('profile.complete');

    Route::post('/profile/extra', [\App\Http\Controllers\ProfileExtraController::class, 'update'])
        ->name('profile.extra.update');
});

/*
|--------------------------------------------------------------------------
| Dashboard (Jetstream default) — under verified
|--------------------------------------------------------------------------
*/
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
});

/*
|--------------------------------------------------------------------------
| Public users
|--------------------------------------------------------------------------
*/
Route::get('/users', [UserDirectoryController::class, 'index'])
    ->name('users.index');

Route::get('/user/{user}', [UserPublicController::class, 'show'])
    ->whereNumber('user')
    ->name('users.show');

/*
|--------------------------------------------------------------------------
| Debug
|--------------------------------------------------------------------------
*/
Route::get('/debug/session', function () {
    return response()->json([
        'user_id' => auth()->id(),
        'auth_provider' => session('auth_provider'),
        'session_keys' => array_keys(session()->all()),
    ]);
})->middleware('auth');
