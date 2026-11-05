<?php

namespace App\Services;

use App\Models\City;
use App\Support\DateTime;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Блок «Скоро: кемпы, мастер-классы, турниры» (лента /events и главная).
 * Лента показывает окно в 10 дней — дальние кемпы/турниры до пользователя не доходят; блок показывает их заранее.
 * Правила видимости — те же, что у ленты (EventIndexService::buildOccurrenceQuery: приватность, бренд, активные туры);
 * кемпы и мастер-классы видны всем регионам, турниры — региону пользователя (как и в рассылке).
 * Порядок СЛУЧАЙНЫЙ при каждой загрузке — чтобы при большом числе событий до интересных можно было «дойти».
 */
class UpcomingSpecialService
{
    public const LIMIT = 10;

    public function __construct(private EventIndexService $index) {}

    /**
     * @param Carbon|null $afterUtc показывать туры, начинающиеся строго позже (по умолчанию — позже сейчас;
     *                              лента передаёт конец своего 10-дневного окна, чтобы не дублировать то, что уже в ленте)
     * @return array{occurrences: Collection, card: array} туры в случайном порядке + данные для events/_card.blade.php
     */
    public function forUser($user, ?Carbon $afterUtc = null, int $limit = self::LIMIT): array
    {
        $userId  = (int) ($user?->id ?? 0);
        $role    = strtolower(trim((string) ($user?->role ?? 'user')));
        $isAdmin = in_array($role, ['admin', 'superadmin', 'owner', 'root'], true);
        $tz      = DateTime::effectiveUserTz($user);
        $nowUtc  = Carbon::now('UTC');
        $after   = $afterUtc && $afterUtc->gt($nowUtc) ? $afterUtc : $nowUtc;

        // пользователь скрыл блок целиком (на 30 дней или переключателем в профиле)
        if ($user && $user->upcoming_hidden_until && Carbon::parse($user->upcoming_hidden_until)->isFuture()) {
            return ['occurrences' => collect(), 'card' => []];
        }
        // скрытые крестиком мероприятия и те, на которые пользователь уже записан
        $dismissed = $userId > 0 ? DB::table('upcoming_dismissals')->where('user_id', $userId)->pluck('event_id')->map(fn ($v) => (int) $v)->all() : [];
        [$joinedOccIds, $joinedEventIds] = $userId > 0 ? $this->index->joinedForUpcoming() : [[], []];

        $q = $this->index->buildOccurrenceQuery($user, $userId, $isAdmin, '', '', null, '', false)
            ->where('starts_at', '>', $after)
            ->whereHas('event', function ($eq) {
                $eq->where(function ($w) {
                    $w->whereIn('format', ['camp', 'master_class'])->orWhere('format', 'like', 'tournament%');
                });
            })
            ->orderBy('starts_at');

        $occs = $q->limit(300)->get();

        // регион пользователя — для турниров (кемпы и мастер-классы видны всем)
        $regionCityIds = null;
        $userCityId = (int) ($user?->city_id ?? 0);
        if ($userCityId > 0 && request('city') !== 'all') {
            $scope = City::feedRegionScope($userCityId);
            $regionCityIds = DB::table('cities')->where('id', $userCityId)
                ->when($scope, function ($c) use ($scope) {
                    [$country, $regions] = $scope;
                    $c->orWhere(function ($w) use ($country, $regions) {
                        $w->whereIn('region', $regions)->when($country !== '', fn ($x) => $x->where('country_code', $country));
                    });
                })
                ->pluck('id')->map(fn ($v) => (int) $v)->all();
        }

        $seen = [];
        $picked = [];
        foreach ($occs as $occ) {
            $event = $occ->event;
            if (!$event || isset($seen[$event->id])) {
                continue;
            }
            if (in_array((int) $event->id, $dismissed, true)
                || in_array((int) $event->id, $joinedEventIds, true)
                || in_array((int) $occ->id, $joinedOccIds, true)) {
                continue;
            }
            $isTournament = !in_array($event->format, ['camp', 'master_class'], true);

            if ($isTournament && $regionCityIds !== null) {
                $cityId = (int) ($event->location?->city_id ?? 0);
                if ($cityId > 0 && !in_array($cityId, $regionCityIds, true)) {
                    continue;
                }
            }
            if (!$occ->effectiveAllowRegistration()) {
                continue;
            }
            $regEnds = $occ->effectiveRegistrationEndsAt();
            if ($regEnds && $nowUtc->greaterThanOrEqualTo($regEnds)) {
                continue; // регистрация уже закрыта
            }

            $seen[$event->id] = true;
            $picked[] = $occ;
        }

        // случайный порядок, затем лимит
        $occurrences = collect($picked)->shuffle()->take($limit)->values();

        return [
            'occurrences' => $occurrences,
            'card'        => $occurrences->isEmpty() ? [] : $this->index->cardData($user, $occurrences),
        ];
    }
}
