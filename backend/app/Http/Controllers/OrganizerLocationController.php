<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Location;
use App\Models\User;
use App\Services\EventAccessService;
use App\Services\UserNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Быстрое создание локации организатором прямо из мастера создания мероприятия.
 * Открывается в модалке (iframe, ?embed=1) на шаге 2 — данные мастера не теряются.
 * Локация общая (organizer_id = NULL, как у созданных админом) и сразу видна в выборе;
 * админам уходит уведомление «дозаполнить» (фото, описание, корты).
 */
class OrganizerLocationController extends Controller
{
    public function __construct(
        private readonly EventAccessService $accessService,
        private readonly UserNotificationService $notifications,
    ) {}

    public function create(Request $request)
    {
        $user = $request->user();
        $this->accessService->ensureCanCreateEvents($user);

        $city = $this->resolveCity((int) $request->query('city_id', 0), $user);
        if (!$city) {
            return view('locations.organizer_create', ['city' => null, 'embed' => $request->boolean('embed')]);
        }

        return view('locations.organizer_create', ['city' => $city, 'embed' => $request->boolean('embed')]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $this->accessService->ensureCanCreateEvents($user);

        $data = $request->validate([
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'name'    => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'lat'     => ['required', 'numeric', 'between:-90,90'],
            'lng'     => ['required', 'numeric', 'between:-180,180'],
        ], [
            'lat.required' => __('locations.org_coords_both'),
            'lng.required' => __('locations.org_coords_both'),
        ]);

        $name = trim($data['name']);

        // Защита от дублей: тот же город + то же название (без учёта регистра)
        // (сравнение в PHP: LOWER() в PostgreSQL не понижает регистр кириллицы при C-локали БД)
        $needle = mb_strtolower($name);
        $dup = Location::query()
            ->where('city_id', (int) $data['city_id'])
            ->pluck('name')
            ->contains(fn ($n) => mb_strtolower(trim((string) $n)) === $needle);
        if ($dup) {
            return back()->withInput()->withErrors(['name' => __('locations.org_duplicate')]);
        }

        $location = new Location();
        $location->organizer_id = null; // общая локация: организаторам разрешены только такие (EventLocationService)
        $location->created_by_user_id = $user->id;
        $location->name = $name;
        $location->address = trim($data['address']);
        $location->city_id = (int) $data['city_id'];
        $location->lat = $data['lat'] ?? null;
        $location->lng = $data['lng'] ?? null;
        $location->save();

        $this->notifyAdmins($location, $user);

        $embed = $request->boolean('embed');
        if ($embed) {
            return view('locations.organizer_created', ['location' => $location]);
        }

        return redirect()
            ->route('events.create', ['step' => 2, 'city_id' => $location->city_id])
            ->with('status', __('locations.org_created_ok', ['name' => $location->name]));
    }

    private function resolveCity(int $cityId, User $user): ?City
    {
        $city = $cityId > 0 ? City::find($cityId) : null;
        return $city ?: ($user->city_id ? City::find((int) $user->city_id) : null);
    }

    private function notifyAdmins(Location $location, User $creator): void
    {
        try {
            $location->loadMissing('city');
            $admins = User::query()->where('role', 'admin')->get();
            foreach ($admins as $admin) {
                if ((int) $admin->id === (int) $creator->id) {
                    continue; // админ сам себе не пишет
                }
                try {
                    $this->notifications->createLocationCreatedByOrganizerNotification($admin, $location, $creator);
                } catch (\Throwable $e) {
                    Log::warning('location_created notify failed for admin ' . $admin->id . ': ' . $e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            Log::warning('OrganizerLocationController::notifyAdmins: ' . $e->getMessage());
        }
    }
}
