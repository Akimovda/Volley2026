<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventTeam;
use App\Models\TournamentMatch;
use App\Models\TournamentStage;
use Illuminate\Support\Facades\DB;

class TournamentNotificationService
{
    public function __construct(
        private UserNotificationService $notificationService,
    ) {}

    /**
     * Уведомление: матч скоро начнётся (обеим командам).
     */
    public function notifyUpcomingMatch(TournamentMatch $match, int $minutesBefore = 15): void
    {
        $stage = $match->stage;
        $event = $stage->event;

        $homeTeam = $match->teamHome;
        $awayTeam = $match->teamAway;

        if (!$homeTeam || !$awayTeam) return;

        $title = "Ваш матч через {$minutesBefore} мин!";
        $body = "{$homeTeam->name} vs {$awayTeam->name} · {$event->title}";
        if ($match->court) {
            $body .= " · {$match->court}";
        }

        $payload = [
            'type'     => 'tournament_match_upcoming',
            'event_id' => $event->id,
            'match_id' => $match->id,
            'url'      => route('tournament.public.show', $event),
        ];

        $this->notifyTeamMembers($homeTeam, 'tournament_match_upcoming', $title, $body, $payload);
        $this->notifyTeamMembers($awayTeam, 'tournament_match_upcoming', $title, $body, $payload);
    }

    /**
     * Уведомление: результат матча (обеим командам).
     */
    public function notifyMatchResult(TournamentMatch $match): void
    {
        if (!$match->isCompleted()) return;

        $stage = $match->stage;
        $event = $stage->event;
        $homeTeam = $match->teamHome;
        $awayTeam = $match->teamAway;
        $winnerTeam = $match->winner;

        if (!$homeTeam || !$awayTeam) return;

        $score = $match->scoreFormatted() ?? '';
        $payload = [
            'type'     => 'tournament_match_result',
            'event_id' => $event->id,
            'match_id' => $match->id,
            'url'      => route('tournament.public.show', $event),
        ];

        // Победители
        if ($winnerTeam) {
            $title = "Победа! {$score}";
            $body = "{$winnerTeam->name} побеждает в матче · {$event->title}";
            $this->notifyTeamMembers($winnerTeam, 'tournament_match_result', $title, $body, $payload);
        }

        // Проигравшие
        $loserTeam = $match->winner_team_id === $homeTeam->id ? $awayTeam : $homeTeam;
        $title = "Матч завершён · {$score}";
        $body = "{$homeTeam->name} vs {$awayTeam->name} · {$event->title}";
        $this->notifyTeamMembers($loserTeam, 'tournament_match_result', $title, $body, $payload);
    }

    /**
     * Уведомление: команда продвинулась в плей-офф.
     */
    public function notifyAdvancement(EventTeam $team, Event $event, string $stageName): void
    {
        $title = "Вы в плей-офф!";
        $body = "Команда {$team->name} прошла в {$stageName} · {$event->title}";

        $payload = [
            'type'     => 'tournament_advancement',
            'event_id' => $event->id,
            'url'      => route('tournament.public.show', $event),
        ];

        $this->notifyTeamMembers($team, 'tournament_advancement', $title, $body, $payload);
    }

    /**
     * Уведомление: турнир завершён, итоги.
     */
    public function notifyTournamentCompleted(Event $event): void
    {
        $title = "Турнир завершён!";
        $body = "{$event->title} — смотрите итоги и статистику";

        $payload = [
            'type'     => 'tournament_completed',
            'event_id' => $event->id,
            'url'      => route('tournament.public.show', [$event, 'tab' => 'results']),
        ];

        // Уведомляем всех участников
        $userIds = DB::table('event_team_members')
            ->join('event_teams', 'event_teams.id', '=', 'event_team_members.event_team_id')
            ->where('event_teams.event_id', $event->id)
            ->where('event_team_members.confirmation_status', 'confirmed')
            ->distinct()
            ->pluck('event_team_members.user_id');

        foreach ($userIds as $userId) {
            $this->notificationService->create(
                userId:   $userId,
                type:     'tournament_completed',
                title:    $title,
                body:     $body,
                payload:  $payload,
                channels: ['in_app', 'telegram', 'vk', 'max'],
            );
        }
    }

    /**
     * Уведомление: фото добавлены.
     */

    /**
     * Уведомление: турнир начинается.
     */
    public function notifyTournamentStarted(Event $event): void
    {
        $title = "Турнир начинается!";
        $body = "{$event->title} — первые матчи стартуют";

        $payload = [
            'type'     => 'tournament_started',
            'event_id' => $event->id,
            'url'      => route('tournament.public.show', $event),
        ];

        $userIds = DB::table('event_team_members')
            ->join('event_teams', 'event_teams.id', '=', 'event_team_members.event_team_id')
            ->where('event_teams.event_id', $event->id)
            ->where('event_team_members.confirmation_status', 'confirmed')
            ->distinct()
            ->pluck('event_team_members.user_id');

        foreach ($userIds as $userId) {
            $this->notificationService->create(
                userId:   $userId,
                type:     'tournament_started',
                title:    $title,
                body:     $body,
                payload:  $payload,
                channels: ['in_app', 'telegram', 'vk', 'max'],
            );
        }
    }

        public function notifyPhotosAdded(Event $event): void
    {
        $title = "Фото добавлены!";
        $body = "К турниру {$event->title} добавлены фотографии";

        $payload = [
            'type'     => 'tournament_photos',
            'event_id' => $event->id,
            'url'      => route('tournament.public.show', [$event, 'tab' => 'results']),
        ];

        $userIds = DB::table('event_team_members')
            ->join('event_teams', 'event_teams.id', '=', 'event_team_members.event_team_id')
            ->where('event_teams.event_id', $event->id)
            ->where('event_team_members.confirmation_status', 'confirmed')
            ->distinct()
            ->pluck('event_team_members.user_id');

        foreach ($userIds as $userId) {
            $this->notificationService->create(
                userId:   $userId,
                type:     'tournament_photos',
                title:    $title,
                body:     $body,
                payload:  $payload,
                channels: ['in_app', 'telegram', 'vk', 'max'],
            );
        }
    }

    /* ── Private ── */


    /**
     * Уведомление о повышении в лигу.
     */
    public function notifyPromotion(EventTeam $team, Event $event, string $fromLeague, string $toLeague): void
    {
        $title = "Повышение!";
        $body = "Команда {$team->name} переходит из «{$fromLeague}» в «{$toLeague}» · {$event->title}";

        $payload = [
            'type'     => 'season_promotion',
            'event_id' => $event->id,
            'url'      => route('tournament.public.show', $event),
        ];

        $this->notifyTeamMembers($team, 'season_promotion', $title, $body, $payload);
    }

    /**
     * Уведомление о выбывании в резерв.
     */
    public function notifyElimination(EventTeam $team, Event $event, string $leagueName, int $reservePosition): void
    {
        $title = "Резерв";
        $body = "Команда {$team->name} переходит в резерв лиги «{$leagueName}» (позиция #{$reservePosition}) · {$event->title}";

        $payload = [
            'type'     => 'season_elimination',
            'event_id' => $event->id,
            'url'      => route('tournament.public.show', $event),
        ];

        $this->notifyTeamMembers($team, 'season_elimination', $title, $body, $payload);
    }

    /**
     * Уведомление о переходе из резерва в основной состав.
     */
    public function notifyActivatedFromReserve(EventTeam $team, string $leagueName): void
    {
        $title = "Вы в основном составе!";
        $body = "Команда {$team->name} переведена из резерва в лигу «{$leagueName}»";

        $payload = [
            'type' => 'season_reserve_activated',
        ];

        $this->notifyTeamMembers($team, 'season_reserve_activated', $title, $body, $payload);
    }

    /**
     * Уведомление: подтвердите участие в следующем туре.
     */
    public function notifyConfirmParticipation(EventTeam $team, Event $event, string $leagueName): void
    {
        $title = "Подтвердите участие";
        $body = "Вы автоматически записаны на следующий тур лиги «{$leagueName}». Подтвердите участие до начала.";

        $payload = [
            'type'     => 'season_confirm_participation',
            'event_id' => $event->id,
            'url'      => route('events.show', $event),
        ];

        $this->notifyTeamMembers($team, 'season_confirm_participation', $title, $body, $payload);
    }

    private function notifyTeamMembers(EventTeam $team, string $type, string $title, string $body, array $payload): void
    {
        $userIds = DB::table('event_team_members')
            ->where('event_team_id', $team->id)
            ->where('confirmation_status', 'confirmed')
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            $this->notificationService->create(
                userId:   $userId,
                type:     $type,
                title:    $title,
                body:     $body,
                payload:  $payload,
                channels: ['in_app', 'telegram', 'vk', 'max'],
            );
        }
    }
}
