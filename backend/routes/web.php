<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Auth\TelegramAuthController;
use App\Http\Controllers\Auth\VkAuthController;
use App\Http\Controllers\EventRegistrationController;
use App\Http\Controllers\EventsController;

Route::get('/', function () {
    return view('welcome');
})->name('home');

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
