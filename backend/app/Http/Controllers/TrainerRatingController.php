<?php

namespace App\Http\Controllers;

use App\Models\EventOccurrence;
use App\Models\User;
use App\Services\TrainerRatingService;
use Illuminate\Http\Request;

class TrainerRatingController extends Controller
{
    public function store(Request $request, EventOccurrence $occurrence, User $trainer, TrainerRatingService $service)
    {
        $data = $request->validate([
            'score'   => ['required', 'integer', 'between:1,10'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $rating = $service->rate(
            occurrenceId: $occurrence->id,
            trainerUserId: $trainer->id,
            raterUserId: (int) $request->user()->id,
            score: (int) $data['score'],
            comment: $data['comment'] ?? null,
        );

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'score' => $rating->score]);
        }

        return back()->with('status', __('trainers.rating_saved'));
    }
}
