<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\EventShowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PublicEventController extends Controller
{
    public function show(Request $request, string $token)
    {
        $query = Event::query()->where('public_token', $token);

        if (Schema::hasColumn('events', 'is_private')) {
            $query->where('is_private', 1);
        }

        $event = $query->firstOrFail();

        // Записываем факт доступа по токену
        if (auth()->check()) {
            DB::table('event_private_accesses')->upsert(
                [
                    'user_id'    => auth()->id(),
                    'event_id'   => $event->id,
                    'created_at' => now(),
                ],
                ['user_id', 'event_id'],
                ['created_at']
            );
        }

        $data = app(EventShowService::class)->handle($request, $event);

        return view('events.show', $data);
    }
}
