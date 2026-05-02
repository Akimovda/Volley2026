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
     */
    public function syncSeasonEventsAfterExpand(Event $event): void
    {
        if ($event->format !== 'tournament' || !$event->season_id) {
            return;
        }

        $season  = TournamentSeason::find($event->season_id);
        $league  = TournamentLeague::where('season_id', $event->season_id)->first();

        if (!$season || !$league) {
            return;
        }

        $this->createSeasonEventsForOccurrences($event, $season, $league);
    }

    /**
     * Создать SeasonEvent для каждого occurrence.
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
