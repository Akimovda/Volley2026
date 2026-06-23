<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserConsent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityConsentController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $version = config('activity.consent_version');

        if (!$request->user()->hasHealthConsent()) {
            return response()->json([
                'consent'         => false,
                'error'           => 'consent_required',
                'consent_version' => $version,
            ], 403);
        }

        return response()->json([
            'consent'         => true,
            'consent_version' => $version,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user    = $request->user();
        $version = config('activity.consent_version');

        if (!$user->hasHealthConsent()) {
            UserConsent::create([
                'user_id'          => $user->id,
                'type'             => 'health_activity',
                'document_version' => $version,
                'locale'           => app()->getLocale(),
                'accepted_at'      => now(),
                'created_at'       => now(),
            ]);
        }

        return response()->json(['ok' => true]);
    }
}
