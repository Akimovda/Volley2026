<?php

namespace App\Http\Controllers;

use App\Services\LevelTestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/** Публичная страница /level-test и JSON-подсчёт результата для неё. */
class LevelTestController extends Controller
{
    public function __construct(private LevelTestService $service) {}

    public function show()
    {
        $questions = [];
        $ui = [];
        foreach (['ru', 'en'] as $lang) {
            foreach (LevelTestService::DISCIPLINES as $d) {
                $questions[$lang][$d] = $this->service->questions($d, $lang);
            }
            $ui[$lang] = [
                'intro' => __('leveltest.intro', [], $lang),
                'choose_discipline' => __('leveltest.choose_discipline', [], $lang),
                'classic' => __('leveltest.classic', [], $lang),
                'beach' => __('leveltest.beach', [], $lang),
                'count' => __('leveltest.question_n', ['n' => ':n', 'total' => ':total'], $lang),
                'back' => __('leveltest.back', [], $lang),
                'restart' => __('leveltest.restart', [], $lang),
                'your_result' => __('leveltest.your_result', [], $lang),
                'points' => __('leveltest.points', ['score' => ':score', 'max' => ':max'], $lang),
                'capped' => __('leveltest.capped_note', [], $lang),
                'find_events' => __('leveltest.find_events', [], $lang),
                'levels_info' => __('leveltest.levels_info', [], $lang),
                'error' => __('leveltest.error', [], $lang),
            ];
        }

        return view('pages.level_test', [
            'questions' => $questions,
            'ui' => $ui,
        ]);
    }

    public function result(Request $request): JsonResponse
    {
        $v = $request->validate([
            'discipline' => ['required', 'in:classic,beach'],
            'lang' => ['nullable', 'in:ru,en'],
            'answers' => ['required', 'array', 'size:16'],
            'answers.*' => ['required', 'integer', 'between:0,3'],
        ]);

        $res = $this->service->evaluate($v['discipline'], $v['answers']);

        return response()->json($res + $this->service->describe($res['level'], $v['lang'] ?? 'ru'));
    }
}
