<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use App\Models\EventOccurrence;
use Carbon\CarbonImmutable;
use App\Models\Event;


class LocationController extends Controller
{
           public function index(Request $request)
        {
            $view = (string) $request->query('view', 'cards'); // cards|rows|map
            if (!in_array($view, ['cards', 'rows', 'map'], true)) {
                $view = 'cards';
            }
        
            // базовый запрос
            $q = Location::query()
                ->with(['city:id,name,region,timezone']);
        
            // если есть soft deletes
            if (Schema::hasColumn('locations', 'deleted_at')) {
                $q->whereNull('deleted_at');
            }
        
            // "действующие" (опционально): если хочешь включить фильтр только по тем,
            // у кого есть будущие occurrence/event — можно включать через ?active=1
            $activeOnly = (int) $request->query('active', 0) === 1;
        
            if ($activeOnly) {
                $nowUtc = CarbonImmutable::now('UTC');
        
                // если есть occurrences — берём по ним
                if (Schema::hasTable('event_occurrences') && Schema::hasColumn('event_occurrences', 'location_id')) {
                    $q->whereExists(function ($sub) use ($nowUtc) {
                        $sub->select(DB::raw(1))
                            ->from('event_occurrences as eo')
                            ->join('events as e', 'e.id', '=', 'eo.event_id')
                            ->whereColumn('eo.location_id', 'locations.id')
                            ->where('eo.starts_at', '>=', $nowUtc)
                            ->where(function ($w) {
                                // публичность (мягко: если колонок нет — не упадёт)
                                if (Schema::hasColumn('events', 'is_private')) {
                                    $w->where(function ($x) {
                                        $x->where('e.is_private', 0)->orWhereNull('e.is_private');
                                    });
                                }
                                if (Schema::hasColumn('events', 'visibility')) {
                                    $w->where(function ($x) {
                                        $x->where('e.visibility', '!=', 'private')->orWhereNull('e.visibility');
                                    });
                                }
                            });
                    });
                }
                // fallback: если occurrences нет — пробуем по events.location_id
                elseif (Schema::hasTable('events') && Schema::hasColumn('events', 'location_id')) {
                    $q->whereExists(function ($sub) use ($nowUtc) {
                        $sub->select(DB::raw(1))
                            ->from('events as e')
                            ->whereColumn('e.location_id', 'locations.id')
                            ->where('e.starts_at', '>=', $nowUtc);
                    });
                }
            }
        
            // сортировка: сначала город, потом название
            $q->orderBy('city_id')->orderBy('name');
        
            // Данные под 3 режима:
            // - cards: пагинация как у тебя сейчас
            // - rows/map: удобно без пагинации (иначе “карта всех” не получится)
            if ($view === 'cards') {
                $locations = $q->paginate(18)->withQueryString();
            
                // ✅ группируем ТОЛЬКО текущую страницу карточек
                $grouped = $locations->getCollection()
                    ->groupBy(fn($l) => $l->city?->name ?? 'Без города');
            
                $allForMap = collect();
            } else {
                $locations = $q->get();
                $grouped   = $locations->groupBy(fn($l) => $l->city?->name ?? 'Без города');
                $allForMap = $locations;
            }
        
            // точки для карты
            $mapPoints = $allForMap
                ->filter(fn($l) => !is_null($l->lat) && !is_null($l->lng))
                ->map(fn($l) => [
                    'id'      => (int) $l->id,
                    'name'    => (string) $l->name,
                    'address' => (string) ($l->address ?? ''),
                    'city'    => (string) ($l->city?->name ?? ''),
                    'lat'     => (float) $l->lat,
                    'lng'     => (float) $l->lng,
                    'url'     => route('locations.show', $l), // если есть show-роут
                ])
                ->values();
        
            return view('locations.index', [
                'viewMode'   => $view,
                'locations'  => $locations,
                'grouped'    => $grouped,
                'mapPoints'  => $mapPoints,
                'activeOnly' => $activeOnly,
            ]);
        }
    public function quickStore(Request $request)
    {
        $user = $request->user();
        $role = (string)($user->role ?? 'user');
        if ($role !== 'admin') abort(403);

        $data = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'address'       => ['nullable', 'string', 'max:255'],

            // вместо city text -> city_id
            'city_id'       => ['nullable', 'integer'],

            // тексты
            'short_text'    => ['nullable', 'string', 'max:255'],
            'long_text'     => ['nullable', 'string'],
            'long_text_full'=> ['nullable', 'string'],
            'note'          => ['nullable', 'string'],

            // coords
            'lat'           => ['nullable', 'numeric', 'between:-90,90'],
            'lng'           => ['nullable', 'numeric', 'between:-180,180'],

            // фото с компа (multipart)
            'photos'        => ['nullable', 'array'],
            'photos.*'      => ['file', 'image', 'max:8192'],

            // фото из профиля (выбор из галереи пользователя)
            'media_ids'     => ['nullable', 'array'],
            'media_ids.*'   => ['integer'],
        ]);

        $location = DB::transaction(function () use ($data, $user, $request) {
            $location = new Location();
            $location->organizer_id = null; // admin создаёт общую локацию

            $location->name = $data['name'];
            $location->address = $data['address'] ?? null;

            if (Schema::hasColumn('locations', 'city_id')) {
                $location->city_id = $data['city_id'] ?? null;
            }

            $location->short_text = $data['short_text'] ?? null;
            $location->long_text = $data['long_text'] ?? null;
            if (Schema::hasColumn('locations', 'long_text_full')) {
                $location->long_text_full = $data['long_text_full'] ?? null;
            }
            if (Schema::hasColumn('locations', 'note')) {
                $location->note = $data['note'] ?? null;
            }

            $location->lat = array_key_exists('lat', $data) ? $data['lat'] : null;
            $location->lng = array_key_exists('lng', $data) ? $data['lng'] : null;

            $location->save();

            // 1) Фото с компьютера
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $file) {
                    $location->addMedia($file)->toMediaCollection('photos');
                }
            }

            // 2) Фото из профиля (копирование media)
            if (!empty($data['media_ids'])) {
                $medias = Media::query()
                    ->whereIn('id', $data['media_ids'])
                    ->where('model_type', get_class($user))
                    ->where('model_id', (int)$user->id)
                    ->get();

                foreach ($medias as $m) {
                    // копируем в коллекцию локации (будут храниться отдельно)
                    $m->copy($location, 'photos');
                }
            }

            return $location;
        });

        return response()->json([
            'ok' => true,
            'message' => 'Локация создана ✅',
            'data' => [
                'id' => $location->id,
                'name' => $location->name,
                'address' => $location->address,
                'city_id' => $location->city_id ?? null,
                'short_text' => $location->short_text,
                'long_text' => $location->long_text,
                'long_text_full' => $location->long_text_full ?? null,
                'note' => $location->note ?? null,
                'lat' => $location->lat,
                'lng' => $location->lng,
                'first_photo' => $location->getFirstMediaUrl('photos') ?: null,
            ],
        ], 200);
    }
}