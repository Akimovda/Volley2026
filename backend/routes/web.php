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

use App\Http\Controllers\Account\LinkCodeController;
use App\Http\Controllers\Account\LinkConsumeController;

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
| AUTH: Telegram
|--------------------------------------------------------------------------
*/
Route::get('/auth/telegram/redirect', [TelegramAuthController::class, 'redirect'])
    ->name('auth.telegram.redirect');

Route::get('/auth/telegram/callback', [TelegramAuthController::class, 'callback'])
    ->name('auth.telegram.callback');

/*
|--------------------------------------------------------------------------
| AUTH: VK
|--------------------------------------------------------------------------
*/
Route::get('/auth/vk/redirect', [VkAuthController::class, 'redirect'])
    ->name('auth.vk.redirect');

Route::get('/auth/vk/callback', [VkAuthController::class, 'callback'])
    ->name('auth.vk.callback');
/*
|-------------------------------------------------------------------------
| AUTH: Yandex
|-------------------------------------------------------------------------
*/
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
        // dashboard
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])
            ->name('dashboard');

        // audits
        Route::get('/audits', [AdminAuditController::class, 'index'])
            ->name('audits.index');

        // users
        Route::get('/users', [AdminUserController::class, 'index'])
            ->name('users.index');

        Route::get('/users/{user}', [AdminUserController::class, 'show'])
            ->name('users.show');

        Route::post('/users/{user}/role', [AdminRoleController::class, 'updateUserRole'])
            ->name('users.role.update');

        // delete / purge (ВАЖНО: purge делаем DELETE, без post-дубля)

        Route::delete('/users/{user}/purge', [AdminUserController::class, 'purge'])
            ->name('users.purge');

        // organizer requests (тоже в admin, без второй группы/префикса)
        Route::get('/organizer-requests', [OrganizerRequestAdminController::class, 'index'])
            ->name('organizer_requests.index');

        Route::post('/organizer-requests/{request}/approve', [OrganizerRequestAdminController::class, 'approve'])
            ->name('organizer_requests.approve');

        Route::post('/organizer-requests/{request}/reject', [OrganizerRequestAdminController::class, 'reject'])
            ->name('organizer_requests.reject');
    });

/*
|--------------------------------------------------------------------------
| Organizer request (public -> create request)
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
| Event join/leave (sanctum)
|--------------------------------------------------------------------------
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

// /*
/*
|--------------------------------------------------------------------------
| Account link-code flow (auth)  [DISABLED]
|--------------------------------------------------------------------------
*/
// Route::middleware(['auth'])->group(function () {
//     Route::post('/account/link-code', [LinkCodeController::class, 'store'])
//         ->name('account.link_code.store');
//
//     Route::get('/account/link', [LinkConsumeController::class, 'show'])
//         ->name('account.link.show');
//
//     Route::post('/account/link', [LinkConsumeController::class, 'store'])
//         ->name('account.link.store');
// });

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
| Dashboard (optional) - jetstream default
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
