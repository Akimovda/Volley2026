<?php

namespace App\Services;

use App\Models\TournamentMatch;
use App\Support\RallyActor;

/**
 * Действия экрана поочкового ввода (очко, отмена, старт партии, замены): правила валидации и вызовы сервисов —
 * один код для организаторских роутов (TournamentController) и для ввода по токен-ссылке (ScorerController).
 * Исключения InvalidArgumentException (текст для пользователя) отдаёт вызывающий.
 */
class RallyInputService
{
    public function __construct(
        private MatchRallyService $rally,
        private MatchLineupService $lineupService,
    ) {}

    public static function pointRules(): array
    {
        return [
            'set_number'        => 'required|integer|min:1|max:5',
            'team_id'           => 'required|integer',
            'action_type'       => 'required|string',
            'player_id'         => 'nullable|integer',
            'dig_user_id'       => 'nullable|integer',
            'assist_user_id'    => 'nullable|integer',
            'client_request_id' => 'nullable|uuid',
        ];
    }

    public static function undoRules(): array
    {
        return [
            'set_number'        => 'required|integer|min:1|max:5',
            'expected_last_seq' => 'nullable|integer|min:0',
        ];
    }

    public static function startRules(): array
    {
        return [
            'set_number'                 => 'required|integer|min:1|max:5',
            'first_serve_team_id'        => 'required|integer',
            'lineups'                    => 'required|array',
            'lineups.*.court'            => 'required|array',
            'lineups.*.court.*.user_id'  => 'required|integer',
            'lineups.*.court.*.position' => 'required|string|max:20',
            'lineups.*.court.*.zone'     => 'required|integer|min:1|max:12',
            'lineups.*.libero'           => 'nullable|integer',
        ];
    }

    public static function eventRules(): array
    {
        return [
            'set_number'        => 'required|integer|min:1|max:5',
            'team_id'           => 'required|integer',
            'type'              => 'required|in:substitution,libero_in,libero_out',
            'out_user_id'       => 'nullable|integer',
            'in_user_id'        => 'nullable|integer',
            'client_request_id' => 'nullable|uuid',
        ];
    }

    public function point(TournamentMatch $match, array $v, RallyActor $actor): void
    {
        $this->rally->recordPoint(
            $match,
            (int) $v['set_number'],
            (int) $v['team_id'],
            $v['action_type'],
            isset($v['player_id']) ? (int) $v['player_id'] : null,
            isset($v['dig_user_id']) ? (int) $v['dig_user_id'] : null,
            isset($v['assist_user_id']) ? (int) $v['assist_user_id'] : null,
            $actor->user,
            $v['client_request_id'] ?? null,
            $actor->name,
            $actor->linkId,
        );
    }

    public function undo(TournamentMatch $match, array $v, RallyActor $actor): void
    {
        $this->rally->undoLast(
            $match,
            (int) $v['set_number'],
            isset($v['expected_last_seq']) ? (int) $v['expected_last_seq'] : null,
            $actor->user,
            $actor->name,
            $actor->linkId,
        );
    }

    public function setStart(TournamentMatch $match, array $v, RallyActor $actor): void
    {
        $lineups = [];
        foreach ($v['lineups'] as $teamId => $team) {
            $lineups[(int) $teamId] = [
                'court'  => array_values($team['court']),
                'libero' => $team['libero'] ?? null,
            ];
        }

        $this->lineupService->setSetStart(
            $match, (int) $v['set_number'], $lineups, (int) $v['first_serve_team_id'],
            $actor->user, $actor->name, $actor->linkId
        );
    }

    public function lineupEvent(TournamentMatch $match, array $v, RallyActor $actor): void
    {
        $svc = $this->lineupService;
        $set = (int) $v['set_number'];
        $team = (int) $v['team_id'];
        $out = isset($v['out_user_id']) ? (int) $v['out_user_id'] : 0;
        $in = isset($v['in_user_id']) ? (int) $v['in_user_id'] : 0;
        $rid = $v['client_request_id'] ?? null;

        match ($v['type']) {
            'substitution' => $svc->substitute($match, $set, $team, $out, $in, $actor->user, $actor->name, $rid, $actor->linkId),
            'libero_in'    => $svc->liberoIn($match, $set, $team, $out, $actor->user, $actor->name, $rid, $actor->linkId),
            'libero_out'   => $svc->liberoOut($match, $set, $team, $actor->user, $actor->name, $rid, $actor->linkId),
        };
    }
}
