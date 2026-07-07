<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivitySession;
use App\Services\ActivitySessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityJumpController extends Controller
{
    public function __construct(private readonly ActivitySessionService $service) {}

    public function store(Request $request, ActivitySession $session): JsonResponse
    {
        if ($session->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'jumps'              => ['required', 'array', 'max:5000'],
            'jumps.*.t'          => ['required', 'numeric', 'min:0'],
            'jumps.*.height_cm'  => ['nullable', 'numeric', 'min:0', 'max:150'],
            'jumps.*.type'       => ['nullable', 'string', 'max:32'],
        ]);

        $accepted = $this->service->ingestJumps($session, $data['jumps']);

        return response()->json(['accepted' => $accepted]);
    }
}
