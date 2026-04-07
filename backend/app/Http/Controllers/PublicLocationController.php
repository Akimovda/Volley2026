<?php
// app/Http/Controllers/PublicLocationController.php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\Location;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PublicLocationController extends Controller
{
    // Лучше константой, раз у вас Москва = 403 (из tinker)
    private const MOSCOW_CITY_ID = 403;

    /**
     * Публичный список локаций: /locations
     *
     * Логика:
     * 1) если выбран city_id в запросе — показываем его
     * 2) иначе если у пользователя есть city_id и в этом городе есть локации — показываем его
     * 3) иначе показываем все города
     */
            public function index(Request $request)
            {
                $viewMode = (string) $request->query('view', 'cards');
                if (!in_array($viewMode, ['rows','cards','map'], true)) $viewMode = 'cards';
            
                $activeOnly = (int) $request->query('active', 0) === 1;
            
                // city filter: только если city_id в URL
                $reqCityId = (int) $request->query('city_id', 0);
            
                // --- базовый фильтр локаций (публичные) ---
                $baseLocationQ = Location::query()->whereNull('organizer_id');
            
                if ($reqCityId > 0) {
                    $baseLocationQ->where('city_id', $reqCityId);
                }
            
                // --- ACTIVE: строим список активных location_id (как у тебя), но без принудительного city_id=403 ---
                $activeLocationIds = null;
            
                if ($activeOnly) {
                    $todayUtc = CarbonImmutable::now('UTC')->startOfDay();
                    $activeLocationIds = [];
            
                    if (Schema::hasTable('event_occurrences')) {
$q = EventOccurrence::query()
    ->whereNotNull('starts_at')
    ->where('starts_at', '>=', $todayUtc)
                            ->whereHas('event', function ($e) {
                                $e->where(function ($pub) {
                                    if (Schema::hasColumn('events', 'is_private')) {
                                        $pub->whereNull('is_private')->orWhere('is_private', false);
                                    }
                                    if (Schema::hasColumn('events', 'visibility')) {
                                        $pub->whereNull('visibility')->orWhere('visibility', '!=', 'private');
                                    }
                                });
                            })
                            ->with(['event:id,location_id']);
            
                        // ✅ если выбран город — сузим
                        if ($reqCityId > 0) {
                            $q->whereHas('event', fn($e) => $e->whereHas('location', fn($l) => $l->where('city_id', $reqCityId)));
                        }
            
                        $activeLocationIds = $q->get()
                            ->pluck('event.location_id')->filter()->unique()->values()->all();
                    } else if (Schema::hasTable('events')) {
$q = Event::query()
    ->whereNotNull('starts_at')
    ->where('starts_at', '>=', $todayUtc)
                            ->where(function ($pub) {
                                if (Schema::hasColumn('events', 'is_private')) {
                                    $pub->whereNull('is_private')->orWhere('is_private', false);
                                }
                                if (Schema::hasColumn('events', 'visibility')) {
                                    $pub->whereNull('visibility')->orWhere('visibility', '!=', 'private');
                                }
                            });
            
                        if ($reqCityId > 0) {
                            $q->whereHas('location', fn($l) => $l->where('city_id', $reqCityId));
                        }
            
                        $activeLocationIds = $q->pluck('location_id')->filter()->unique()->values()->all();
                    }
            
                    if (empty($activeLocationIds)) $activeLocationIds = [-1]; // чтобы было пусто
                }
            
                // ✅ пагинация по городам
                $citiesQ = City::query()
                    ->select('id','name','region','country_code','timezone')
                    ->when($reqCityId > 0, fn($q) => $q->whereKey($reqCityId))
                    ->whereHas('locations', function ($q) use ($activeOnly, $activeLocationIds) {
                        $q->whereNull('organizer_id');
                        if ($activeOnly && is_array($activeLocationIds)) {
                            $q->whereIn('id', $activeLocationIds);
                        }
                    })
                    ->with(['locations' => function ($q) use ($activeOnly, $activeLocationIds) {
                        $q->whereNull('organizer_id')
                          ->with('city:id,name,timezone')
                          ->orderBy('name');
            
                        if ($activeOnly && is_array($activeLocationIds)) {
                            $q->whereIn('id', $activeLocationIds);
                        }
                    }])
                    ->orderBy('name');
            
                $cities = $citiesQ->paginate(10)->withQueryString(); // 10 городов на страницу, внутри — все их локации
            
                return view('locations.index', [
                    'cities' => $cities,
                    'viewMode' => $viewMode,
                    'activeOnly' => $activeOnly ? 1 : 0,
                    'selectedCityId' => $reqCityId,
                ]);
            }

    /**
     * Публичная страница локации: /locations/{id}-{slug}
     */
    public function show(Request $request, int $location, string $slug)
    {
        $loc = Location::query()
            ->whereNull('organizer_id')
            ->with(['city:id,name,timezone', 'media'])
            ->whereKey($location)
            ->firstOrFail();

        $canonicalSlug = $this->slugify($loc->name);
        if ($slug !== $canonicalSlug) {
            return redirect()
                ->route('locations.show', ['location' => $loc->id, 'slug' => $canonicalSlug])
                ->setStatusCode(301);
        }

        $photos = $loc->getMedia('photos')
            ->sortBy(fn($m) => (int)($m->order_column ?? 0))
            ->values();

        $todayUtc = CarbonImmutable::now('UTC')->startOfDay();

        $user = $request->user();
        $userId = (int)($user?->id ?? 0);
        $isAdmin = (string)($user?->role ?? '') === 'admin';

        // -----------------------------
        // 1) occurrences (для recurring)
        // -----------------------------
        $occurrences = collect();

        if (Schema::hasTable('event_occurrences')) {
            $occQ = EventOccurrence::query()
                ->whereHas('event', function ($q) use ($loc, $userId, $isAdmin) {
                    $q->where('location_id', (int)$loc->id);

                    // Если НЕ админ — показываем только публичные,
                    // но организатор события видит свои private.
                    if (!$isAdmin) {
                        $q->where(function ($w) use ($userId) {
                            // public
                            $w->where(function ($pub) {
                                if (Schema::hasColumn('events', 'is_private')) {
                                    $pub->whereNull('is_private')->orWhere('is_private', false);
                                }
                                if (Schema::hasColumn('events', 'visibility')) {
                                    $pub->whereNull('visibility')->orWhere('visibility', '!=', 'private');
                                }
                            });

                            // или своё (organizer)
                            if ($userId > 0 && Schema::hasColumn('events', 'organizer_id')) {
                                $w->orWhere('organizer_id', $userId);
                            }
                        });
                    }
                });

            if (Schema::hasColumn('event_occurrences', 'cancelled_at')) {
                $occQ->whereNull('cancelled_at');
            }
            if (Schema::hasColumn('event_occurrences', 'is_cancelled')) {
                $occQ->where(function ($w) {
                    $w->whereNull('is_cancelled')->orWhere('is_cancelled', false);
                });
            }

$occQ->whereNotNull('starts_at')
     ->where('starts_at', '>=', $todayUtc);

            $occurrences = $occQ
                ->with([
                    'event' => function ($q) {
                        $q->with([
                            'location' => function ($lq) {
                                $lq->select('id', 'name', 'address', 'city_id')
                                   ->with('city:id,name,region');
                            },
                            'gameSettings',
                            'media',
                            'organizer:id,name,first_name,last_name,email,role',
                        ]);
                    },
                ])
                ->orderBy('starts_at')
                ->limit(50)
                ->get();
        }

        // -----------------------------------------
        // 2) fallback: обычные events (если нет occurrences)
        // -----------------------------------------
        $events = collect();

        if ($occurrences->isEmpty()) {
            $eventsQ = Event::query()
                ->where('location_id', (int)$loc->id);

            if (!$isAdmin) {
                $eventsQ->where(function ($w) use ($userId) {
                    // public
                    $w->where(function ($pub) {
                        if (Schema::hasColumn('events', 'is_private')) {
                            $pub->whereNull('is_private')->orWhere('is_private', false);
                        }
                        if (Schema::hasColumn('events', 'visibility')) {
                            $pub->whereNull('visibility')->orWhere('visibility', '!=', 'private');
                        }
                    });

                    // или своё (organizer)
                    if ($userId > 0 && Schema::hasColumn('events', 'organizer_id')) {
                        $w->orWhere('organizer_id', $userId);
                    }
                });
            }

$eventsQ->whereNotNull('starts_at')
        ->where('starts_at', '>=', $todayUtc);

            $events = $eventsQ
                ->with([
                    'location' => function ($lq) {
                        $lq->select('id', 'name', 'address', 'city_id')
                           ->with('city:id,name,region');
                    },
                    'gameSettings',
                    'media',
                    'organizer:id,name,first_name,last_name,email,role',
                ])
                ->orderBy('starts_at')
                ->limit(50)
                ->get();
        }

        return view('locations.show', [
            'location' => $loc,
            'photos' => $photos,
            'slug' => $canonicalSlug,
            'occurrences' => $occurrences,
            'events' => $events,
        ]);
    }

    private function resolveSelectedCity(Request $request): ?City
    {
        // 1) явно выбран город
        $reqCityId = (int)($request->query('city_id') ?? 0);
        if ($reqCityId > 0) {
            return City::query()
                ->select('id','name','region','country_code','timezone')
                ->find($reqCityId);
        }

        // 2) город пользователя + есть локации
        $userCityId = (int)($request->user()?->city_id ?? 0);
        if ($userCityId > 0) {
            $hasLocations = Location::query()
                ->whereNull('organizer_id')
                ->where('city_id', $userCityId)
                ->exists();

            if ($hasLocations) {
                $city = City::query()
                    ->select('id','name','region','country_code','timezone')
                    ->find($userCityId);
                if ($city) return $city;
            }
        }

        // 3) Москва: сначала по ID (надёжнее), потом по имени
        // 3) если ничего не выбрано — НЕ подставляем Москву
        return null;
    }

    private function formatCityLabel(City $city): string
    {
        $parts = [$city->name];
        if (!empty($city->region)) $parts[] = '(' . $city->region . ')';
        if (!empty($city->country_code)) $parts[] = $city->country_code;
        return implode(' ', $parts);
    }

    private function slugify(string $value): string
    {
        $s = Str::slug($value, '-');
        return $s !== '' ? $s : 'location';
    }
}