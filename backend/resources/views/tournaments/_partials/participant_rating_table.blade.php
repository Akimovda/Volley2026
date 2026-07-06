{{--
    Таблица рейтинга участников турнира в стиле /players/rating: турнирная
    статистика + общий рейтинг (CR/игры/winrate). Данные собираются в
    TournamentStatsService::getParticipantRatingTable() — формулы CR/winrate
    идентичны PlayerRatingController (max(0, mu-3*sigma), wins/matches*100).

    @var \App\Models\Event $event
--}}
@php
$ratingData = app(\App\Services\TournamentStatsService::class)->getParticipantRatingTable($event);
$rows = $ratingData['rows'];
$hasPoints = $ratingData['hasPoints'];
$hiddenCount = $ratingData['hiddenCount'];
$direction = $ratingData['direction'];
@endphp

<div class="card p-3 mb-3">
    <div class="b-700 f-16 mb-2">{{ __('tournaments.pub_player_ranking') }}</div>

    @if(empty($rows))
    <div class="f-13" style="opacity:.5">Нет данных. Статистика появится после первых сыгранных матчей.</div>
    @else
    <div class="d-none-desktop f-12 mb-1" style="opacity:.4;display:none">👆 {{ __('tournaments.pub_swipe_left_hint') }}</div>
    <style>
        @media (max-width:768px) { .d-none-desktop { display:block !important; } }
    </style>
    <div class="table-scrollable">
        <div class="table-drag-indicator"></div>
        <table class="table f-14">
            <thead>
                <tr>
                    <th class="p-1" rowspan="2" style="width:32px;text-align:left;vertical-align:bottom">#</th>
                    <th class="p-1" rowspan="2" style="text-align:left;vertical-align:bottom">{{ __('tournaments.stats_col_player') }}</th>
                    <th class="p-1" colspan="{{ $hasPoints ? 3 : 2 }}" style="text-align:center;border-bottom:1px solid rgba(128,128,128,.15)">{{ __('tournaments.in_tournament') }}</th>
                    <th class="p-1" colspan="3" style="text-align:center;border-bottom:1px solid rgba(128,128,128,.15)">{{ __('tournaments.overall_rating') }}</th>
                </tr>
                <tr style="border-bottom:2px solid rgba(128,128,128,.2)">
                    <th class="p-1" style="text-align:center">{{ __('tournaments.games') }}</th>
                    <th class="p-1" style="text-align:center">{{ __('tournaments.wins') }}</th>
                    @if($hasPoints)
                    <th class="p-1" style="text-align:center">{{ __('tournaments.points') }}</th>
                    @endif
                    <th class="p-1 b-600" style="text-align:center">{{ __('players.conservative_rating') }}</th>
                    <th class="p-1" style="text-align:center">{{ __('tournaments.games') }}</th>
                    <th class="p-1" style="text-align:center">Win%</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $i => $r)
                <tr style="border-bottom:1px solid rgba(128,128,128,.1)">
                    <td class="p-1">
                        @if($i < 3)
                        <span class="f-16">{{ ['🥇','🥈','🥉'][$i] }}</span>
                        @else
                        <span style="opacity:.5">{{ $i + 1 }}</span>
                        @endif
                    </td>
                    <td class="p-1">
                        <div class="d-flex gap-1" style="align-items:center">
                            <img src="{{ $r['user']?->profile_photo_url }}" alt="" loading="lazy" style="width:24px;height:24px;border-radius:50%;object-fit:cover;flex-shrink:0">
                            <a href="{{ route('users.show', $r['user_id']) }}" class="blink b-600">{{ $r['user']?->displayName() ?? '#' . $r['user_id'] }}</a>
                        </div>
                    </td>
                    <td class="p-1" style="text-align:center">{{ $r['t_games'] }}</td>
                    <td class="p-1" style="text-align:center;color:#10b981">{{ $r['t_wins'] }}</td>
                    @if($hasPoints)
                    <td class="p-1 b-700" style="text-align:center;color:#E7612F">{{ $r['t_points'] }}</td>
                    @endif
                    <td class="p-1 b-700" style="text-align:center;color:#E7612F">{{ number_format($r['cr'], 1) }}</td>
                    <td class="p-1" style="text-align:center;opacity:.7">{{ $r['o_games'] }}</td>
                    <td class="p-1" style="text-align:center;opacity:.7">{{ $r['o_winrate'] }}%</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if($hiddenCount > 0)
    <div class="f-13 mt-1" style="opacity:.5">{{ __('tournaments.rating_hidden_no_games', ['count' => $hiddenCount]) }}</div>
    @endif
    @endif

    <div class="mt-2 text-center">
        <a href="{{ route('players.rating', ['direction' => $direction]) }}" class="btn btn-secondary btn-small">{{ __('tournaments.full_rating_link') }} →</a>
    </div>
</div>
