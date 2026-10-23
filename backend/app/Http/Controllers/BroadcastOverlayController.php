<?php

namespace App\Http\Controllers;

use App\Models\BroadcastLink;
use App\Services\Broadcast\CourtStateBroadcaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Публичные (по токену) страницы оверлея. Вне группы web: без сессии/кук/редиректов. */
class BroadcastOverlayController extends Controller
{
    private const HEADERS = [
        'Referrer-Policy' => 'no-referrer',
        'Cache-Control'   => 'no-store, no-cache, must-revalidate, max-age=0',
        'X-Robots-Tag'    => 'noindex, nofollow',
    ];

    /** Страница оверлея: отдельный blade без общего layout. */
    public function page(Request $request, CourtStateBroadcaster $broadcaster): Response
    {
        $this->noDebugbar();
        /** @var BroadcastLink $link */
        $link = $request->attributes->get('broadcast_link');
        $court = $link->court;
        if (!$court || !$court->is_active) {
            return \App\Http\Middleware\BroadcastLinkAccess::blank($request);
        }

        // Язык — RU по умолчанию, ?lang=en переопределяет (сессии/SetLocale на этом роуте нет)
        $lang = $request->query('lang') === 'en' ? 'en' : 'ru';
        app()->setLocale($lang);

        $config = [
            'stateUrl' => route('broadcast.overlay.state', ['token' => $link->token], false),
            'channel'  => 'court.' . $court->channel_key,
            'key'      => (string) config('reverb.apps.apps.0.key'),
            'preview'  => $request->boolean('preview'),
            'audio'    => $request->query('audio') !== '0',
            'strings'  => trans('broadcast'),
            'initial'  => $broadcaster->cached($court),
            'demo'     => null,
        ];
        // Пример состояния — ТОЛЬКО вместе с preview=1 (в эфир не попадает): без канала и без опроса /state
        $demo = (string) $request->query('demo');
        if ($config['preview'] && in_array($demo, \App\Services\Broadcast\CourtStateService::DEMOS, true)) {
            $config['demo'] = $demo;
            $config['initial'] = app(\App\Services\Broadcast\CourtStateService::class)->demo($court, $demo);
            $config['channel'] = null;
            $config['key'] = '';
            $config['stateUrl'] = null;
        }

        return response(view('broadcast.overlay', ['config' => $config, 'preview' => $config['preview'], 'audio' => $config['audio']])->render(), 200,
            self::HEADERS + ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function state(Request $request, CourtStateBroadcaster $broadcaster): Response|JsonResponse
    {
        $this->noDebugbar();
        /** @var BroadcastLink $link */
        $link = $request->attributes->get('broadcast_link');
        $court = $link->court;
        if (!$court || !$court->is_active) {
            return \App\Http\Middleware\BroadcastLinkAccess::blank($request);
        }

        return response()->json($broadcaster->cached($court) + [
            // Имя публичного канала Reverb (court.{channel_key}) — для подписки приложения; в рассылку состояния не входит
            'channel_key' => $court->channel_key,
            'channel'     => 'court.' . $court->channel_key,
            'reverb'      => \App\Services\Broadcast\ReverbClientConfig::get(),
        ], 200, self::HEADERS + [
            // CORS: оверлей может открываться из WebView/другого домена
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    /** Панель отладки не должна попадать в кадр эфира (на dev она внедряется в любой HTML). */
    private function noDebugbar(): void
    {
        if (class_exists(\Barryvdh\Debugbar\Facades\Debugbar::class)) {
            \Barryvdh\Debugbar\Facades\Debugbar::disable();
        }
    }
}
