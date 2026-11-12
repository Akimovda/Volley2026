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
public function index(Request $request)
{
    $search = trim((string) $request->query('q', ''));

    $q = Location::query()
        ->with(['city:id,name,region,country_code,timezone']) // добавили region и country_code
        ->orderByRaw('COALESCE((select name from cities where cities.id = locations.city_id), \'\') asc')
        ->orderBy('name');

    if ($search !== '') {
        // Поиск по названию, адресу, городу (и по ID, если введено число); без учёта регистра, спецсимволы LIKE экранируем
        $like = '%' . addcslashes(mb_substr($search, 0, 100), '\\%_') . '%';
        $q->where(function ($w) use ($like, $search) {
            $w->where('locations.name', 'ilike', $like)
              ->orWhere('locations.address', 'ilike', $like)
              ->orWhereHas('city', fn ($c) => $c->where('name', 'ilike', $like));
            if (ctype_digit($search)) {
                $w->orWhere('locations.id', (int) $search);
            }
        });
    }

    return view('admin.locations.index', [
        'locations' => $q->paginate(30)->withQueryString(),
        'search'    => $search,
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
        'address'        => ['required', 'string', 'max:255'],
        'city_id'        => ['required', 'integer', 'exists:cities,id'],
        'timezone'       => ['nullable', 'string', 'max:64'],
        'short_text'     => ['nullable', 'string', 'max:255'],
        'long_text'      => ['nullable', 'string'],
        'long_text_full' => ['nullable', 'string'], // ДОБАВЛЕНО
        'note'           => ['nullable', 'string'], // ДОБАВЛЕНО
        'lat'            => ['required', 'numeric', 'between:-90,90'],
        'lng'            => ['required', 'numeric', 'between:-180,180'],
        'photos'         => ['nullable', 'array'],
        'photos.*'       => ['nullable', 'image', 'max:5120'],
    ]);

    $city = City::query()->whereKey((int)$data['city_id'])->first();

    $location = new Location();
    $location->organizer_id = null;
    $location->name = $data['name'];
    $location->address = $data['address'] ?? null;

    if (\App\Support\SchemaCache::hasColumn('locations', 'city_id')) {
        $location->city_id = (int)$data['city_id'];
    }

    if (\App\Support\SchemaCache::hasColumn('locations', 'timezone')) {
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

        // Журнал изменений локации (виден администратору и владельцу площадки)
        $locationAudit = DB::table('admin_audits')
            ->leftJoin('users', 'users.id', '=', 'admin_audits.actor_user_id')
            ->where('admin_audits.target_type', 'location')
            ->where('admin_audits.target_id', (string) $location->id)
            ->orderByDesc('admin_audits.id')
            ->limit(100)
            ->get(['admin_audits.id', 'admin_audits.action', 'admin_audits.meta', 'admin_audits.created_at',
                   'users.first_name', 'users.last_name', 'users.name'])
            ->map(function ($r) {
                $r->meta = json_decode((string) $r->meta, true) ?: [];
                $r->actor = trim(($r->last_name ?? '') . ' ' . ($r->first_name ?? '')) ?: (string) ($r->name ?? '—');

                return $r;
            });

        return view('admin.locations.edit', compact('location', 'photos', 'directions', 'clubManagers', 'trustedOrganizers', 'priceRules', 'locationAudit'));
    }

public function update(Request $request, Location $location)
{
    $data = $request->validate([
        'name'           => ['required', 'string', 'max:255'],
        'address'        => ['required', 'string', 'max:255'],
        'city_id'        => [$request->user()?->isAdmin() ? 'required' : 'nullable', 'integer', 'exists:cities,id'],
        'timezone'       => ['nullable', 'string', 'max:64'],
        'short_text'     => ['nullable', 'string', 'max:255'],
        'long_text'      => ['nullable', 'string'],
        'long_text_full' => ['nullable', 'string'], // ДОБАВИТЬ
        'note'           => ['nullable', 'string'], // ДОБАВИТЬ
        'lat'            => ['required', 'numeric', 'between:-90,90'],
        'lng'            => ['required', 'numeric', 'between:-180,180'],
        'photos'         => ['nullable', 'array'],
        'photos.*'       => ['nullable', 'image', 'max:5120'],
        'owner_id'       => ['nullable', 'integer', 'exists:users,id'],
        'booking_cancel_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
        'refund_policy'  => ['nullable', 'in:full,none'],
        'refund_deadline_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
    ]);

    // Владелец площадки не меняет владельца, город и часовой пояс — только администратор
    $isAdmin = (bool) $request->user()?->isAdmin();

    if ($isAdmin) {
        $location->owner_id = $data['owner_id'] ?? null;
    }
    $location->booking_cancel_hours = $data['booking_cancel_hours'] ?? 24;
    $location->refund_policy = $data['refund_policy'] ?? 'full';
    $location->refund_deadline_hours = $data['refund_deadline_hours'] ?? 24;

    $location->name = $data['name'];
    $location->address = $data['address'] ?? null;

    if ($isAdmin) {
        $city = City::query()->whereKey((int)$data['city_id'])->first();

        if (\App\Support\SchemaCache::hasColumn('locations', 'city_id')) {
            $location->city_id = (int)$data['city_id'];
        }

        if (\App\Support\SchemaCache::hasColumn('locations', 'timezone')) {
            $tz = trim((string)($data['timezone'] ?? ''));
            if ($tz === '') $tz = (string)($city->timezone ?? $location->timezone ?? 'Europe/Moscow');
            $location->timezone = $tz;
        }
    }

    $location->short_text = $data['short_text'] ?? null;
    $location->long_text  = $data['long_text'] ?? null;
    $location->long_text_full = $data['long_text_full'] ?? null; // ДОБАВИТЬ
    $location->note = $data['note'] ?? null; // ДОБАВИТЬ
    $location->lat = array_key_exists('lat', $data) ? $data['lat'] : null;
    $location->lng = array_key_exists('lng', $data) ? $data['lng'] : null;

    $changes = $this->dirtyDiff($location);
    $location->save();

    // Порядок загруженных фото (перетаскивание) — сохраняется вместе с формой
    $order = array_values(array_filter((array) $request->input('photo_order', []), fn ($id) => ctype_digit((string) $id)));
    foreach ($order as $i => $mediaId) {
        \Spatie\MediaLibrary\MediaCollections\Models\Media::query()
            ->where('id', (int) $mediaId)
            ->where('model_type', Location::class)
            ->where('model_id', $location->id)
            ->update(['order_column' => $i + 1]);
    }

    $uploaded = 0;
    foreach ($request->file('photos', []) as $file) {
        if (!$file) continue;
        $location->addMedia($file)->toMediaCollection('photos');
        $uploaded++;
    }

    if ($changes !== [] || $uploaded > 0) {
        $this->audit('location.update', $location, array_filter([
            'changes'         => $changes ?: null,
            'photos_uploaded' => $uploaded ?: null,
        ]));
    }

    // Загрузили фото — возвращаем к блоку «Фотографии», чтобы сразу увидеть их и при необходимости удалить
    if ($uploaded > 0) {
        return redirect(route('admin.locations.edit', $location) . '#location-photos')
            ->with('status', 'Локация сохранена, фото загружено: ' . $uploaded . ' ✅');
    }

    return back()->with('status', 'Локация сохранена ✅');
}

    public function saveDirections(Request $request, Location $location)
    {
        $data = $request->validate([
            'directions'                          => ['nullable', 'array'],
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

        $auditBefore = $this->directionsSnapshot($location);

        DB::transaction(function () use ($data, $location) {
            foreach ([LocationDirection::DIRECTION_CLASSIC, LocationDirection::DIRECTION_BEACH] as $directionKey) {
                $input = ($data['directions'] ?? [])[$directionKey] ?? null;
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

        $this->auditSnapshotDiff('location.directions.update', $location, $auditBefore, $this->directionsSnapshot($location));

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

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
            'directions'                        => ['nullable', 'array'],
            'directions.*.base_price'           => ['nullable', 'numeric', 'min:0.01', 'max:99999'],
            'directions.*.rules'                => ['nullable', 'array'],
            'directions.*.rules.*.court_id'     => ['nullable', 'integer', 'exists:location_courts,id'],
            'directions.*.rules.*.day_of_week'  => ['nullable', 'integer', 'min:0', 'max:6'],
            'directions.*.rules.*.starts_at'    => ['nullable', 'date_format:H:i'],
            'directions.*.rules.*.ends_at'      => ['nullable', 'date_format:H:i'],
            'directions.*.rules.*.price'        => ['required', 'numeric', 'min:0.01', 'max:99999'],
        ]);

        $auditBefore = $this->priceSnapshot($location);

        foreach (($data['directions'] ?? []) as $directionKey => $input) {
            foreach (($input['rules'] ?? []) as $i => $rule) {
                $hasStart = !empty($rule['starts_at']);
                $hasEnd = !empty($rule['ends_at']);
                if ($hasStart !== $hasEnd) {
                    if ($request->expectsJson()) {
                        return response()->json(['ok' => false, 'message' => __('club.price_rule_time_both_required')], 422);
                    }
                    return back()->with('error', __('club.price_rule_time_both_required'));
                }
                if ($hasStart && $hasEnd && $rule['ends_at'] <= $rule['starts_at']) {
                    if ($request->expectsJson()) {
                        return response()->json(['ok' => false, 'message' => __('club.price_rule_time_order')], 422);
                    }
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

                $input = ($data['directions'] ?? [])[$directionKey] ?? null;
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

        $this->auditSnapshotDiff('location.prices.update', $location, $auditBefore, $this->priceSnapshot($location));

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

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

        $this->audit('location.trust.save', $location, [
            'organizer_id'   => (int) $data['organizer_id'],
            'organizer_name' => $this->userLabel((int) $data['organizer_id']),
            'trust_level'    => $data['trust_level'],
        ]);

        return back()->with('status', __('club.trust_saved') . ' ✅');
    }

    public function destroyTrust(Location $location, ClubOrganizerTrust $trust)
    {
        abort_unless($trust->location_id === $location->id, 404);

        $this->audit('location.trust.destroy', $location, [
            'organizer_id'   => (int) $trust->organizer_id,
            'organizer_name' => $this->userLabel((int) $trust->organizer_id),
            'trust_level'    => $trust->trust_level,
        ]);

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

    // ───────────── Журнал изменений локации (admin_audits, target_type = location) ─────────────

    private function audit(string $action, Location $location, array $meta = []): void
    {
        \App\Support\AdminAuditLogger::log($action, 'location', $location->id, array_merge([
            'actor_role'    => auth()->user()?->isAdmin() ? 'admin' : 'owner',
            'location_name' => $location->name,
        ], $meta));
    }

    private function userLabel(int $id): string
    {
        $u = \App\Models\User::find($id);

        return $u ? (trim(($u->last_name ?? '') . ' ' . ($u->first_name ?? '')) ?: (string) $u->name) : ('#' . $id);
    }

    /** Изменённые поля модели: [поле => [было, стало]] (длинные тексты усечены). */
    private function dirtyDiff(Location $location): array
    {
        $out = [];
        foreach ($location->getDirty() as $field => $new) {
            if (in_array($field, ['updated_at'], true)) {
                continue;
            }
            $out[$field] = [$this->auditValue($location->getOriginal($field)), $this->auditValue($new)];
        }

        return $out;
    }

    private function auditValue(mixed $v): string
    {
        if ($v === null || $v === '') {
            return '';
        }
        if (is_bool($v)) {
            return $v ? '1' : '0';
        }

        return mb_substr((string) $v, 0, 300);
    }

    private function auditSnapshotDiff(string $action, Location $location, array $before, array $after): void
    {
        $changes = [];
        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $field) {
            $old = $before[$field] ?? '';
            $new = $after[$field] ?? '';
            if ($old !== $new) {
                $changes[$field] = [$old, $new];
            }
        }
        if ($changes !== []) {
            $this->audit($action, $location, ['changes' => $changes]);
        }
    }

    /** Плоский снимок направлений, кортов и часов работы для сравнения до/после. */
    private function directionsSnapshot(Location $location): array
    {
        $snap = [];
        $dirs = LocationDirection::where('location_id', $location->id)
            ->with(['courts' => fn ($q) => $q->orderBy('sort_order'), 'workingHours'])
            ->get()->keyBy('direction');

        foreach ([LocationDirection::DIRECTION_CLASSIC, LocationDirection::DIRECTION_BEACH] as $k) {
            $d = $dirs->get($k);
            $snap["direction.$k.active"] = $d && $d->is_active ? '1' : '0';
            $snap["direction.$k.courts"] = $d
                ? $d->courts->where('is_active', true)->map(fn ($c) => $c->name . ($c->is_indoor ? ' (indoor)' : ''))->implode(', ')
                : '';
            $snap["direction.$k.hours"] = $d
                ? $d->workingHours->sortBy('day_of_week')->map(fn ($h) => $h->day_of_week . ': ' . ($h->is_day_off
                    ? 'off'
                    : substr((string) $h->opens_at, 0, 5) . '-' . substr((string) $h->closes_at, 0, 5)))->implode('; ')
                : '';
        }

        return $snap;
    }

    /** Плоский снимок прайса аренды (базовая цена и правила). */
    private function priceSnapshot(Location $location): array
    {
        $snap = [];
        $dirs = LocationDirection::where('location_id', $location->id)->get()->keyBy('direction');

        foreach ([LocationDirection::DIRECTION_CLASSIC, LocationDirection::DIRECTION_BEACH] as $k) {
            $d = $dirs->get($k);
            $rules = $d ? \App\Models\CourtPriceRule::where('direction_id', $d->id)->orderBy('priority')->orderBy('id')->get() : collect();
            $base = $rules->first(fn ($r) => $r->court_id === null && $r->day_of_week === null && $r->starts_at === null);
            $snap["price.$k.base"] = $base ? (string) (float) $base->price_per_hour : '';
            $snap["price.$k.rules"] = $rules->reject(fn ($r) => $base && $r->id === $base->id)
                ->map(fn ($r) => implode(' ', array_filter([
                    $r->court_id ? 'court#' . $r->court_id : null,
                    $r->day_of_week !== null ? 'day' . $r->day_of_week : null,
                    $r->starts_at ? substr((string) $r->starts_at, 0, 5) . '-' . substr((string) $r->ends_at, 0, 5) : null,
                ])) . '=' . (float) $r->price_per_hour)
                ->sort()->implode('; ');
        }

        return $snap;
    }
}
