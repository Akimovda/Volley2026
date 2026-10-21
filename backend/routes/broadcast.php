<?php

use App\Http\Controllers\BroadcastOverlayController;
use App\Http\Controllers\ScorerController;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
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

/*
| Ведущий матча по токен-ссылке площадки. В отличие от оверлея — ГРУППА web (гостевая сессия + обычный CSRF:
| токен из мета-тега и заголовок X-CSRF-TOKEN, исключений из CSRF нет). scorer.link — токен/срок/приглашённый пользователь.
*/
// Лимиты: общий на IP (на площадке несколько кортов за одним Wi-Fi) и свой на каждый токен
// Именованные лимитеры — свой счётчик: безымянный throttle:N,M делит ключ (IP) со ВСЕМИ такими же, и трафик ввода душил бы оверлей (120/мин) на той же сети
RateLimiter::for('scorer-ip', fn ($request) => Limit::perMinute(900)->by('scorer-ip:' . $request->ip()));
RateLimiter::for('scorer', fn ($request) => Limit::perMinute(240)->by('scorer:' . (string) $request->route('token')));

Route::middleware(['web', 'throttle:scorer-ip', 'throttle:scorer', 'scorer.link'])->prefix('s/{token}')->where(['token' => '[A-Za-z0-9]{40}', 'match' => '[0-9]+'])->group(function () {
    Route::get('/', [ScorerController::class, 'hub'])->name('scorer.hub');
    Route::post('/name', [ScorerController::class, 'setName'])->name('scorer.name');
    Route::get('/m/{match}', [ScorerController::class, 'match'])->name('scorer.match');
    Route::get('/m/{match}/fragment', [ScorerController::class, 'fragment'])->name('scorer.fragment');
    Route::post('/m/{match}/point', [ScorerController::class, 'point'])->name('scorer.point');
    Route::post('/m/{match}/undo', [ScorerController::class, 'undo'])->name('scorer.undo');
    Route::post('/m/{match}/lineup/start', [ScorerController::class, 'lineupStart'])->name('scorer.lineup.start');
    Route::post('/m/{match}/lineup/event', [ScorerController::class, 'lineupEvent'])->name('scorer.lineup.event');
    Route::post('/m/{match}/finalize', [ScorerController::class, 'finalize'])->name('scorer.finalize');
});
