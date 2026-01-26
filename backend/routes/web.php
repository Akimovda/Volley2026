<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Auth\TelegramAuthController;
use App\Http\Controllers\Auth\VkAuthController;
use App\Http\Controllers\Auth\YandexAuthController;

use App\Http\Controllers\EventsController;
use App\Http\Controllers\EventRegistrationController;

use App\Http\Controllers\UserDirectoryController;
use App\Http\Controllers\UserPublicController;
use App\Http\Controllers\UserPhotoController;

use App\Http\Controllers\OrganizerRequestController;

// unlink providers
use App\Http\Controllers\AccountUnlinkController;

// account delete request
use App\Http\Controllers\AccountDeleteRequestController;

// ✅ privacy toggle controller
use App\Http\Controllers\ProfileContactPrivacyController;

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminRoleController;
use App\Http\Controllers\Admin\AdminAuditController;
use App\Http\Controllers\Admin\OrganizerRequestAdminController;
use App\Http\Controllers\Admin\AdminUserRestrictionController;

// ✅ создание мероприятий
use App\Http\Controllers\EventCreateController;
use App\Http\Controllers\LocationController;

/*
|--------------------------------------------------------------------------
| Home
|--------------------------------------------------------------------------
*/
Route::get('/', fn () => view('welcome'))->name('home');

/*
|--------------------------------------------------------------------------
| Events (PUBLIC ENTRY POINT)
|--------------------------------------------------------------------------
*/
Route::get('/events', [EventsController::class, 'index'])
    ->name('events.index');

/*
|--------------------------------------------------------------------------
| Dashboard (user) + Alias /Dashboard
|--------------------------------------------------------------------------
*/
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
])->group(function () {
    Route::get('/dashboard', fn () => view('dashboard'))->name('dashboard');
});

// Alias for old URL (case-sensitive path)
Route::get('/Dashboard', fn () => redirect()->to('/dashboard'))->name('Dashboard');

/*
|--------------------------------------------------------------------------
| AUTH: Telegram / VK / Yandex
|--------------------------------------------------------------------------
*/
Route::get('/auth/telegram/callback', [TelegramAuthController::class, 'callback'])
    ->name('auth.telegram.callback');

// (опционально) если когда-нибудь понадобится отдельная "подготовка" сессии для telegram
// Route::get('/auth/telegram/redirect', [TelegramAuthController::class, 'redirect'])
//     ->name('auth.telegram.redirect');

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
| Event join / leave (auth + verified + restricted)
|--------------------------------------------------------------------------
*/
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'user.restricted',
])->group(function () {
    Route::post('/events/{event}/join', [EventRegistrationController::class, 'store'])
        ->name('events.join');
    Route::delete('/events/{event}/leave', [EventRegistrationController::class, 'destroy'])
        ->name('events.leave');
});

/*
|--------------------------------------------------------------------------
| Profile / Photos / Unlink / Delete-request / Privacy (auth + verified)
|--------------------------------------------------------------------------
*/
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    // Photos / avatar gallery
    Route::get('/user/photos', [UserPhotoController::class, 'index'])->name('user.photos');
    Route::post('/user/photos', [UserPhotoController::class, 'store'])->name('user.photos.store');
    Route::post('/user/photos/{media}/set-avatar', [UserPhotoController::class, 'setAvatar'])->name('user.photos.setAvatar');
    Route::delete('/user/photos/{media}', [UserPhotoController::class, 'destroy'])->name('user.photos.destroy');

    // Unlink provider routes (POST)
    Route::post('/account/unlink/telegram', [AccountUnlinkController::class, 'telegram'])
        ->name('account.unlink.telegram');
    Route::post('/account/unlink/vk', [AccountUnlinkController::class, 'vk'])
        ->name('account.unlink.vk');
    Route::post('/account/unlink/yandex', [AccountUnlinkController::class, 'yandex'])
        ->name('account.unlink.yandex');

    // Account delete request
    Route::post('/account/delete-request', [AccountDeleteRequestController::class, 'store'])
        ->name('account.delete.request');

    // ✅ Privacy toggle (совпадает с Blade: route('profile.contact_privacy.update'))
    Route::post('/profile/contact-privacy', [ProfileContactPrivacyController::class, 'update'])
        ->name('profile.contact_privacy.update');
});

/*
|--------------------------------------------------------------------------
| Event create + quick locations (admin/organizer/staff)
|--------------------------------------------------------------------------
*/
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'user.restricted',
])->group(function () {
    Route::get('/events/create', [EventCreateController::class, 'create'])
        ->name('events.create');

    Route::post('/events', [EventCreateController::class, 'store'])
        ->name('events.store');

    // quick-create location (AJAX/обычный POST)
    Route::post('/locations/quick', [LocationController::class, 'quickStore'])
        ->name('locations.quick_store');
});


/*
|--------------------------------------------------------------------------
| Profile completion / extra data (auth)
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
| Organizer request (public)
|--------------------------------------------------------------------------
*/
Route::post('/organizer/request', [OrganizerRequestController::class, 'store'])
    ->name('organizer.request');

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

        Route::get('/audits', [AdminAuditController::class, 'index'])
            ->name('audits.index');

        Route::get('/users', [AdminUserController::class, 'index'])
            ->name('users.index');

        Route::get('/users/{user}', [AdminUserController::class, 'show'])
            ->name('users.show');

        Route::post('/users/{user}/role', [AdminRoleController::class, 'updateUserRole'])
            ->name('users.role.update');

        Route::delete('/users/{user}/purge', [AdminUserController::class, 'purge'])
            ->name('users.purge');

        Route::post('/users/{user}/restrictions/events', [AdminUserRestrictionController::class, 'banEvents'])
            ->name('users.restrictions.events');

        Route::post('/users/{user}/restrictions/clear', [AdminUserRestrictionController::class, 'clearAll'])
            ->name('users.restrictions.clear');

        Route::get('/organizer-requests', [OrganizerRequestAdminController::class, 'index'])
            ->name('organizer_requests.index');

        Route::post('/organizer-requests/{request}/approve', [OrganizerRequestAdminController::class, 'approve'])
            ->name('organizer_requests.approve');

        Route::post('/organizer-requests/{request}/reject', [OrganizerRequestAdminController::class, 'reject'])
            ->name('organizer_requests.reject');
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
| Public pages
|--------------------------------------------------------------------------
*/
Route::get('/level_players', fn () => view('pages.level_players'))
    ->name('level_players');

Route::view('/personal_data_agreement', 'pages.personal_data_agreement')
    ->name('personal_data_agreement');

/*
|--------------------------------------------------------------------------
| Debug (optional)
|--------------------------------------------------------------------------
*/
Route::get('/debug/session', function () {
    return response()->json([
        'user_id' => auth()->id(),
        'session' => session()->all(),
    ]);
})->middleware('auth');
