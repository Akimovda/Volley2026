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

    /** Заглушка: страница оверлея — этап А2. */
    public function page(Request $request): Response
    {
        return response(
            '<!doctype html><html><head><meta charset="utf-8"><meta name="robots" content="noindex,nofollow">'
            . '<meta name="referrer" content="no-referrer"><title></title>'
            . '<style>html,body{margin:0;background:transparent}</style></head><body></body></html>',
            200,
            self::HEADERS + ['Content-Type' => 'text/html; charset=UTF-8']
        );
    }

    public function state(Request $request, CourtStateBroadcaster $broadcaster): JsonResponse
    {
        /** @var BroadcastLink $link */
        $link = $request->attributes->get('broadcast_link');
        $court = $link->court;
        abort_unless($court && $court->is_active, 404);

        return response()->json($broadcaster->cached($court), 200, self::HEADERS + [
            // CORS: оверлей может открываться из WebView/другого домена
            'Access-Control-Allow-Origin' => '*',
        ]);
    }
}
