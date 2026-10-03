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
        $data = [];
        foreach (LevelTestService::DISCIPLINES as $d) {
            $data[$d] = $this->service->questions($d);
        }

        return view('pages.level_test', ['questions' => $data]);
    }

    public function result(Request $request): JsonResponse
    {
        $v = $request->validate([
            'discipline' => ['required', 'in:classic,beach'],
            'answers' => ['required', 'array', 'size:12'],
            'answers.*' => ['required', 'integer', 'between:0,3'],
        ]);

        $res = $this->service->evaluate($v['discipline'], $v['answers']);

        return response()->json($res + $this->service->describe($res['level']));
    }
}
