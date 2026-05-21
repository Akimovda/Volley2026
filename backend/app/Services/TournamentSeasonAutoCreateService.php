<?php

namespace App\Services;

use App\Models\Event;
use App\Models\TournamentSeason;
use App\Models\League;
use App\Models\TournamentLeague;
use App\Models\TournamentSeasonEvent;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

/**
 * Автоматическое создание Season + League + SeasonEvent
 * при создании recurring tournament event.
 */
final class TournamentSeasonAutoCreateService
{
    /**
     * Если event — recurring tournament и пользователь выбрал «Создать серию»,
     * создаём Season + League (или привязываем к существующей).
     */
    public function createIfNeeded(Event $event, array $data): ?TournamentSeason
    {
        // Только для recurring tournament
        if ($event->format !== 'tournament' || !$event->is_recurring) {
            return null;
        }

        // Уже привязан к сезону — не дублируем
        if ($event->season_id) {
            return null;
        }

        // Пользователь отказался от серии
        if (!in_array($data['create_season'] ?? '1', [1, '1', true, 'true', 'on'], true)) {
            return null;
        }

        $mode = (string) ($data['season_league_mode'] ?? 'new');

        if ($mode === 'existing') {
            return $this->attachToExisting($event, $data);
        }

        return $this->createNew($event, $data);
    }

    /**
     * Создать новый Season + League.
     */
    private function createNew(Event $event, array $data): ?TournamentSeason
    {
        $divisionName = trim((string) ($data['new_league_name'] ?? 'Основной'));
        if ($divisionName === '') {
            $divisionName = 'Основной';
        }

        $direction = $event->direction ?? 'classic';

        // 1. League (верхнеуровневая сущность)
        $leagueName = trim((string) ($data['new_league_name'] ?? $data['league_name'] ?? $event->title));
        $leagueSlug = Str::slug($leagueName);
        if ($leagueSlug === '') $leagueSlug = 'league';
        $baseSlug = $leagueSlug;
        $i = 2;
        while (League::where('slug', $leagueSlug)->exists()) {
            $leagueSlug = $baseSlug . '-' . $i++;
        }

        $topLeague = League::create([
            'organizer_id' => $event->organizer_id,
            'name'         => $leagueName,
            'slug'         => $leagueSlug,
            'direction'    => $direction,
            'status'       => League::STATUS_ACTIVE,
        ]);

        // 2. Season (внутри лиги)
        $seasonName = $event->title . ' — Сезон';
        $slug = Str::slug($seasonName) . '-' . Str::random(6);

        $season = TournamentSeason::create([
            'organizer_id' => $event->organizer_id,
            'league_id'    => $topLeague->id,
            'name'         => $seasonName,
            'slug'         => $slug,
            'direction'    => $direction,
            'starts_at'    => $event->starts_at,
            'ends_at'      => $this->estimateEndDate($event),
            'status'       => TournamentSeason::STATUS_ACTIVE,
            'config'       => [
                'auto_promotion'  => false,
                'source_event_id' => $event->id,
            ],
        ]);

        // 3. Division (бывшая "лига" внутри сезона)
        $maxTeams = (int) ($data['tournament_teams_count'] ?? $event->tournament_teams_count ?? 4);

        $league = TournamentLeague::create([
            'season_id'  => $season->id,
            'name'       => $divisionName,
            'level'      => 1,
            'sort_order' => 1,
            'max_teams'  => $maxTeams > 0 ? $maxTeams : null,
            'config'     => [],
        ]);

        // 3. Создаём SeasonEvent для всех occurrence
        $this->createSeasonEventsForOccurrences($event, $season, $league);

        // 4. Привязка
        $event->season_id = $season->id;
        $event->saveQuietly();

        Log::info('TournamentSeasonAutoCreate: new league+season', [
            'event_id'      => $event->id,
            'league_id'     => $topLeague->id,
            'season_id'     => $season->id,
            'division_id'   => $league->id,
            'division_name' => $divisionName,
        ]);

        return $season;
    }

    /**
     * Привязать к существующему Season + League.
     */
    private function attachToExisting(Event $event, array $data): ?TournamentSeason
    {
        $seasonId = (int) ($data['existing_season_id'] ?? 0);
        $leagueId = (int) ($data['existing_league_id'] ?? 0);

        if ($seasonId <= 0 || $leagueId <= 0) {
            Log::warning('TournamentSeasonAutoCreate: existing mode but no season/league selected', [
                'event_id' => $event->id,
            ]);
            // Fallback: создаём новый
            return $this->createNew($event, $data);
        }

        // Проверяем что сезон принадлежит этому организатору
        $season = TournamentSeason::where('id', $seasonId)
            ->where('organizer_id', $event->organizer_id)
            ->first();

        if (!$season) {
            Log::warning('TournamentSeasonAutoCreate: season not found or wrong organizer', [
                'event_id'  => $event->id,
                'season_id' => $seasonId,
            ]);
            return $this->createNew($event, $data);
        }

        // Проверяем что лига принадлежит этому сезону
        $league = TournamentLeague::where('id', $leagueId)
            ->where('season_id', $season->id)
            ->first();

        if (!$league) {
            Log::warning('TournamentSeasonAutoCreate: league not found in season', [
                'event_id'  => $event->id,
                'league_id' => $leagueId,
            ]);
            return $this->createNew($event, $data);
        }

        // Определяем round_number
        $nextRound = TournamentSeasonEvent::where('season_id', $season->id)
            ->where('league_id', $league->id)
            ->max('round_number') ?? 0;
        $nextRound++;

        // SeasonEvent
        TournamentSeasonEvent::create([
            'season_id'     => $season->id,
            'league_id'     => $league->id,
            'event_id'      => $event->id,
            'occurrence_id' => null,
            'round_number'  => $nextRound,
            'status'        => TournamentSeasonEvent::STATUS_PENDING,
        ]);

        // Привязка
        $event->season_id = $season->id;
        $event->saveQuietly();

        Log::info('TournamentSeasonAutoCreate: attached to existing', [
            'event_id'     => $event->id,
            'season_id'    => $season->id,
            'league_id'    => $league->id,
            'round_number' => $nextRound,
        ]);

        return $season;
    }

    /**
     * Синхронизировать SeasonEvents для события после expansion occurrences.
     * Вызывается из OccurrenceExpansionService если event — tournament с season_id.
     * Для каждого нового occurrence ищет правильный сезон по дате в той же лиге.
     */
    public function syncSeasonEventsAfterExpand(Event $event): void
    {
        if ($event->format !== 'tournament' || !$event->season_id) {
            return;
        }

        $currentSeason = TournamentSeason::find($event->season_id);

        // Если у сезона нет league_id — старая логика без date-routing
        if (!$currentSeason || !$currentSeason->league_id) {
            $league = TournamentLeague::where('season_id', $event->season_id)->first();
            if ($currentSeason && $league) {
                $this->createSeasonEventsForOccurrences($event, $currentSeason, $league);
            }
            return;
        }

        $parentLeagueId = (int) $currentSeason->league_id;
        $tz = $event->timezone ?: 'UTC';

        $occurrences = $event->occurrences()
            ->whereNull('cancelled_at')
            ->orderBy('starts_at')
            ->get();

        foreach ($occurrences as $occ) {
            // Уже привязан — не трогаем
            if (TournamentSeasonEvent::where('occurrence_id', $occ->id)->exists()) {
                continue;
            }

            $localDate = \Carbon\Carbon::parse($occ->starts_at, 'UTC')
                ->setTimezone($tz)
                ->toDateString();

            $resolved = $this->resolveSeasonForDate($parentLeagueId, $localDate);

            if (!$resolved) {
                Log::info('syncSeasonEventsAfterExpand: no season for date, skipping', [
                    'event_id'      => $event->id,
                    'occurrence_id' => $occ->id,
                    'date'          => $localDate,
                ]);
                continue;
            }

            ['season' => $targetSeason, 'league' => $targetLeague] = $resolved;

            $nextRound = TournamentSeasonEvent::where('season_id', $targetSeason->id)
                ->where('league_id', $targetLeague->id)
                ->max('round_number') ?? 0;

            TournamentSeasonEvent::create([
                'season_id'     => $targetSeason->id,
                'league_id'     => $targetLeague->id,
                'event_id'      => $event->id,
                'occurrence_id' => $occ->id,
                'round_number'  => $nextRound + 1,
                'status'        => TournamentSeasonEvent::STATUS_PENDING,
            ]);
        }

        $this->updateEventSeasonId($event, $parentLeagueId);
    }

    /**
     * Полная перепривязка всех occurrences события к правильным сезонам по датам.
     * Используется для backfill через artisan-команду и обратной силы.
     *
     * @return array{created: int, moved: int, skipped: int}
     */
    public function syncAllOccurrencesToCorrectSeasons(Event $event): array
    {
        if ($event->format !== 'tournament' || !$event->season_id) {
            return ['created' => 0, 'moved' => 0, 'skipped' => 0];
        }

        $currentSeason = TournamentSeason::find($event->season_id);
        if (!$currentSeason || !$currentSeason->league_id) {
            return ['created' => 0, 'moved' => 0, 'skipped' => 0];
        }

        $parentLeagueId = (int) $currentSeason->league_id;
        $tz = $event->timezone ?: 'UTC';

        $occurrences = $event->occurrences()
            ->whereNull('cancelled_at')
            ->orderBy('starts_at')
            ->get();

        $created = 0;
        $moved   = 0;
        $skipped = 0;

        foreach ($occurrences as $occ) {
            $localDate = \Carbon\Carbon::parse($occ->starts_at, 'UTC')
                ->setTimezone($tz)
                ->toDateString();

            $resolved = $this->resolveSeasonForDate($parentLeagueId, $localDate);

            if (!$resolved) {
                // Нет сезона на эту дату — удаляем существующую запись если есть
                $deleted = TournamentSeasonEvent::where('occurrence_id', $occ->id)->delete();
                if ($deleted) {
                    Log::info('syncAllOccurrences: removed from season (no covering season)', [
                        'event_id'      => $event->id,
                        'occurrence_id' => $occ->id,
                        'date'          => $localDate,
                    ]);
                }
                $skipped++;
                continue;
            }

            ['season' => $targetSeason, 'league' => $targetLeague] = $resolved;

            $existing = TournamentSeasonEvent::where('occurrence_id', $occ->id)->first();

            if ($existing) {
                if ((int) $existing->season_id === (int) $targetSeason->id
                    && (int) $existing->league_id === (int) $targetLeague->id) {
                    // Уже правильно
                    continue;
                }
                // Перемещаем в нужный сезон
                $existing->update([
                    'season_id' => $targetSeason->id,
                    'league_id' => $targetLeague->id,
                    'event_id'  => $event->id,
                ]);
                Log::info('syncAllOccurrences: moved occurrence to correct season', [
                    'event_id'       => $event->id,
                    'occurrence_id'  => $occ->id,
                    'from_season_id' => $existing->getOriginal('season_id'),
                    'to_season_id'   => $targetSeason->id,
                ]);
                $moved++;
            } else {
                TournamentSeasonEvent::create([
                    'season_id'     => $targetSeason->id,
                    'league_id'     => $targetLeague->id,
                    'event_id'      => $event->id,
                    'occurrence_id' => $occ->id,
                    'round_number'  => 0,
                    'status'        => TournamentSeasonEvent::STATUS_PENDING,
                ]);
                $created++;
            }
        }

        // Перенумеровать раунды внутри каждого сезона
        $this->renumberRounds($event);

        // Обновить event.season_id на актуальный сезон
        $this->updateEventSeasonId($event, $parentLeagueId);

        return ['created' => $created, 'moved' => $moved, 'skipped' => $skipped];
    }

    /**
     * Найти сезон и дивизион для заданной локальной даты в рамках лиги.
     *
     * @return array{season: TournamentSeason, league: TournamentLeague}|null
     */
    private function resolveSeasonForDate(int $parentLeagueId, string $localDateStr): ?array
    {
        $season = TournamentSeason::where('league_id', $parentLeagueId)
            ->where('status', '!=', TournamentSeason::STATUS_DRAFT)
            ->whereDate('starts_at', '<=', $localDateStr)
            ->whereDate('ends_at', '>=', $localDateStr)
            ->orderBy('starts_at', 'desc')
            ->first();

        if (!$season) {
            return null;
        }

        $league = TournamentLeague::where('season_id', $season->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        if (!$league) {
            return null;
        }

        return ['season' => $season, 'league' => $league];
    }

    /**
     * Обновить event.season_id на сезон ближайшего предстоящего occurrence.
     * Если предстоящих нет — берёт сезон последнего прошедшего.
     */
    private function updateEventSeasonId(Event $event, int $parentLeagueId): void
    {
        $tz = $event->timezone ?: 'UTC';

        // Ближайший предстоящий
        $nextOcc = $event->occurrences()
            ->whereNull('cancelled_at')
            ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->first();

        // Если нет — последний прошедший
        $occ = $nextOcc ?? $event->occurrences()
            ->whereNull('cancelled_at')
            ->orderBy('starts_at', 'desc')
            ->first();

        if (!$occ) {
            return;
        }

        $localDate = \Carbon\Carbon::parse($occ->starts_at, 'UTC')
            ->setTimezone($tz)
            ->toDateString();

        $resolved = $this->resolveSeasonForDate($parentLeagueId, $localDate);
        if (!$resolved) {
            return;
        }

        $targetSeasonId = (int) $resolved['season']->id;
        if ((int) $event->season_id !== $targetSeasonId) {
            Log::info('updateEventSeasonId: season changed', [
                'event_id'      => $event->id,
                'old_season_id' => $event->season_id,
                'new_season_id' => $targetSeasonId,
            ]);
            $event->season_id = $targetSeasonId;
            $event->saveQuietly();
        }
    }

    /**
     * Перенумеровать round_number внутри каждого сезона+дивизиона для данного события
     * по порядку дат occurrence.
     */
    private function renumberRounds(Event $event): void
    {
        $rows = \Illuminate\Support\Facades\DB::table('tournament_season_events as tse')
            ->join('event_occurrences as eo', 'eo.id', '=', 'tse.occurrence_id')
            ->where('tse.event_id', $event->id)
            ->whereNotNull('tse.occurrence_id')
            ->orderBy('tse.season_id')
            ->orderBy('tse.league_id')
            ->orderBy('eo.starts_at')
            ->select('tse.id', 'tse.season_id', 'tse.league_id')
            ->get();

        $counters = [];
        foreach ($rows as $row) {
            $key = $row->season_id . ':' . $row->league_id;
            $counters[$key] = ($counters[$key] ?? 0) + 1;
            \Illuminate\Support\Facades\DB::table('tournament_season_events')
                ->where('id', $row->id)
                ->update(['round_number' => $counters[$key]]);
        }
    }

    /**
     * Создать SeasonEvent для каждого occurrence (старая логика без date-routing).
     */
    private function createSeasonEventsForOccurrences(Event $event, TournamentSeason $season, TournamentLeague $league): void
    {
        $occurrences = $event->occurrences()
            ->whereNull('cancelled_at')
            ->orderBy('starts_at')
            ->get();

        foreach ($occurrences as $i => $occ) {
            $exists = TournamentSeasonEvent::where('season_id', $season->id)
                ->where('occurrence_id', $occ->id)
                ->exists();

            if (!$exists) {
                TournamentSeasonEvent::create([
                    'season_id'     => $season->id,
                    'league_id'     => $league->id,
                    'event_id'      => $event->id,
                    'occurrence_id' => $occ->id,
                    'round_number'  => $i + 1,
                    'status'        => TournamentSeasonEvent::STATUS_PENDING,
                ]);
            }
        }
    }

    /**
     * Определить дату окончания из RRULE (UNTIL / COUNT).
     */
    private function estimateEndDate(Event $event): ?\Carbon\Carbon
    {
        $rule = (string) ($event->recurrence_rule ?? '');

        if (preg_match('/UNTIL=(\d{8}T\d{6}Z?)/i', $rule, $m)) {
            try {
                return \Carbon\Carbon::parse($m[1]);
            } catch (\Throwable) {}
        }

        if (preg_match('/COUNT=(\d+)/i', $rule, $mc)) {
            $count = (int) $mc[1];
            if ($count > 0 && $event->starts_at) {
                $interval = 1;
                if (preg_match('/INTERVAL=(\d+)/i', $rule, $mi)) {
                    $interval = max(1, (int) $mi[1]);
                }

                $freq = 'weeks';
                if (preg_match('/FREQ=(DAILY|WEEKLY|MONTHLY|YEARLY)/i', $rule, $mf)) {
                    $freq = match (strtoupper($mf[1])) {
                        'DAILY'   => 'days',
                        'WEEKLY'  => 'weeks',
                        'MONTHLY' => 'months',
                        'YEARLY'  => 'years',
                        default   => 'weeks',
                    };
                }

                return \Carbon\Carbon::parse($event->starts_at)
                    ->add($freq, $count * $interval);
            }
        }

        return null;
    }
}
