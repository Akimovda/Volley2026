<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\ClubOrganizerTrust;
use App\Models\Location;
use App\Models\LocationCourt;
use App\Models\LocationDirection;
use App\Models\LocationWorkingHour;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class AdminLocationController extends Controller
{
public function index()
{
    $q = Location::query()
        ->with(['city:id,name,region,country_code,timezone']) // добавили region и country_code
        ->orderByRaw('COALESCE((select name from cities where cities.id = locations.city_id), \'\') asc')
        ->orderBy('name');

    return view('admin.locations.index', [
        'locations' => $q->paginate(30)->withQueryString(),
    ]);
}

    public function create()
    {
        // можно передать дефолтный город "Москва" (если у вас есть такой флаг/поиск)
        return view('admin.locations.create');
    }

public function store(Request $request)
{
    $data = $request->validate([
        'name'           => ['required', 'string', 'max:255'],
        'address'        => ['nullable', 'string', 'max:255'],
        'city_id'        => ['required', 'integer', 'exists:cities,id'],
        'timezone'       => ['nullable', 'string', 'max:64'],
        'short_text'     => ['nullable', 'string', 'max:255'],
        'long_text'      => ['nullable', 'string'],
        'long_text_full' => ['nullable', 'string'], // ДОБАВЛЕНО
        'note'           => ['nullable', 'string'], // ДОБАВЛЕНО
        'lat'            => ['nullable', 'numeric', 'between:-90,90'],
        'lng'            => ['nullable', 'numeric', 'between:-180,180'],
        'photos'         => ['nullable', 'array'],
        'photos.*'       => ['nullable', 'image', 'max:5120'],
    ]);

    $city = City::query()->whereKey((int)$data['city_id'])->first();

    $location = new Location();
    $location->organizer_id = null;
    $location->name = $data['name'];
    $location->address = $data['address'] ?? null;

    if (Schema::hasColumn('locations', 'city_id')) {
        $location->city_id = (int)$data['city_id'];
    }

    if (Schema::hasColumn('locations', 'timezone')) {
        $tz = trim((string)($data['timezone'] ?? ''));
        if ($tz === '') $tz = (string)($city->timezone ?? 'Europe/Moscow');
        $location->timezone = $tz;
    }

    $location->short_text = $data['short_text'] ?? null;
    $location->long_text  = $data['long_text'] ?? null;
    $location->long_text_full = $data['long_text_full'] ?? null; // ДОБАВЛЕНО
    $location->note = $data['note'] ?? null; // ДОБАВЛЕНО
    $location->lat = array_key_exists('lat', $data) ? $data['lat'] : null;
    $location->lng = array_key_exists('lng', $data) ? $data['lng'] : null;

    $location->save();

    foreach ($request->file('photos', []) as $file) {
        if (!$file) continue;
        $location->addMedia($file)->toMediaCollection('photos');
    }

    return redirect()
        ->route('admin.locations.index')
        ->with('status', 'Локация создана ✅');
}

    public function edit(Location $location)
    {
        $location->load(['city:id,name,region,country_code,timezone']);

        $photos = $location->getMedia('photos')
            ->sortBy(fn ($m) => (int)($m->order_column ?? 0))
            ->values();

        $directions = LocationDirection::where('location_id', $location->id)
            ->with(['courts' => fn ($q) => $q->orderBy('sort_order'), 'workingHours'])
            ->get()
            ->keyBy('direction');

        $clubManagers = \App\Models\User::where('is_club_manager', true)
            ->orderBy('last_name')
            ->get();

        $trustedOrganizers = ClubOrganizerTrust::where('location_id', $location->id)
            ->with('organizer')
            ->get();

        $priceRules = \App\Models\CourtPriceRule::whereIn('direction_id', $directions->pluck('id'))
            ->orderBy('priority')
            ->get()
            ->groupBy('direction_id');

        return view('admin.locations.edit', compact('location', 'photos', 'directions', 'clubManagers', 'trustedOrganizers', 'priceRules'));
    }

public function update(Request $request, Location $location)
{
    $data = $request->validate([
        'name'           => ['required', 'string', 'max:255'],
        'address'        => ['nullable', 'string', 'max:255'],
        'city_id'        => ['required', 'integer', 'exists:cities,id'],
        'timezone'       => ['nullable', 'string', 'max:64'],
        'short_text'     => ['nullable', 'string', 'max:255'],
        'long_text'      => ['nullable', 'string'],
        'long_text_full' => ['nullable', 'string'], // ДОБАВИТЬ
        'note'           => ['nullable', 'string'], // ДОБАВИТЬ
        'lat'            => ['nullable', 'numeric', 'between:-90,90'],
        'lng'            => ['nullable', 'numeric', 'between:-180,180'],
        'photos'         => ['nullable', 'array'],
        'photos.*'       => ['nullable', 'image', 'max:5120'],
        'owner_id'       => ['nullable', 'integer', 'exists:users,id'],
        'booking_cancel_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
    ]);

    if ($request->user()?->isAdmin()) {
        $location->owner_id = $data['owner_id'] ?? null;
        $location->booking_cancel_hours = $data['booking_cancel_hours'] ?? 24;
    }

    $city = City::query()->whereKey((int)$data['city_id'])->first();

    $location->name = $data['name'];
    $location->address = $data['address'] ?? null;

    if (Schema::hasColumn('locations', 'city_id')) {
        $location->city_id = (int)$data['city_id'];
    }

    if (Schema::hasColumn('locations', 'timezone')) {
        $tz = trim((string)($data['timezone'] ?? ''));
        if ($tz === '') $tz = (string)($city->timezone ?? $location->timezone ?? 'Europe/Moscow');
        $location->timezone = $tz;
    }

    $location->short_text = $data['short_text'] ?? null;
    $location->long_text  = $data['long_text'] ?? null;
    $location->long_text_full = $data['long_text_full'] ?? null; // ДОБАВИТЬ
    $location->note = $data['note'] ?? null; // ДОБАВИТЬ
    $location->lat = array_key_exists('lat', $data) ? $data['lat'] : null;
    $location->lng = array_key_exists('lng', $data) ? $data['lng'] : null;

    $location->save();

    foreach ($request->file('photos', []) as $file) {
        if (!$file) continue;
        $location->addMedia($file)->toMediaCollection('photos');
    }

    return back()->with('status', 'Локация сохранена ✅');
}

    public function saveDirections(Request $request, Location $location)
    {
        $data = $request->validate([
            'directions'                          => ['required', 'array'],
            'directions.*.enabled'                 => ['nullable', 'boolean'],
            'directions.*.courts_count'            => ['nullable', 'integer', 'min:1', 'max:20'],
            'directions.*.court_names'             => ['nullable', 'array'],
            'directions.*.court_names.*'           => ['nullable', 'string', 'max:100'],
            'directions.*.court_indoor'             => ['nullable', 'array'],
            'directions.*.court_indoor.*'           => ['nullable', 'boolean'],
            'directions.*.hours'                   => ['nullable', 'array'],
            'directions.*.hours.*.opens_at'        => ['nullable', 'date_format:H:i'],
            'directions.*.hours.*.closes_at'       => ['nullable', 'date_format:H:i'],
            'directions.*.hours.*.is_day_off'      => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($data, $location) {
            foreach ([LocationDirection::DIRECTION_CLASSIC, LocationDirection::DIRECTION_BEACH] as $directionKey) {
                $input = $data['directions'][$directionKey] ?? null;
                $enabled = (bool) ($input['enabled'] ?? false);

                $existing = LocationDirection::where('location_id', $location->id)
                    ->where('direction', $directionKey)
                    ->first();

                if (!$enabled) {
                    if ($existing) {
                        $existing->is_active = false;
                        $existing->save();
                    }
                    continue;
                }

                $isNewDirection = !$existing;
                $courtsCount = max(1, (int) ($input['courts_count'] ?? 1));

                $direction = LocationDirection::updateOrCreate(
                    ['location_id' => $location->id, 'direction' => $directionKey],
                    ['courts_count' => $courtsCount, 'is_active' => true]
                );

                // Корты: upsert по порядковому номеру (sort_order = 1..N)
                $courtNames = $input['court_names'] ?? [];
                $courtIndoor = $input['court_indoor'] ?? [];
                $existingCourts = $direction->courts()->orderBy('sort_order')->get()->keyBy('sort_order');

                for ($i = 1; $i <= $courtsCount; $i++) {
                    $name = trim((string) ($courtNames[$i - 1] ?? ''))
                        ?: __('club.court_default_name_' . $directionKey, ['n' => $i]);
                    $isIndoor = (bool) ($courtIndoor[$i - 1] ?? false);

                    $court = $existingCourts->get($i);
                    if ($court) {
                        $court->name = $name;
                        $court->is_indoor = $isIndoor;
                        $court->is_active = true;
                        $court->save();
                    } else {
                        LocationCourt::create([
                            'direction_id' => $direction->id,
                            'name'         => $name,
                            'is_indoor'    => $isIndoor,
                            'sort_order'   => $i,
                            'is_active'    => true,
                        ]);
                    }
                }

                // Количество уменьшилось — деактивируем лишние, НЕ удаляем (на них могут быть брони)
                $direction->courts()->where('sort_order', '>', $courtsCount)->update(['is_active' => false]);

                // Режим работы: upsert по (direction_id, day_of_week)
                $hoursInput = $input['hours'] ?? [];
                for ($day = 0; $day <= 6; $day++) {
                    $dayData = $hoursInput[$day] ?? null;

                    if ($dayData === null) {
                        if ($isNewDirection) {
                            // Дефолт при создании направления: Пн-Вс 08:00-23:00, без выходных
                            LocationWorkingHour::updateOrCreate(
                                ['direction_id' => $direction->id, 'day_of_week' => $day],
                                ['opens_at' => '08:00', 'closes_at' => '23:00', 'is_day_off' => false]
                            );
                        }
                        // для уже существующего направления без данных по дню — не трогаем
                        continue;
                    }

                    $isDayOff = (bool) ($dayData['is_day_off'] ?? false);
                    LocationWorkingHour::updateOrCreate(
                        ['direction_id' => $direction->id, 'day_of_week' => $day],
                        [
                            'opens_at'   => $isDayOff ? null : ($dayData['opens_at'] ?? null),
                            'closes_at'  => $isDayOff ? null : ($dayData['closes_at'] ?? null),
                            'is_day_off' => $isDayOff,
                        ]
                    );
                }
            }
        });

        return back()->with('status', __('club.save_directions') . ' ✅');
    }

    /**
     * Полная перезапись правил ценообразования направления (delete + insert
     * в транзакции). priority вычисляется автоматически по специфичности:
     * court(+4) + day(+2) + time-window(+1) — 0 (база) .. 7 (корт+день+время).
     */
    public function savePriceRules(Request $request, Location $location)
    {
        $data = $request->validate([
            'directions'                        => ['required', 'array'],
            'directions.*.base_price'           => ['nullable', 'numeric', 'min:0.01', 'max:99999'],
            'directions.*.rules'                => ['nullable', 'array'],
            'directions.*.rules.*.court_id'     => ['nullable', 'integer', 'exists:location_courts,id'],
            'directions.*.rules.*.day_of_week'  => ['nullable', 'integer', 'min:0', 'max:6'],
            'directions.*.rules.*.starts_at'    => ['nullable', 'date_format:H:i'],
            'directions.*.rules.*.ends_at'      => ['nullable', 'date_format:H:i'],
            'directions.*.rules.*.price'        => ['required', 'numeric', 'min:0.01', 'max:99999'],
        ]);

        foreach ($data['directions'] as $directionKey => $input) {
            foreach (($input['rules'] ?? []) as $i => $rule) {
                $hasStart = !empty($rule['starts_at']);
                $hasEnd = !empty($rule['ends_at']);
                if ($hasStart !== $hasEnd) {
                    return back()->with('error', __('club.price_rule_time_both_required'));
                }
                if ($hasStart && $hasEnd && $rule['ends_at'] <= $rule['starts_at']) {
                    return back()->with('error', __('club.price_rule_time_order'));
                }
            }
        }

        DB::transaction(function () use ($data, $location) {
            foreach ([LocationDirection::DIRECTION_CLASSIC, LocationDirection::DIRECTION_BEACH] as $directionKey) {
                $direction = LocationDirection::where('location_id', $location->id)
                    ->where('direction', $directionKey)
                    ->first();
                if (!$direction) {
                    continue;
                }

                $input = $data['directions'][$directionKey] ?? null;
                if ($input === null) {
                    continue;
                }

                \App\Models\CourtPriceRule::where('direction_id', $direction->id)->delete();

                $rows = [];

                if (!empty($input['base_price'])) {
                    $rows[] = [
                        'direction_id'   => $direction->id,
                        'court_id'       => null,
                        'day_of_week'    => null,
                        'starts_at'      => null,
                        'ends_at'        => null,
                        'price_per_hour' => $input['base_price'],
                        'priority'       => 0,
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ];
                }

                foreach (($input['rules'] ?? []) as $rule) {
                    if (empty($rule['price'])) {
                        continue;
                    }

                    $courtId = !empty($rule['court_id']) ? (int) $rule['court_id'] : null;
                    $dayOfWeek = isset($rule['day_of_week']) && $rule['day_of_week'] !== ''
                        ? (int) $rule['day_of_week'] : null;
                    $startsAt = $rule['starts_at'] ?? null;
                    $endsAt = $rule['ends_at'] ?? null;

                    $priority = ($courtId ? 4 : 0) + ($dayOfWeek !== null ? 2 : 0) + ($startsAt && $endsAt ? 1 : 0);

                    $rows[] = [
                        'direction_id'   => $direction->id,
                        'court_id'       => $courtId,
                        'day_of_week'    => $dayOfWeek,
                        'starts_at'      => $startsAt,
                        'ends_at'        => $endsAt,
                        'price_per_hour' => $rule['price'],
                        'priority'       => $priority,
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ];
                }

                if (!empty($rows)) {
                    \App\Models\CourtPriceRule::insert($rows);
                }
            }
        });

        return back()->with('status', __('club.price_rules_saved') . ' ✅');
    }

    public function saveTrust(Request $request, Location $location)
    {
        $data = $request->validate([
            'organizer_id' => ['required', 'integer', 'exists:users,id'],
            'trust_level'  => ['required', Rule::in([
                ClubOrganizerTrust::LEVEL_PREPAID_ONLY,
                ClubOrganizerTrust::LEVEL_ALLOW_ON_SITE,
                ClubOrganizerTrust::LEVEL_TRUSTED,
            ])],
        ]);

        ClubOrganizerTrust::updateOrCreate(
            ['location_id' => $location->id, 'organizer_id' => $data['organizer_id']],
            ['trust_level' => $data['trust_level']]
        );

        return back()->with('status', __('club.trust_saved') . ' ✅');
    }

    public function destroyTrust(Location $location, ClubOrganizerTrust $trust)
    {
        abort_unless($trust->location_id === $location->id, 404);

        $trust->delete();

        return back()->with('status', __('club.trust_removed') . ' ✅');
    }

    public function destroy(Location $location)
    {
        $location->delete();

        return redirect()
            ->route('admin.locations.index')
            ->with('status', 'Локация удалена ✅');
    }
}