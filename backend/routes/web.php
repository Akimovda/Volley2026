<?php
	//web.php
	declare(strict_types=1);
	
	use Illuminate\Support\Facades\Route;
	use App\Http\Controllers\Auth\TelegramAuthController;
	use App\Http\Controllers\Auth\VkAuthController;
	use App\Http\Controllers\Auth\YandexAuthController;
	use App\Http\Controllers\MaxBindingController;
	use App\Http\Controllers\NotificationController;
	use App\Http\Controllers\EventsController;
	use App\Http\Controllers\EventCreateController;
	use App\Http\Controllers\EventRegistrationController;
    use App\Http\Controllers\EventRegistrationInviteController;
	use App\Http\Controllers\EventManagementController;
	use App\Http\Controllers\EventRegistrationsManagementController;
	use App\Http\Controllers\EventRegistrationGroupController;
	use App\Http\Controllers\UserDirectoryController;
	use App\Http\Controllers\UserPublicController;
	use App\Http\Controllers\UserPhotoController;
	use App\Http\Controllers\UserSearchController;
	use App\Http\Controllers\CitySearchController;
	use App\Http\Controllers\OrganizerRequestController;
	use App\Http\Controllers\AccountUnlinkController;
	use App\Http\Controllers\AccountDeleteRequestController;
	use App\Http\Controllers\ProfileContactPrivacyController;
	use App\Http\Controllers\LocationController;
	use App\Http\Controllers\PublicLocationController;
	use App\Http\Controllers\TournamentTeamController;
	use App\Http\Controllers\TournamentTeamInviteController;
	use App\Http\Controllers\ProfileNotificationChannelController;
	use App\Http\Controllers\TelegramNotifyBindingController;
	use App\Http\Controllers\VkNotifyBindingController;
	use App\Http\Controllers\OccurrenceWaitlistController;
	
	// ADMIN
	use App\Http\Controllers\Admin\AdminDashboardController;
	use App\Http\Controllers\Admin\AdminUserController;
	use App\Http\Controllers\Admin\AdminRoleController;
	use App\Http\Controllers\Admin\AdminAuditController;
	use App\Http\Controllers\Admin\OrganizerRequestAdminController;
	use App\Http\Controllers\Admin\AdminUserRestrictionController;
	use App\Http\Controllers\Admin\AdminLocationController;
	use App\Http\Controllers\Admin\AdminLocationPhotoController;
	use App\Http\Controllers\Admin\AdminNotificationTemplateController;
	use App\Http\Controllers\Admin\AdminBroadcastController;
	
	// AJAX (city-first)
	use App\Http\Controllers\Ajax\CityMetaController;
	use App\Http\Controllers\Ajax\LocationsByCityController;
	
	/*
		|--------------------------------------------------------------------------
		| Route patterns
		|--------------------------------------------------------------------------
	*/
	
	Route::pattern('event', '[0-9]+');
	Route::pattern('occurrence', '[0-9]+');
	Route::pattern('user', '[0-9]+');
	Route::pattern('location', '[0-9]+');
	Route::pattern('invite', '[0-9]+');
	Route::pattern('notification', '[0-9]+');
	
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
	
	Route::get('/events/{event}', [EventsController::class, 'show'])
    ->middleware('track.view:event,event')
    ->name('events.show');
	
	/*
		|--------------------------------------------------------------------------
		| Private public-token event page
		|--------------------------------------------------------------------------
	*/
	
	Route::get('/e/{token}', [\App\Http\Controllers\PublicEventController::class, 'show'])
    ->name('events.public');
	
	/*
		|--------------------------------------------------------------------------
		| Public availability endpoints
		|--------------------------------------------------------------------------
	*/
	
	Route::get('/occurrences/{occurrence}/availability', [EventsController::class, 'availabilityOccurrence'])
    ->name('occurrences.availability');
	
	/*
		|--------------------------------------------------------------------------
		| Public Locations (Variant B: /locations/{id}-{slug})
		|--------------------------------------------------------------------------
	*/
	
	Route::get('/locations', [PublicLocationController::class, 'index'])->name('locations.index');
	
	Route::get('/locations/{location}-{slug}', [PublicLocationController::class, 'show'])
    ->whereNumber('location')
    ->middleware('track.view:location,location')
    ->name('locations.show');
	
	/*
		|--------------------------------------------------------------------------
		| Channel Notification
		|--------------------------------------------------------------------------
	*/
	Route::middleware(['auth'])->group(function () {
		Route::get('/user/profile/notification-channels', [ProfileNotificationChannelController::class, 'index'])
        ->name('profile.notification_channels');
		
		Route::post('/user/profile/notification-channels/bind', [ProfileNotificationChannelController::class, 'createBind'])
        ->name('profile.notification_channels.bind');
		
		Route::delete('/user/profile/notification-channels/{channel}', [ProfileNotificationChannelController::class, 'destroy'])
        ->name('profile.notification_channels.destroy');
	});
	/*
		|--------------------------------------------------------------------------
		| Notification
		|--------------------------------------------------------------------------
	*/
	
	Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
	])->group(function () {
		Route::get('/notifications', [NotificationController::class, 'index'])
        ->name('notifications.index');
		
		Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])
        ->name('notifications.read');
		
		Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])
        ->name('notifications.read_all');
		
		Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');		
		
		Route::post('/profile/telegram/generate-link', [TelegramNotifyBindingController::class, 'generate'])
		->name('profile.telegram.generate_link');
		
		Route::post('/profile/telegram/disconnect', [TelegramNotifyBindingController::class, 'disconnect'])
        ->name('profile.telegram.disconnect');
		
		Route::post('/profile/vk/generate-link', [VkNotifyBindingController::class, 'generate'])
        ->name('profile.vk.generate_link');
		
		Route::post('/profile/vk/disconnect', [VkNotifyBindingController::class, 'disconnect'])
        ->name('profile.vk.disconnect');
	});
	
	/*
		|--------------------------------------------------------------------------
		| City search (used by create wizard autocomplete)
		|--------------------------------------------------------------------------
	*/
	
	Route::get('/cities/search', [CitySearchController::class, 'search'])
    ->name('cities.search');
    
    Route::get('/ajax/locations/with-events', LocationsByCityController::class)
    ->name('ajax.locations.withEvents');
	
	/*
		|--------------------------------------------------------------------------
		| Dashboard (user)
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
		| AUTH: Турниры (команды, заявки, приглашения)
		|--------------------------------------------------------------------------
	*/
	Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'user.restricted',
	])->group(function () {
		// Просмотр команды
		Route::get('/events/{event}/teams/{team}', [TournamentTeamController::class, 'show'])
        ->name('tournamentTeams.show');
		
		// Создание команды
		Route::post('/events/{event}/teams', [TournamentTeamController::class, 'store'])
        ->name('tournamentTeams.store');
		// Создание ссылки-приглашения в команду
		Route::post('/events/{event}/teams/{team}/invites', [TournamentTeamInviteController::class, 'store'])
        ->name('tournamentTeamInvites.store');
		
		Route::get('/team-invites/{token}', [TournamentTeamInviteController::class, 'show'])
        ->name('tournamentTeamInvites.show');
		
		Route::post('/team-invites/{token}/accept', [TournamentTeamInviteController::class, 'accept'])
        ->name('tournamentTeamInvites.accept');
		
		Route::post('/team-invites/{token}/decline', [TournamentTeamInviteController::class, 'decline'])
        ->name('tournamentTeamInvites.decline');
		
		// Подтверждение участника капитаном
		Route::post('/events/{event}/teams/{team}/members/{member}/confirm', [TournamentTeamController::class, 'confirmMember'])
        ->name('tournamentTeams.members.confirm');
		
		// Отклонение участника капитаном
		Route::post('/events/{event}/teams/{team}/members/{member}/decline', [TournamentTeamController::class, 'declineMember'])
        ->name('tournamentTeams.members.decline');
		
		// Удаление участника из команды
		Route::delete('/events/{event}/teams/{team}/members/{member}', [TournamentTeamController::class, 'removeMember'])
        ->name('tournamentTeams.members.destroy');
		
		Route::get('/events/{event}/occurrences/manage', [EventManagementController::class, 'occurrences'])
        ->name('events.event_management.occurrences');
		// Подача заявки команды на турнир
		Route::post('/events/{event}/teams/{team}/submit', [TournamentTeamController::class, 'submitApplication'])
        ->name('tournamentTeams.submit');
	});
	
	/*
		|--------------------------------------------------------------------------
		| Event join / leave (AUTH + VERIFIED + RESTRICTED)
		|--------------------------------------------------------------------------
	*/
	
	Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'user.restricted',
	])->group(function () {
	    
	    Route::post('/occurrences/{occurrence}/waitlist', [OccurrenceWaitlistController::class, 'store'])
         ->name('occurrences.waitlist.join');

        Route::delete('/occurrences/{occurrence}/waitlist', [OccurrenceWaitlistController::class, 'destroy'])
          ->name('occurrences.waitlist.leave');
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

    Route::post('/events/{event}/invite', [EventRegistrationInviteController::class, 'store'])
        ->name('events.invite');

Route::post('/events/{event}/invite', [EventRegistrationInviteController::class, 'store'])
    ->name('events.invite');
		
		Route::post('/profile/max/generate-link', [MaxBindingController::class, 'generate'])
        ->name('profile.max.generate_link');
		
		Route::post('/profile/max/disconnect', [MaxBindingController::class, 'disconnect'])
        ->name('profile.max.disconnect');
	});
	
	/*
		|--------------------------------------------------------------------------
		| Event group registrations (AUTH + VERIFIED + RESTRICTED)
		|--------------------------------------------------------------------------
	*/
	
	Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'user.restricted',
	])->group(function () {
		Route::post('/events/{event}/group/create', [EventRegistrationGroupController::class, 'create'])
        ->name('events.group.create');
		
		Route::post('/events/{event}/group/invite', [EventRegistrationGroupController::class, 'invite'])
        ->name('events.group.invite');
		
		Route::post('/events/{event}/group/invites/{invite}/accept', [EventRegistrationGroupController::class, 'accept'])
        ->name('events.group.accept');
		
		Route::post('/events/{event}/group/invites/{invite}/decline', [EventRegistrationGroupController::class, 'decline'])
        ->name('events.group.decline');
		
		Route::post('/events/{event}/group/leave', [EventRegistrationGroupController::class, 'leave'])
        ->name('events.group.leave');
		
		Route::post('/events/{event}/group/management/invite', [EventRegistrationGroupController::class, 'managementInvite'])
        ->name('events.group.management.invite');
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
Route::delete('/user/photos/event/{media}', [UserPhotoController::class, 'destroyEventPhoto'])->name('user.photos.destroyEventPhoto');
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
	*/
	
	Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'user.restricted',
	])->group(function () {
		/*
			| AJAX endpoints for create wizard (city-first)
		*/
		Route::get('/ajax/locations/by-city', LocationsByCityController::class)
        ->name('ajax.locations.byCity');
		
		Route::get('/ajax/cities/meta', CityMetaController::class)
        ->name('ajax.cities.meta');
		
		// create wizard
		Route::get('/events/create', [EventCreateController::class, 'choose'])
        ->name('events.create');
		
		// store event
		Route::post('/events', [EventCreateController::class, 'store'])
        ->name('events.store');
		
		// event management
		Route::get('/events/create/event_management', [EventManagementController::class, 'index'])
        ->name('events.create.event_management');
		
		Route::get('/events/create/event_management/{event}/edit', [EventManagementController::class, 'edit'])
        ->name('events.event_management.edit');
		
		Route::put('/events/create/event_management/{event}', [EventManagementController::class, 'update'])
        ->name('events.event_management.update');
		
		Route::delete('/events/event_management/{event}', [EventManagementController::class, 'destroy'])
        ->name('events.event_management.destroy');
		
		Route::post('/events/create/event_management/bulk', [EventManagementController::class, 'bulk'])
        ->name('events.create.event_management.bulk');
		
		Route::post('/events/create/event_management/bulk-delete', [EventManagementController::class, 'bulkDelete'])
        ->name('events.create.event_management.bulk_delete');
        
        Route::delete('/occurrences/{occurrence}', [EventManagementController::class, 'destroyOccurrence'])
        ->name('occurrences.destroy');
		
		// optional legacy copy
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
		
		Route::post('/events/{event}/registrations/{registration}/group/create', [EventRegistrationsManagementController::class, 'createGroup'])
        ->name('events.registrations.group.create');
		
		Route::post('/events/{event}/registrations/group/invite', [EventRegistrationsManagementController::class, 'inviteToGroup'])
        ->name('events.registrations.group.invite');
		
		Route::patch('/events/{event}/registrations/{registration}/group/leave', [EventRegistrationsManagementController::class, 'leaveGroup'])
        ->name('events.registrations.group.leave');
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
		
		// edit other (admin/organizer only)
		Route::post('/profile/extra/{user}', [\App\Http\Controllers\ProfileExtraController::class, 'updateOther'])
        ->whereNumber('user')
        ->name('profile.extra.update.other')
        ->middleware('can:edit-user-profile-extra,user');
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
		
        Route::post('/broadcasts/dry-run', [AdminBroadcastController::class, 'dryRun'])
		->name('broadcasts.dry_run');
        Route::post('/broadcasts/preview-audience', [AdminBroadcastController::class, 'previewAudience'])
		->name('broadcasts.preview_audience');
        
        Route::post('/broadcasts/test-send', [AdminBroadcastController::class, 'testSend'])
		->name('broadcasts.test_send');
		
        Route::post('/broadcasts/{broadcast}/launch', [AdminBroadcastController::class, 'launch'])
		->name('broadcasts.launch');
        
        Route::get('/broadcasts', [AdminBroadcastController::class, 'index'])
		->name('broadcasts.index');
        
        Route::get('/broadcasts/create', [AdminBroadcastController::class, 'create'])
		->name('broadcasts.create');
        
        Route::post('/broadcasts', [AdminBroadcastController::class, 'store'])
		->name('broadcasts.store');
        
        Route::get('/broadcasts/{broadcast}/edit', [AdminBroadcastController::class, 'edit'])
		->name('broadcasts.edit');
        
        Route::patch('/broadcasts/{broadcast}', [AdminBroadcastController::class, 'update'])
		->name('broadcasts.update');
        Route::get('/notification-templates', [AdminNotificationTemplateController::class, 'index'])
		->name('notification_templates.index');
		
        Route::get('/notification-templates/{template}/edit', [AdminNotificationTemplateController::class, 'edit'])
		->name('notification_templates.edit');
        
        Route::patch('/notification-templates/{template}', [AdminNotificationTemplateController::class, 'update'])
		->name('notification_templates.update');
		
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
    ->middleware('track.view:user,user')
    ->name('users.show');
	
	/*
		|--------------------------------------------------------------------------
		| Public pages
		|--------------------------------------------------------------------------
	*/
	
	Route::get('/level_players', fn () => view('pages.level_players'))->name('level_players');
	
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
/*
|--------------------------------------------------------------------------
| User Social (votes + likes)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])->group(function () {
    Route::post('/user/{user}/vote', [\App\Http\Controllers\UserSocialController::class, 'vote'])
        ->whereNumber('user')
        ->name('user.vote');
    Route::post('/user/{user}/like', [\App\Http\Controllers\UserSocialController::class, 'like'])
        ->whereNumber('user')
        ->name('user.like');
});

/*
|--------------------------------------------------------------------------
| Volleyball Schools
|--------------------------------------------------------------------------
*/
// Статичные роуты ПЕРЕД динамическим {slug}
Route::get('/volleyball_school', [\App\Http\Controllers\VolleyballSchoolController::class, 'index'])
    ->name('volleyball_school.index');

Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])->group(function () {
    Route::get('/volleyball_school/create', [\App\Http\Controllers\VolleyballSchoolController::class, 'create'])
        ->name('volleyball_school.create');
    Route::post('/volleyball_school', [\App\Http\Controllers\VolleyballSchoolController::class, 'store'])
        ->name('volleyball_school.store');
    Route::get('/volleyball_school/my/edit', [\App\Http\Controllers\VolleyballSchoolController::class, 'edit'])
        ->name('volleyball_school.edit');
    Route::put('/volleyball_school/my/edit', [\App\Http\Controllers\VolleyballSchoolController::class, 'update'])
        ->name('volleyball_school.update');
});

// Динамический {slug} — ПОСЛЕ статичных
Route::get('/volleyball_school/{slug}', [\App\Http\Controllers\VolleyballSchoolController::class, 'show'])
    ->name('volleyball_school.show');

/*
|--------------------------------------------------------------------------
| Payment Settings
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])->group(function () {
    Route::get('/profile/payment-settings', [\App\Http\Controllers\PaymentSettingsController::class, 'show'])
        ->name('profile.payment_settings');
    Route::post('/profile/payment-settings', [\App\Http\Controllers\PaymentSettingsController::class, 'update'])
        ->name('profile.payment_settings.update');

    Route::post('/payments/{payment}/user-confirm', [\App\Http\Controllers\PaymentController::class, 'userConfirm'])
        ->name('payments.user_confirm');
    Route::post('/payments/{payment}/org-confirm', [\App\Http\Controllers\PaymentController::class, 'orgConfirm'])
        ->name('payments.org_confirm');
});

// Дополнительные роуты платежей
Route::post('/payments/yoomoney/webhook', [\App\Http\Controllers\PaymentController::class, 'yoomoneyWebhook'])
    ->name('payments.yoomoney.webhook')
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);

Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])->group(function () {
    Route::post('/payments/{payment}/org-reject', [\App\Http\Controllers\PaymentController::class, 'orgReject'])
        ->name('payments.org_reject');
    Route::get('/profile/transactions', [\App\Http\Controllers\PaymentController::class, 'transactions'])
        ->name('profile.transactions');
    Route::get('/wallet', [\App\Http\Controllers\PaymentController::class, 'wallet'])
        ->name('wallet.index');
});

Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])->group(function () {
    Route::post('/payments/{payment}/refund', [\App\Http\Controllers\PaymentController::class, 'refund'])
        ->name('payments.refund');
});

/*
|--------------------------------------------------------------------------
| Dashboards
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])->group(function () {
    Route::get('/org/dashboard', [\App\Http\Controllers\OrgDashboardController::class, 'index'])
        ->name('org.dashboard');
    Route::get('/player/dashboard', [\App\Http\Controllers\PlayerDashboardController::class, 'index'])
        ->name('player.dashboard');
});

/*
|--------------------------------------------------------------------------
| Абонементы и купоны
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])->group(function () {

    // Шаблоны абонементов (организатор/админ)
    Route::get('/subscriptions/templates', [\App\Http\Controllers\SubscriptionTemplateController::class, 'index'])
        ->name('subscription_templates.index');
    Route::get('/subscriptions/templates/create', [\App\Http\Controllers\SubscriptionTemplateController::class, 'create'])
        ->name('subscription_templates.create');
    Route::post('/subscriptions/templates', [\App\Http\Controllers\SubscriptionTemplateController::class, 'store'])
        ->name('subscription_templates.store');
    Route::get('/subscriptions/templates/{subscriptionTemplate}/edit', [\App\Http\Controllers\SubscriptionTemplateController::class, 'edit'])
        ->name('subscription_templates.edit');
    Route::put('/subscriptions/templates/{subscriptionTemplate}', [\App\Http\Controllers\SubscriptionTemplateController::class, 'update'])
        ->name('subscription_templates.update');
    Route::delete('/subscriptions/templates/{subscriptionTemplate}', [\App\Http\Controllers\SubscriptionTemplateController::class, 'destroy'])
        ->name('subscription_templates.destroy');

    // Выданные абонементы
    Route::get('/subscriptions', [\App\Http\Controllers\SubscriptionController::class, 'index'])
        ->name('subscriptions.index');
    Route::get('/subscriptions/my', [\App\Http\Controllers\SubscriptionController::class, 'my'])
        ->name('subscriptions.my');
    Route::post('/subscriptions/issue', [\App\Http\Controllers\SubscriptionController::class, 'issue'])
        ->name('subscriptions.issue');
    Route::post('/subscriptions/{subscription}/extend', [\App\Http\Controllers\SubscriptionController::class, 'extend'])
        ->name('subscriptions.extend');
    Route::post('/subscriptions/{subscription}/freeze', [\App\Http\Controllers\SubscriptionController::class, 'freeze'])
        ->name('subscriptions.freeze');
    Route::post('/subscriptions/{subscription}/unfreeze', [\App\Http\Controllers\SubscriptionController::class, 'unfreeze'])
        ->name('subscriptions.unfreeze');
    Route::post('/subscriptions/{subscription}/transfer', [\App\Http\Controllers\SubscriptionController::class, 'transfer'])
        ->name('subscriptions.transfer');
    Route::get('/subscriptions/{subscription}/usages', [\App\Http\Controllers\SubscriptionController::class, 'usages'])
        ->name('subscriptions.usages');

    // Шаблоны купонов
    Route::get('/coupons/templates', [\App\Http\Controllers\CouponTemplateController::class, 'index'])
        ->name('coupon_templates.index');
    Route::get('/coupons/templates/create', [\App\Http\Controllers\CouponTemplateController::class, 'create'])
        ->name('coupon_templates.create');
    Route::post('/coupons/templates', [\App\Http\Controllers\CouponTemplateController::class, 'store'])
        ->name('coupon_templates.store');
    Route::get('/coupons/templates/{couponTemplate}/edit', [\App\Http\Controllers\CouponTemplateController::class, 'edit'])
        ->name('coupon_templates.edit');
    Route::put('/coupons/templates/{couponTemplate}', [\App\Http\Controllers\CouponTemplateController::class, 'update'])
        ->name('coupon_templates.update');
    Route::post('/coupons/templates/{couponTemplate}/issue', [\App\Http\Controllers\CouponTemplateController::class, 'issue'])
        ->name('coupon_templates.issue');

    // Купоны пользователя
    Route::get('/coupons/my', [\App\Http\Controllers\CouponController::class, 'my'])
        ->name('coupons.my');
    Route::get('/coupons/activate/{code}', [\App\Http\Controllers\CouponController::class, 'activate'])
        ->name('coupons.activate');
    Route::post('/coupons/{coupon}/transfer', [\App\Http\Controllers\CouponController::class, 'transfer'])
        ->name('coupons.transfer');
    Route::get('/coupons/org', [\App\Http\Controllers\CouponController::class, 'orgIndex'])
        ->name('coupons.org_index');
});

// Подтверждение автозаписи
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])->group(function () {
    Route::post('/registrations/{registration}/confirm', function(\App\Models\EventRegistration $registration, \Illuminate\Http\Request $request) {
        if ($registration->user_id !== $request->user()->id) abort(403);
        $registration->update(['confirmed_at' => now()]);
        return back()->with('status', '✅ Участие подтверждено!');
    })->name('registrations.confirm');
});

// Покупка абонемента
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])->group(function () {
    Route::post('/subscriptions/{template}/buy', [\App\Http\Controllers\SubscriptionController::class, 'buy'])
        ->name('subscriptions.buy');
});

// Массовая выдача купонов + активация по коду
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])->group(function () {
    Route::post('/coupons/templates/{couponTemplate}/bulk-issue', [\App\Http\Controllers\CouponTemplateController::class, 'bulkIssue'])
        ->name('coupon_templates.bulk_issue');
});

// Активация купона по коду (публичный — но требует авторизации)
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])->group(function () {
    Route::get('/coupon/{code}', [\App\Http\Controllers\CouponController::class, 'activate'])
        ->name('coupons.activate.short');
});

Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])->group(function () {
    Route::post('/coupons/templates/{couponTemplate}/issue-link', [\App\Http\Controllers\CouponTemplateController::class, 'issueLink'])
        ->name('coupon_templates.issue_link');
});
