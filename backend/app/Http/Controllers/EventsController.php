<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Support\Facades\DB;

class EventsController extends Controller
{
public function index()
{
    $events = Event::query()
        ->orderByDesc('id')
        ->get();

    $joinedEventIds = [];

    if (auth()->check()) {
        $joinedEventIds = DB::table('event_registrations')
            ->where('user_id', auth()->id())
            ->pluck('event_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    return view('events.index', [
        'events' => $events,
        'joinedEventIds' => $joinedEventIds,
    ]);
}
}
