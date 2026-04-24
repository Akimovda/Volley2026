<?php

namespace App\Services;

use App\Models\Event;
use App\Models\User;
use App\Models\TournamentSeason;
use App\Models\TournamentLeague;
use App\Models\TournamentSeasonEvent;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TournamentSeasonService
{
    /**
     * Создать сезон.
     */
    public function createSeason(User $organizer, array $data): TournamentSeason
    {
        $slug = $data['slug'] ?? Str::slug($data['name']);

        // Уникальность slug
        $baseSlug = $slug;
        $i = 1;
        while (TournamentSeason::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $i++;
        }

        return TournamentSeason::create([
            'organizer_id' => $organizer->id,
            'league_id'    => $data['league_id'] ?? null,
            'name'         => $data['name'],
            'slug'         => $slug,
            'direction'    => $data['direction'] ?? 'classic',
            'starts_at'    => $data['starts_at'] ?? null,
            'ends_at'      => $data['ends_at'] ?? null,
            'status'       => TournamentSeason::STATUS_DRAFT,
            'config'       => $data['config'] ?? [],
        ]);
    }

    /**
     * Обновить сезон.
     */
    public function updateSeason(TournamentSeason $season, array $data): TournamentSeason
    {
        $fillable = ['name', 'league_id', 'direction', 'starts_at', 'ends_at', 'status', 'config'];
        $season->update(array_intersect_key($data, array_flip($fillable)));

        if (isset($data['slug']) && $data['slug'] !== $season->slug) {
            $slug = Str::slug($data['slug']);
            if (!TournamentSeason::where('slug', $slug)->where('id', '!=', $season->id)->exists()) {
                $season->update(['slug' => $slug]);
            }
        }

        return $season->fresh();
    }

    /**
     * Активировать сезон.
     */
    public function activate(TournamentSeason $season): TournamentSeason
    {
        if ($season->leagues()->count() === 0) {
            throw new InvalidArgumentException('Создайте хотя бы один дивизион перед активацией.');
        }

        $season->update(['status' => TournamentSeason::STATUS_ACTIVE]);
        return $season->fresh();
    }

    /**
     * Завершить сезон.
     */
    public function complete(TournamentSeason $season): TournamentSeason
    {
        $season->update(['status' => TournamentSeason::STATUS_COMPLETED]);
        return $season->fresh();
    }

    /**
     * Привязать турнир (event) к сезону и лиге.
     */
    public function attachEvent(
        TournamentSeason $season,
        TournamentLeague $league,
        Event $event,
        ?int $roundNumber = null,
    ): TournamentSeasonEvent {
        if ($league->season_id !== $season->id) {
            throw new InvalidArgumentException('Дивизион не принадлежит этому сезону.');
        }

        $roundNumber = $roundNumber ?? ($season->currentRound() + 1);

        return TournamentSeasonEvent::create([
            'season_id'    => $season->id,
            'league_id'    => $league->id,
            'event_id'     => $event->id,
            'round_number' => $roundNumber,
            'status'       => TournamentSeasonEvent::STATUS_PENDING,
        ]);
    }

    /**
     * Отвязать турнир от сезона.
     */
    public function detachEvent(TournamentSeason $season, Event $event): void
    {
        TournamentSeasonEvent::where('season_id', $season->id)
            ->where('event_id', $event->id)
            ->delete();
    }

    /**
     * Отметить тур как завершённый.
     */
    public function completeRound(TournamentSeasonEvent $seasonEvent): TournamentSeasonEvent
    {
        $seasonEvent->update(['status' => TournamentSeasonEvent::STATUS_COMPLETED]);
        return $seasonEvent->fresh();
    }

    /**
     * Получить все сезоны организатора.
     */
    public function getByOrganizer(User $organizer, ?string $status = null): Collection
    {
        $query = TournamentSeason::where('organizer_id', $organizer->id)
            ->orderByDesc('starts_at');

        if ($status) {
            $query->where('status', $status);
        }

        return $query->with('leagues')->get();
    }

    /**
     * Удалить сезон (только draft).
     */
    public function deleteSeason(TournamentSeason $season): void
    {
        if (!$season->isDraft()) {
            throw new InvalidArgumentException('Можно удалить только сезон в статусе draft.');
        }

        $season->delete();
    }
}
