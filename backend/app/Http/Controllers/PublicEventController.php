<?php
// app/Http/Controllers/PublicEventController.php
namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\EventShowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class PublicEventController extends Controller
{
    /**
     * GET /e/{token}
     * Публичный просмотр приватного события по токену
     */
    public function show(Request $request, string $token)
    {
        /*
        |--------------------------------------------------------------------------
        | 1. Найти событие по публичному токену
        |--------------------------------------------------------------------------
        */

        $query = Event::query()
            ->where('public_token', $token);

        if (Schema::hasColumn('events', 'is_private')) {
            $query->where('is_private', 1);
        }

        $event = $query->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | 2. Передать всё в EventShowService
        |--------------------------------------------------------------------------
        */

        $data = app(EventShowService::class)->handle($request, $event);

        /*
        |--------------------------------------------------------------------------
        | 3. Вернуть Blade
        |--------------------------------------------------------------------------
        */

        return view('events.show', $data);
    }
}