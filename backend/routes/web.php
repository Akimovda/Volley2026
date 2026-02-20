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
use App\Http\Controllers\UserTimezoneController;
use App\Http\Controllers\UserSearchController;
use App\Http\Controllers\CitySearchController;
use App\Http\Controllers\OrganizerRequestController;
use App\Http\Controllers\AccountUnlinkController;
use App\Http\Controllers\AccountDeleteRequestController;
use App\Http\Controllers\ProfileContactPrivacyController;

use App\Http\Controllers\EventCreateController;
use App\Http\Controllers\EventManagementController;

use App\Http\Controllers\LocationController;
use App\Http\Controllers\PublicLocationController;

use App\Http\Controllers\EventRegistrationsManagementController;

// ADMIN
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminRoleController;
use App\Http\Controllers\Admin\AdminAuditController;
use App\Http\Controllers\Admin\OrganizerRequestAdminController;
use App\Http\Controllers\Admin\AdminUserRestrictionController;
use App\Http\Controllers\Admin\AdminLocationController;
use App\Http\Controllers\Admin\AdminLocationPhotoController;

// patterns
Route::pattern('event', '[0-9]+');
Route::pattern('occurrence', '[0-9]+');
Route::pattern('user', '[0-9]+');
Route::pattern('location', '[0-9]+');

/*
|--------------------------------------------------------------------------
| Home
|--------------------------------------------------------------------------
*/
Route::get('/', fn () => view('welcome'))->name('home');

/*
|--------------------------------------------------------------------------
| Events (PUBLIC)
|--------------------------------------------------------------------------
*/
Route::get('/events', [EventsController::class, 'index'])->name('events.index');

/**
 * Публичная страница события (гостям тоже можно смотреть).
 * Внутри blade уже есть UI "Войти чтобы записаться".
 */
Route::get('/events/{event}', [EventsController::class, 'show'])
    ->name('events.show');
/**
 * Приватное событие
 */    
Route::get('/e/{token}', [\App\Http\Controllers\PublicEventController::class, 'show'])
    ->name('events.public');
/**
 * Публичные availability (нужно для счетчиков и модалки на /events)
 */
Route::get('/occurrences/{occurrence}/availability', [EventsController::class, 'availabilityOccurrence'])
    ->name('occurrences.availability');

Route::get('/events/{event}/availability', [EventsController::class, 'availability'])
    ->name('events.availability');

/*
|--------------------------------------------------------------------------
| Public Locations (Variant B: /locations/{id}-{slug})
|--------------------------------------------------------------------------
*/
Route::get('/locations', [PublicLocationController::class, 'index'])->name('locations.index');
Route::get('/locations/{location}-{slug}', [PublicLocationController::class, 'show'])
    ->whereNumber('location')
    ->name('locations.show');
/*
|--------------------------------------------------------------------------
| Поиск городов
|--------------------------------------------------------------------------
*/    
Route::get('/cities/search', [CitySearchController::class, 'search'])
    ->name('cities.search');
/*
|--------------------------------------------------------------------------
| Поиск игроков и тренеров
|--------------------------------------------------------------------------
*/
Route::get('api/users/search', [UserSearchController::class, 'search'])
    ->middleware('web')
    ->name('api.users.search');
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

Route::get('/Dashboard', fn () => redirect()->to('/dashboard'))->name('Dashboard');

/*
|--------------------------------------------------------------------------
| AUTH: Telegram / VK / Yandex
|--------------------------------------------------------------------------
*/
Route::get('/auth/telegram/callback', [TelegramAuthController::class, 'callback'])->name('auth.telegram.callback');

Route::get('/auth/vk/redirect', [VkAuthController::class, 'redirect'])->name('auth.vk.redirect');
Route::get('/auth/vk/callback', [VkAuthController::class, 'callback'])->name('auth.vk.callback');

Route::get('/auth/yandex/redirect', [YandexAuthController::class, 'redirect'])->name('auth.yandex.redirect');
Route::get('/auth/yandex/callback', [YandexAuthController::class, 'callback'])->name('auth.yandex.callback');

/*
|--------------------------------------------------------------------------
| Event join / leave (AUTH)
|--------------------------------------------------------------------------
*/
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'user.restricted',
])->group(function () {
    // legacy join/leave by Event (пишет в первый occurrence)
    Route::post('/events/{event}/join', [EventRegistrationController::class, 'store'])
        ->name('events.join');
    Route::delete('/events/{event}/leave', [EventRegistrationController::class, 'destroy'])
        ->name('events.leave');

    // NEW join/leave by Occurrence
    Route::post('/occurrences/{occurrence}/join', [EventRegistrationController::class, 'storeOccurrence'])
        ->name('occurrences.join');
    Route::delete('/occurrences/{occurrence}/leave', [EventRegistrationController::class, 'destroyOccurrence'])
        ->name('occurrences.leave');
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
    Route::get('/user/photos', [UserPhotoController::class, 'index'])->name('user.photos');
    Route::post('/user/photos', [UserPhotoController::class, 'store'])->name('user.photos.store');
    Route::post('/user/photos/{media}/set-avatar', [UserPhotoController::class, 'setAvatar'])->name('user.photos.setAvatar');
    Route::delete('/user/photos/{media}', [UserPhotoController::class, 'destroy'])->name('user.photos.destroy');

    Route::post('/account/unlink/telegram', [AccountUnlinkController::class, 'telegram'])->name('account.unlink.telegram');
    Route::post('/account/unlink/vk', [AccountUnlinkController::class, 'vk'])->name('account.unlink.vk');
    Route::post('/account/unlink/yandex', [AccountUnlinkController::class, 'yandex'])->name('account.unlink.yandex');

    Route::post('/account/delete-request', [AccountDeleteRequestController::class, 'store'])->name('account.delete.request');

    Route::post('/profile/contact-privacy', [ProfileContactPrivacyController::class, 'update'])
        ->name('profile.contact_privacy.update');
});

/*
|--------------------------------------------------------------------------
| Event create + management (AUTH + VERIFIED + RESTRICTED)
|--------------------------------------------------------------------------
| ✅ /events/create                  -> мастер создания (choose -> create)
| ✅ /events/create/event_management -> управление
| ❌ /events/create/from-scratch     -> УДАЛЕНО
| ❌ /events/create/from-template    -> УДАЛЕНО
|--------------------------------------------------------------------------
*/
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'user.restricted',
])->group(function () {

    Route::get('/events/create', [EventCreateController::class, 'choose'])
        ->name('events.create');

    Route::get('/events/create/event_management', [EventManagementController::class, 'index'])
        ->name('events.create.event_management');

    // ✅ edit/update
    Route::get('/events/create/event_management/{event}/edit', [EventManagementController::class, 'edit'])
        ->name('events.event_management.edit');

    Route::put('/events/create/event_management/{event}', [EventManagementController::class, 'update'])
        ->name('events.event_management.update');

    // ✅ создание события
    Route::post('/events', [EventCreateController::class, 'store'])
        ->name('events.store');

    // ⚠️ Вариант А: если ты копируешь через /events/create?from_event_id=ID (как в blade) —
    // этот POST-роут не обязателен. Оставляй только если реально используешь его где-то.
    Route::post('/events/{event}/copy', [EventCreateController::class, 'fromEvent'])
        ->name('events.copy');

    Route::post('/locations/quick', [LocationController::class, 'quickStore'])
        ->name('locations.quick_store')
        ->middleware(['can:is-admin']);
});

/*
|--------------------------------------------------------------------------
| Event Registrations management (auth + verified + restricted)
|--------------------------------------------------------------------------
*/
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'user.restricted',
])->group(function () {
    Route::get('/events/{event}/registrations', [EventRegistrationsManagementController::class, 'index'])
        ->name('events.registrations.index');

    Route::post('/events/{event}/registrations/add', [EventRegistrationsManagementController::class, 'addPlayer'])
        ->name('events.registrations.add');

    Route::patch('/events/{event}/registrations/{registration}/position', [EventRegistrationsManagementController::class, 'updatePosition'])
        ->name('events.registrations.position');

    Route::patch('/events/{event}/registrations/{registration}/cancel', [EventRegistrationsManagementController::class, 'cancel'])
        ->name('events.registrations.cancel');

    Route::delete('/events/{event}/registrations/{registration}', [EventRegistrationsManagementController::class, 'destroy'])
        ->name('events.registrations.destroy');
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

    Route::post('/profile/timezone', [UserTimezoneController::class, 'store'])
        ->name('profile.timezone.store');
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
        Route::get('/audits', [AdminAuditController::class, 'index'])->name('audits.index');

        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');
        Route::post('/users/{user}/role', [AdminRoleController::class, 'updateUserRole'])->name('users.role.update');
        Route::delete('/users/{user}/purge', [AdminUserController::class, 'purge'])->name('users.purge');

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

        // Locations CRUD
        Route::get('/locations', [AdminLocationController::class, 'index'])->name('locations.index');
        Route::get('/locations/create', [AdminLocationController::class, 'create'])->name('locations.create');
        Route::post('/locations', [AdminLocationController::class, 'store'])->name('locations.store');
        Route::get('/locations/{location}/edit', [AdminLocationController::class, 'edit'])->name('locations.edit');
        Route::put('/locations/{location}', [AdminLocationController::class, 'update'])->name('locations.update');
        Route::delete('/locations/{location}', [AdminLocationController::class, 'destroy'])->name('locations.destroy');

        Route::post('/locations/{location}/photos/reorder', [AdminLocationPhotoController::class, 'reorder'])
            ->name('locations.photos.reorder');
        Route::delete('/locations/{location}/photos/{media}', [AdminLocationPhotoController::class, 'destroy'])
            ->name('locations.photos.destroy');
    });

/*
|--------------------------------------------------------------------------
| Public users
|--------------------------------------------------------------------------
*/
Route::get('/users', [UserDirectoryController::class, 'index'])->name('users.index');
Route::get('/user/{user}', [UserPublicController::class, 'show'])
    ->whereNumber('user')
    ->name('users.show');

/*
|--------------------------------------------------------------------------
| Public pages
|--------------------------------------------------------------------------
*/
Route::get('/level_players', fn () => view('pages.level_players'))->name('level_players');
Route::view('/personal_data_agreement', 'pages.personal_data_agreement')->name('personal_data_agreement');

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
