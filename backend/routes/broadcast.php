<?php

use App\Http\Controllers\BroadcastOverlayController;
use Illuminate\Support\Facades\Route;

/*
| Трансляция: публичные страницы по токен-ссылке. Регистрируются в bootstrap/app.php БЕЗ группы web —
| нет сессии/кук/CSRF/SetLocale/EnsureProfileCompleted. Доступ — middleware broadcast.link:{role}.
*/
Route::middleware(['throttle:120,1', 'broadcast.link:overlay'])->group(function () {
    Route::get('/o/{token}', [BroadcastOverlayController::class, 'page'])
        ->where('token', '[^/]+')->name('broadcast.overlay');
    Route::get('/o/{token}/state', [BroadcastOverlayController::class, 'state'])
        ->where('token', '[^/]+')->name('broadcast.overlay.state');
});
