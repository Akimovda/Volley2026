<?php
//api.php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\OccurrenceParticipantsController;
use App\Http\Controllers\Api\MaxBindWebhookController;
use App\Http\Controllers\Api\TelegramNotifyWebhookController;
use App\Http\Controllers\Api\VkNotifyWebhookController;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post(
    '/integrations/telegram/complete-notify-bind',
    [\App\Http\Controllers\Api\TelegramNotifyWebhookController::class, 'complete']
);
Route::post('/integrations/max/bind-info', [MaxBindWebhookController::class, 'bindInfo']);
Route::post('/integrations/max/complete-personal-bind', [MaxBindWebhookController::class, 'completePersonalBind']);

Route::get(
    '/occurrences/{occurrence}/participants',
    [OccurrenceParticipantsController::class, 'index']
)->middleware('throttle:30,1');

Route::post('/integrations/channels/complete-bind', [\App\Http\Controllers\Api\ChannelBindWebhookController::class, 'complete']);
Route::post('/integrations/vk/complete-notify-bind', [VkNotifyWebhookController::class, 'complete']);
Route::get('/occurrences/{occurrence}/stats', function (string $occurrence) {
    $count = cache()->remember(
        "occurrence_stats_$occurrence",
        3,
        fn () => app(\App\Services\EventOccurrenceStatsService::class)
            ->getRegisteredCount($occurrence)
    );

    return response()->json([
        'registered_total' => $count,
    ]);
});
Route::get('/app/level-scheme', [\App\Http\Controllers\LevelSchemeController::class, 'api'])->middleware('throttle:120,1');
Route::post('/integrations/level-test/questions', [\App\Http\Controllers\Api\LevelTestBotController::class, 'questions'])->middleware('throttle:120,1');
Route::post('/integrations/level-test/score', [\App\Http\Controllers\Api\LevelTestBotController::class, 'score'])->middleware('throttle:120,1');
Route::post('/integrations/channels/set-thread', [\App\Http\Controllers\Api\ChannelSetThreadController::class, '__invoke']);

// Push-уведомления — device tokens + уведомления (поддержка и sanctum-токена, и web-сессии)
Route::middleware('auth:sanctum,web')->group(function () {
    Route::post('/device-token', [\App\Http\Controllers\Api\DeviceTokenController::class, 'store']);
    Route::delete('/device-token', [\App\Http\Controllers\Api\DeviceTokenController::class, 'destroy']);
    Route::get('/notifications/unread-count', [\App\Http\Controllers\Api\NotificationsApiController::class, 'unreadCount']);
    Route::get('/notifications', [\App\Http\Controllers\Api\NotificationsApiController::class, 'index']);
    Route::post('/notifications/read-all', [\App\Http\Controllers\Api\NotificationsApiController::class, 'markAllRead']);
    Route::post('/notifications/{id}/read', [\App\Http\Controllers\Api\NotificationsApiController::class, 'markRead']);
    Route::delete('/notifications/{id}', [\App\Http\Controllers\Api\NotificationsApiController::class, 'destroy']);
});

// Activity / HR tracking
Route::middleware('auth:sanctum,web')->group(function () {
    Route::get('/activity/consent',   [\App\Http\Controllers\Api\ActivityConsentController::class, 'show']);
    Route::post('/activity/consent',  [\App\Http\Controllers\Api\ActivityConsentController::class, 'store']);
    Route::post('/activity/devices',           [\App\Http\Controllers\Api\ActivityDeviceController::class, 'upsert']);
    Route::delete('/activity/devices/{device}', [\App\Http\Controllers\Api\ActivityDeviceController::class, 'destroy']);
    Route::post('/activity/sessions', [\App\Http\Controllers\Api\ActivitySessionController::class, 'start']);
    Route::post('/activity/sessions/{session}/samples',  [\App\Http\Controllers\Api\ActivitySessionController::class, 'ingestSamples']);
    Route::post('/activity/sessions/{session}/jumps',    [\App\Http\Controllers\Api\ActivityJumpController::class, 'store']);
    Route::post('/activity/sessions/{session}/finalize', [\App\Http\Controllers\Api\ActivitySessionController::class, 'finalize']);
    Route::delete('/activity/sessions/{session}', [\App\Http\Controllers\Api\ActivitySessionController::class, 'destroy']);
    Route::get('/activity/stats/weekly', [\App\Http\Controllers\Api\ActivityStatsController::class, 'weekly'])
        ->middleware('abilities:activity:read');
    Route::post('/activity/import/healthkit', [\App\Http\Controllers\Api\ActivityImportController::class, 'importHealthKit']);

    // Выдача PAT для iOS-виджета (вызывается из WKWebView по сессионной cookie)
    Route::post('/widget/token', [\App\Http\Controllers\Api\WidgetTokenController::class, 'issue']);
});

// Face ID / биометрическая авторизация
Route::post('/biometric/login', [\App\Http\Controllers\Api\BiometricController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/biometric/register', [\App\Http\Controllers\Api\BiometricController::class, 'register']);
    Route::delete('/biometric/revoke', [\App\Http\Controllers\Api\BiometricController::class, 'revoke']);
});

// Проверка версии нативного приложения (до логина, статический конфиг, без БД)
Route::get('/app-version', function (Request $request) {
    $platform = $request->query('platform');
    if (!in_array($platform, ['ios', 'android'], true)) {
        return response()->json(['error' => 'platform must be ios or android'], 400);
    }
    $c = config("app_version.$platform");

    return response()->json([
        'latest_version' => $c['latest'],
        'min_version' => $c['min'],
        'update_url' => $c['update_url'],
    ]);
})->middleware('throttle:120,1');
