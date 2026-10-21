<?php

namespace App\Support;

use App\Models\BroadcastLink;
use App\Models\TournamentMatch;

/**
 * Набор адресов действий экрана поочкового ввода. Экран и фрагмент — один код (rally_screen/rally_live),
 * для организатора адреса ведут в обычные роуты, для ведущего по токен-ссылке — в /s/{token}/….
 * Ключ `set` — шаблон с {set}.
 */
class RallyUrls
{
    public static function forOrganizer(TournamentMatch $match): array
    {
        return [
            'mode'         => 'organizer',
            'point'        => route('tournament.matches.rally.point', $match),
            'undo'         => route('tournament.matches.rally.undo', $match),
            'finalize'     => route('tournament.matches.rally.finalize', $match),
            'fragment'     => route('tournament.matches.rally.fragment', $match),
            'lineup_start' => route('tournament.matches.rally.lineup.start', $match),
            'lineup_event' => route('tournament.matches.rally.lineup.event', $match),
            'set'          => route('tournament.matches.rally.form', [$match, 'set' => '{set}']),
            'back'         => route('tournament.matches.score.form', $match),
            'pdf'          => route('tournament.matches.pdf_stats', $match),
        ];
    }

    public static function forScorer(BroadcastLink $link, TournamentMatch $match): array
    {
        $t = $link->token;

        return [
            'mode'         => 'scorer',
            'point'        => route('scorer.point', [$t, $match]),
            'undo'         => route('scorer.undo', [$t, $match]),
            'finalize'     => route('scorer.finalize', [$t, $match]),
            'fragment'     => route('scorer.fragment', [$t, $match]),
            'lineup_start' => route('scorer.lineup.start', [$t, $match]),
            'lineup_event' => route('scorer.lineup.event', [$t, $match]),
            'set'          => route('scorer.match', [$t, $match, 'set' => '{set}']),
            'back'         => route('scorer.hub', $t),
            'pdf'          => null,
        ];
    }

    public static function setUrl(array $urls, int $set): string
    {
        return str_replace(['{set}', '%7Bset%7D'], (string) $set, $urls['set']);
    }
}
