<?php
//api.php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserSearchController;
use App\Http\Controllers\Api\OccurrenceParticipantsController;
use App\Http\Controllers\Api\MaxBindWebhookController;
use App\Http\Controllers\Api\TelegramNotifyWebhookController;
use App\Http\Controllers\Api\VkNotifyWebhookController;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/users/search', [UserSearchController::class, 'search'])
    ->name('api.users.search');
    
Route::post(
    '/integrations/telegram/complete-notify-bind',
    [\App\Http\Controllers\Api\TelegramNotifyWebhookController::class, 'complete']
);
Route::post('/integrations/max/bind-info', [MaxBindWebhookController::class, 'bindInfo']);
Route::post('/integrations/max/complete-personal-bind', [MaxBindWebhookController::class, 'completePersonalBind']);

Route::get(
    '/occurrences/{occurrence}/participants',
    [OccurrenceParticipantsController::class, 'index']
);

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
Route::post('/integrations/channels/set-thread', [\App\Http\Controllers\Api\ChannelSetThreadController::class, '__invoke']);

// Push-уведомления — device tokens (поддержка и sanctum-токена, и web-сессии)
Route::middleware('auth:sanctum,web')->group(function () {
    Route::post('/device-token', [\App\Http\Controllers\Api\DeviceTokenController::class, 'store']);
    Route::delete('/device-token', [\App\Http\Controllers\Api\DeviceTokenController::class, 'destroy']);
});

// Face ID / биометрическая авторизация
Route::post('/biometric/login', [\App\Http\Controllers\Api\BiometricController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/biometric/register', [\App\Http\Controllers\Api\BiometricController::class, 'register']);
    Route::delete('/biometric/revoke', [\App\Http\Controllers\Api\BiometricController::class, 'revoke']);
});
