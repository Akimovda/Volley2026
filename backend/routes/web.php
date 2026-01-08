<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserDirectoryController;
use App\Http\Controllers\UserPublicController;
use App\Http\Controllers\Auth\TelegramAuthController;
use App\Http\Controllers\Auth\VkAuthController;
use App\Http\Controllers\EventRegistrationController;
use App\Http\Controllers\EventsController;
use App\Http\Controllers\OrganizerRequestController;
use App\Http\Controllers\Admin\OrganizerRequestAdminController;

use App\Http\Controllers\Account\LinkCodeController;
use App\Http\Controllers\Account\LinkConsumeController;

Route::get('/', function () {
    return view('welcome');
})->name('home');

/*Отправки сообщений Админу что бы стать организатором*/
Route::middleware(['auth'])->group(function () {
    // User: send organizer request
    Route::post('/organizer/request', [OrganizerRequestController::class, 'store'])
        ->name('organizer.request');

    // Admin: organizer requests
    Route::middleware(['can:approve-organizer-request'])->prefix('admin')->group(function () {
        Route::get('/organizer-requests', [OrganizerRequestAdminController::class, 'index'])
            ->name('admin.organizer_requests.index');

        Route::post('/organizer-requests/{request}/approve', [OrganizerRequestAdminController::class, 'approve'])
            ->name('admin.organizer_requests.approve');

        Route::post('/organizer-requests/{request}/reject', [OrganizerRequestAdminController::class, 'reject'])
            ->name('admin.organizer_requests.reject');
    });

    /**
     * Account link-code flow (привязка Telegram/VK через одноразовый код)
     */
    Route::post('/account/link-code', [LinkCodeController::class, 'store'])
        ->name('account.link_code.store');

    Route::get('/account/link', [LinkConsumeController::class, 'show'])
        ->name('account.link.show');

    Route::post('/account/link', [LinkConsumeController::class, 'store'])
        ->name('account.link.store');
});

/**
 * Events page
 */
Route::get('/events', [EventsController::class, 'index'])
    ->name('events.index');

/**
 * Telegram login callback
 * (должен быть ДО protected routes)
 */
Route::get('/telegram/callback', [TelegramAuthController::class, 'callback'])
    ->name('telegram.callback');

/**
 * VK ID (OAuth 2.1 + PKCE) routes
 * Используем только /auth/vk/* чтобы не плодить 2 разных набора URL
 */
Route::get('/auth/vk/redirect', [VkAuthController::class, 'redirect'])
    ->name('auth.vk.redirect');

Route::get('/auth/vk/callback', [VkAuthController::class, 'callback'])
    ->name('auth.vk.callback');

/**
 * Event join (запись на мероприятие) — только для авторизованных
 * Без verified, чтобы Telegram/VK пользователи могли пользоваться сразу
 */
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
])->group(function () {
    Route::post('/events/{event}/join', [EventRegistrationController::class, 'store'])
        ->name('events.join');

    Route::delete('/events/{event}/leave', [EventRegistrationController::class, 'destroy'])
        ->name('events.leave');
});

/**
 * Profile completion helper page
 */
Route::get('/profile/complete', [\App\Http\Controllers\ProfileCompletionController::class, 'show'])
    ->middleware(['auth'])
    ->name('profile.complete');

/**
 * Extra profile (анкета игрока)
 */
Route::post('/profile/extra', [\App\Http\Controllers\ProfileExtraController::class, 'update'])
    ->middleware(['auth'])
    ->name('profile.extra.update');

/**
 * Jetstream dashboard (может быть полезен для админки/внутренних страниц).
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

/**
 * Public users directory + public profile
 */
Route::get('/users', [UserDirectoryController::class, 'index'])
    ->name('users.index');

Route::get('/user/{user}', [UserPublicController::class, 'show'])
    ->whereNumber('user')
    ->name('users.show');
Route::get('/debug/session', function () {
    return response()->json([
        'user_id' => auth()->id(),
        'auth_provider' => session('auth_provider'),
        'session_keys' => array_keys(session()->all()),
    ]);
})->middleware('auth');
