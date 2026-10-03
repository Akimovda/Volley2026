<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LevelTestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/** Эндпоинты для ботов Telegram/MAX (секрет X-Bind-Secret). Язык — параметр lang (ru|en, по умолчанию ru). */
class LevelTestBotController extends Controller
{
    public function __construct(private LevelTestService $service) {}

    public function questions(Request $request): JsonResponse
    {
        $this->authorizeBot($request);
        $v = $request->validate([
            'discipline' => ['required', 'in:classic,beach'],
            'lang' => ['nullable', 'in:ru,en'],
        ]);

        return response()->json([
            'ok' => true,
            'questions' => $this->service->questions($v['discipline'], $v['lang'] ?? 'ru'),
        ]);
    }

    public function score(Request $request): JsonResponse
    {
        $this->authorizeBot($request);
        $v = $request->validate([
            'discipline' => ['required', 'in:classic,beach'],
            'lang' => ['nullable', 'in:ru,en'],
            'answers' => ['required', 'array', 'size:16'],
            'answers.*' => ['required', 'integer', 'between:0,3'],
        ]);

        $res = $this->service->evaluate($v['discipline'], $v['answers']);

        return response()->json(['ok' => true] + $res + $this->service->describe($res['level'], $v['lang'] ?? 'ru') + [
            'url' => route('level_test'),
        ]);
    }

    private function authorizeBot(Request $request): void
    {
        $expected = (string) config('services.bind.secret', '');
        abort_unless(
            $expected !== '' && hash_equals($expected, (string) $request->header('X-Bind-Secret', '')),
            403,
            'Invalid bind secret'
        );
    }
}
