<?php

use App\Http\Controllers\BroadcastOverlayController;
use App\Http\Controllers\ScorerController;
use Illuminate\Support\Facades\Route;

/*
| Трансляция: публичные страницы по токен-ссылке. Регистрируются в bootstrap/app.php БЕЗ группы web —
| нет сессии/кук/CSRF/SetLocale/EnsureProfileCompleted. Доступ — middleware broadcast.link:{role}.
*/
// Лимитеры overlay-page / overlay-state — в AppServiceProvider (счётчик на токен+IP, не общий на IP)
Route::middleware(['broadcast.link:overlay'])->group(function () {
    Route::get('/o/{token}', [BroadcastOverlayController::class, 'page'])
        ->middleware('throttle:overlay-page')->where('token', '[^/]+')->name('broadcast.overlay');
    Route::get('/o/{token}/state', [BroadcastOverlayController::class, 'state'])
        ->middleware('throttle:overlay-state')->where('token', '[^/]+')->name('broadcast.overlay.state');
});

/*
| Ведущий матча по токен-ссылке площадки. В отличие от оверлея — ГРУППА web (гостевая сессия + обычный CSRF:
| токен из мета-тега и заголовок X-CSRF-TOKEN, исключений из CSRF нет). scorer.link — токен/срок/приглашённый пользователь.
*/
// Лимитеры scorer-ip / scorer объявлены в AppServiceProvider (при route:cache этот файл не исполняется — RateLimiter::for здесь не сработал бы)
Route::middleware(['web', 'throttle:scorer-ip', 'throttle:scorer', 'scorer.link'])->prefix('s/{token}')->where(['token' => '[A-Za-z0-9]{40}', 'match' => '[0-9]+'])->group(function () {
    Route::get('/', [ScorerController::class, 'hub'])->name('scorer.hub');
    Route::post('/name', [ScorerController::class, 'setName'])->name('scorer.name');
    Route::get('/m/{match}', [ScorerController::class, 'match'])->name('scorer.match');
    Route::get('/m/{match}/fragment', [ScorerController::class, 'fragment'])->name('scorer.fragment');
    Route::post('/m/{match}/point', [ScorerController::class, 'point'])->name('scorer.point');
    Route::post('/m/{match}/undo', [ScorerController::class, 'undo'])->name('scorer.undo');
    Route::post('/m/{match}/lineup/start', [ScorerController::class, 'lineupStart'])->name('scorer.lineup.start');
    Route::post('/m/{match}/lineup/event', [ScorerController::class, 'lineupEvent'])->name('scorer.lineup.event');
    Route::post('/m/{match}/jersey', [ScorerController::class, 'jersey'])->name('scorer.jersey');
    Route::post('/m/{match}/serve-first', [ScorerController::class, 'serveFirst'])->name('scorer.serve.first');
    Route::post('/m/{match}/match-start', [ScorerController::class, 'matchStart'])->name('scorer.match.start');
    Route::post('/m/{match}/match-unstart', [ScorerController::class, 'matchUnstart'])->name('scorer.match.unstart');
    Route::post('/m/{match}/serve-mark', [ScorerController::class, 'serveMark'])->name('scorer.serve.mark');
    Route::post('/m/{match}/finalize', [ScorerController::class, 'finalize'])->name('scorer.finalize');
});
