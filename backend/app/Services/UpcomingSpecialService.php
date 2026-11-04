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
     * @return Collection<int, array> элементы: occ, event, kind(camp|master_class|tournament), status(open|opens|full),
     *                                opensAt(Carbon|null, в поясе пользователя), startsLocal, endsLocal, remaining(int|null), image
     */
    public function forUser($user, ?Carbon $afterUtc = null, int $limit = self::LIMIT): Collection
    {
        $userId  = (int) ($user?->id ?? 0);
        $role    = strtolower(trim((string) ($user?->role ?? 'user')));
        $isAdmin = in_array($role, ['admin', 'superadmin', 'owner', 'root'], true);
        $tz      = DateTime::effectiveUserTz($user);
        $nowUtc  = Carbon::now('UTC');
        $after   = $afterUtc && $afterUtc->gt($nowUtc) ? $afterUtc : $nowUtc;

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
        $items = [];
        foreach ($occs as $occ) {
            $event = $occ->event;
            if (!$event || isset($seen[$event->id])) {
                continue;
            }
            $kind = $event->format === 'camp' ? 'camp' : ($event->format === 'master_class' ? 'master_class' : 'tournament');

            if ($kind === 'tournament' && $regionCityIds !== null) {
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
            $regStarts = $occ->effectiveRegistrationStartsAt();
            $opensAt   = ($regStarts && $nowUtc->lessThan($regStarts)) ? $regStarts->copy()->setTimezone($tz) : null;

            $startsLocal = Carbon::parse($occ->starts_at, 'UTC')->setTimezone($tz);
            $durationSec = (int) ($occ->duration_sec ?? 0);
            $endsLocal   = $durationSec >= 86400 ? $startsLocal->copy()->addSeconds($durationSec) : null;

            $items[] = [
                'occ'         => $occ,
                'event'       => $event,
                'kind'        => $kind,
                'status'      => $opensAt ? 'opens' : 'open',
                'opensAt'     => $opensAt,
                'startsLocal' => $startsLocal,
                'endsLocal'   => ($endsLocal && !$endsLocal->isSameDay($startsLocal)) ? $endsLocal : null,
                'remaining'   => null,
                'image'       => null,
            ];
        }

        // случайный порядок, затем лимит
        $items = collect($items)->shuffle()->take($limit)->values();

        $this->fillSeats($items);
        $this->fillImages($items);

        return $items;
    }

    /** Свободные места (кемпы и мастер-классы — индивидуальная запись); «Мест нет» — статус full. */
    private function fillSeats(Collection $items): void
    {
        $ids = $items->filter(fn ($i) => $i['kind'] !== 'tournament')->map(fn ($i) => (int) $i['occ']->id)->all();
        if (!$ids) {
            return;
        }
        $booked = DB::table('event_registrations')
            ->whereIn('occurrence_id', $ids)
            ->whereNull('cancelled_at')
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', 'confirmed'))
            ->select('occurrence_id', DB::raw('count(*) as c'))
            ->groupBy('occurrence_id')
            ->pluck('c', 'occurrence_id');

        $items->transform(function ($i) use ($booked) {
            if ($i['kind'] === 'tournament') {
                return $i;
            }
            $occ = $i['occ'];
            $max = (int) ($occ->max_players ?? ($occ->event->gameSettings->max_players ?? 0));
            if ($max > 0) {
                $left = max(0, $max - (int) ($booked[$occ->id] ?? 0));
                $i['remaining'] = $left;
                if ($left === 0 && $i['status'] === 'open') {
                    $i['status'] = 'full';
                }
            }
            return $i;
        });
    }

    /** Обложка: первое фото события → cover → картинка направления (как в карточке ленты). */
    private function fillImages(Collection $items): void
    {
        $firstIds = $items->map(fn ($i) => (int) (((array) ($i['event']->event_photos ?? []))[0] ?? 0))->filter()->unique()->all();
        $media = $firstIds
            ? \Spatie\MediaLibrary\MediaCollections\Models\Media::whereIn('id', $firstIds)->get()->keyBy('id')
            : collect();

        $items->transform(function ($i) use ($media) {
            $event = $i['event'];
            $pid = (int) (((array) ($event->event_photos ?? []))[0] ?? 0);
            $url = null;
            if ($pid && $media->has($pid)) {
                $url = $media[$pid]->getUrl('event_thumb');
            }
            if (!$url) {
                $url = $event->getFirstMediaUrl('cover') ?: null;
            }
            $i['image'] = $url ?: '/img/' . ($event->direction === 'beach' ? 'beach.webp' : 'classic.webp');
            return $i;
        });
    }
}
