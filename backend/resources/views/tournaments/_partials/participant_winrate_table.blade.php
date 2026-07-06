{{--
    Старая таблица рейтинга участников турнира (WinRate/сеты/разница очков).
    Заменена на participant_rating_table.blade.php (стиль /players/rating).
    Файл оставлен для отката — не подключается по умолчанию.

    @var \App\Models\Event $event
--}}
@php
$topPlayers = app(\App\Services\TournamentStatsService::class)->getTopPlayers($event->id, 20);
@endphp

<div class="card p-3 mb-3">
    <div class="b-700 f-16 mb-2">{{ __('tournaments.pub_player_ranking') }}</div>

    @if($topPlayers->isNotEmpty())
    <div class="d-none-desktop f-12 mb-1" style="opacity:.4;display:none">👆 {{ __('tournaments.pub_swipe_left_hint') }}</div>
    <style>
        @media (max-width:768px) { .d-none-desktop { display:block !important; } }
    </style>
    <div class="table-scrollable">
        <div class="table-drag-indicator"></div>
        <table class="table">
            <thead>
                <tr style="border-bottom:2px solid rgba(128,128,128,.2)">
                    <th class="p-1" style="text-align:left">#</th>
                    <th class="p-1" style="text-align:left">{{ __('tournaments.stats_col_player') }}</th>
                    <th class="p-1" style="text-align:left">{{ __('tournaments.standings_col_team') }}</th>
                    <th class="p-1" style="text-align:center">Матчи</th>
                    <th class="p-1" style="text-align:center">Победы</th>
                    <th class="p-1" style="text-align:center">WinRate</th>
                    <th class="p-1" style="text-align:center">{{ __('tournaments.pub_sets_col') }}</th>
                    <th class="p-1" style="text-align:center">{{ __('tournaments.tv_diff_col') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($topPlayers as $i => $ps)
                <tr style="border-bottom:1px solid rgba(128,128,128,.1)">
                    <td class="p-1 b-700">{{ $i + 1 }}</td>
                    <td class="p-1">
                        <a href="{{ route('users.show', $ps->user_id) }}" class="blink">
                            {{ $ps->user->displayName() }}
                        </a>
                    </td>
                    <td class="p-1" style="opacity:.7">{{ $ps->team->name ?? '—' }}</td>
                    <td class="p-1" style="text-align:center">{{ $ps->matches_played }}</td>
                    <td class="p-1" style="text-align:center;color:#10b981">{{ $ps->matches_won }}</td>
                    <td class="p-1 b-700" style="text-align:center;color:#E7612F">{{ $ps->match_win_rate }}%</td>
                    <td class="p-1" style="text-align:center">{{ $ps->sets_won }}:{{ $ps->sets_lost }}</td>
                    <td class="p-1" style="text-align:center">{{ $ps->point_diff > 0 ? '+' : '' }}{{ $ps->point_diff }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div class="f-13" style="opacity:.5">Нет данных. Статистика появится после первых сыгранных матчей.</div>
    @endif
</div>
