{{-- resources/views/games/manage.blade.php — страница организатора «Игра со статистикой» --}}
<x-voll-layout body_class="friendly-game-page">

    <x-slot name="title">{{ __('games.manage_title') }} — {{ $event->title }}</x-slot>
    <x-slot name="h1">📊 {{ __('games.manage_title') }}</x-slot>
    <x-slot name="h2">{{ $event->title }}</x-slot>
    <x-slot name="t_description">{{ __('games.manage_h2') }}</x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('events.show', $event) }}?occurrence={{ $occurrence->id }}" itemprop="item"><span itemprop="name">{{ __('games.breadcrumb_event') }}</span></a>
            <meta itemprop="position" content="2">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">{{ __('games.manage_title') }}</span>
            <meta itemprop="position" content="3">
        </li>
    </x-slot>

    <x-slot name="style">
        @include('games._styles')
        <style>
            .gm-row { display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:.7rem 0; border-bottom:1px solid rgba(0,0,0,.06); }
            body.dark .gm-row { border-bottom-color: rgba(255,255,255,.08); }
            .gm-row .gm-who { display:flex; align-items:center; gap:.9rem; min-width:0; }
            .gm-row img { width:3.4rem; height:3.4rem; border-radius:50%; object-fit:cover; flex-shrink:0; }
            .gm-seg { display:inline-flex; flex-shrink:0; }
            .gm-seg label { cursor:pointer; margin:0; }
            .gm-seg input { display:none; }
            .gm-seg span { display:block; padding:.5rem 1.3rem; border:1px solid rgba(0,0,0,.18); font-weight:600; font-size:1.4rem; }
            body.dark .gm-seg span { border-color: rgba(255,255,255,.25); }
            .gm-seg label:first-child span { border-radius:.8rem 0 0 .8rem; }
            .gm-seg label:last-child span { border-radius:0 .8rem .8rem 0; }
            .gm-seg input:checked + span { background:#2967BA; color:#fff; border-color:#2967BA; }
            .gm-seg label.gm-away input:checked + span { background:#E7612F; border-color:#E7612F; }
            .gm-seg label.gm-none input:checked + span { background:rgba(120,120,128,.25); color:inherit; border-color:rgba(120,120,128,.35); }
            .gm-actions { display:flex; flex-wrap:wrap; gap:.8rem; }
            .gm-lvl { font-size:1.2rem; opacity:.6; }
            .gm-color-row { display:flex; gap:1.5rem; flex-wrap:wrap; }
            .gm-color-row select { padding:.6rem 1rem; border-radius:.8rem; border:1px solid rgba(0,0,0,.18); background:transparent; color:inherit; }
        </style>
    </x-slot>

    <div class="container">

        @if(session('success'))<div class="alert alert-success mb-2">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="alert alert-danger mb-2">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger mb-2">{{ $errors->first() }}</div>@endif

        {{-- Тур + ссылки --}}
        <div class="ramka">
            <div class="d-flex between fvc" style="flex-wrap:wrap;gap:1rem">
                <div>
                    <div class="b-600 f-16">{{ $occurrence->starts_at?->copy()->timezone($occurrence->timezone ?? 'UTC')->format('d.m.Y H:i') }}</div>
                    @if($event->stats_rated)<div class="f-13 mt-05" style="opacity:.7">{{ __('games.rated_note') }}</div>@endif
                </div>
                <div class="gm-actions">
                    @if($occurrences->count() > 1)
                    <form method="GET" action="{{ route('game.manage', $event) }}">
                        <select name="occurrence" onchange="this.form.submit()" style="padding:.6rem 1rem;border-radius:.8rem;border:1px solid rgba(0,0,0,.18);background:transparent;color:inherit">
                            @foreach($occurrences as $o)
                            <option value="{{ $o->id }}" @selected($o->id === $occurrence->id)>{{ $o->starts_at?->copy()->timezone($o->timezone ?? 'UTC')->format('d.m.Y H:i') }}</option>
                            @endforeach
                        </select>
                    </form>
                    @endif
                    <a class="btn btn-secondary" href="{{ route('game.results', $event) }}?occurrence={{ $occurrence->id }}">{{ __('games.btn_results') }}</a>
                    <a class="btn btn-secondary" href="{{ route('events.registrations.index', $event) }}?occurrence={{ $occurrence->id }}">{{ __('games.roster_add_link') }}</a>
                </div>
            </div>
        </div>

        {{-- Новый матч --}}
        <div class="ramka">
            <h2 class="-mt-05">{{ __('games.new_match_title') }}</h2>
            <div class="f-14 mb-2" style="opacity:.65">{{ __('games.new_match_hint') }}</div>

            @if($roster->isEmpty())
                <div class="f-15" style="opacity:.75">{{ __('games.roster_empty') }}</div>
            @else
            <form method="POST" action="{{ route('game.matches.store', $event) }}" id="gm-form">
                @csrf
                <input type="hidden" name="occurrence_id" value="{{ $occurrence->id }}">

                <div class="gm-actions mb-2">
                    <button type="button" class="btn btn-secondary btn-small" id="gm-split-level">⚖️ {{ __('games.split_level') }}</button>
                    <button type="button" class="btn btn-secondary btn-small" id="gm-split-random">🎲 {{ __('games.split_random') }}</button>
                    <button type="button" class="btn btn-secondary btn-small" id="gm-reset">{{ __('games.reset') }}</button>
                </div>

                <div class="gm-color-row mb-2">
                    <label>{{ __('games.team_a') }}:
                        <select name="home_color">@foreach($colors as $k => $label)<option value="{{ $k }}" @selected(old('home_color', 'red') === $k)>{{ $label }}</option>@endforeach</select>
                    </label>
                    <label>{{ __('games.team_b') }}:
                        <select name="away_color">@foreach($colors as $k => $label)<option value="{{ $k }}" @selected(old('away_color', 'blue') === $k)>{{ $label }}</option>@endforeach</select>
                    </label>
                    <span class="f-14" style="align-self:center">
                        {{ __('games.count_home') }}: <b id="gm-cnt-home">0</b> · {{ __('games.count_away') }}: <b id="gm-cnt-away">0</b>
                    </span>
                </div>

                <div id="gm-roster">
                    @foreach($roster as $u)
                    @php
                        $lvl   = $event->direction === 'beach' ? $u->beach_level : $u->classic_level;
                        $chosen = old("side.{$u->id}", in_array($u->id, $prefill['home']) ? 'home' : (in_array($u->id, $prefill['away']) ? 'away' : ''));
                    @endphp
                    <div class="gm-row" data-level="{{ (int) $lvl }}">
                        <div class="gm-who">
                            <img src="{{ $u->profile_photo_url }}" alt="">
                            <div style="min-width:0">
                                <div class="b-600">{{ trim(($u->last_name ?? '') . ' ' . ($u->first_name ?? '')) ?: $u->name }}</div>
                                @if($lvl)<div class="gm-lvl">{{ __('games.level_short') }} {{ $lvl }}</div>@endif
                            </div>
                        </div>
                        <span class="gm-seg">
                            <label class="gm-none"><input type="radio" name="side[{{ $u->id }}]" value="" @checked($chosen === '')><span>—</span></label>
                            <label class="gm-home"><input type="radio" name="side[{{ $u->id }}]" value="home" @checked($chosen === 'home')><span>1</span></label>
                            <label class="gm-away"><input type="radio" name="side[{{ $u->id }}]" value="away" @checked($chosen === 'away')><span>2</span></label>
                        </span>
                    </div>
                    @endforeach
                </div>

                <button type="submit" class="btn mt-2">▶ {{ __('games.create_match') }}</button>
            </form>
            @endif
        </div>

        {{-- Матчи --}}
        <div class="ramka">
            <h2 class="-mt-05">{{ __('games.matches_title') }}</h2>
            @forelse($matches as $m)
            @php
                $statusLabel = match($m->status) { 'completed' => __('games.status_completed'), 'live' => __('games.status_live'), default => __('games.status_scheduled') };
                $setsText = $m->status === 'completed'
                    ? collect($m->score_home ?? [])->map(fn($h, $i) => $h . ':' . ($m->score_away[$i] ?? 0))->implode('  ')
                    : '';
            @endphp
            <div class="card mb-2">
                <div class="d-flex between fvc mb-1" style="flex-wrap:wrap;gap:.8rem">
                    <div class="b-700 f-16">{{ __('games.match_n', ['n' => $m->match_number]) }}</div>
                    <span class="badge badge-sm {{ $m->status === 'completed' ? 'status-finished' : ($m->status === 'live' ? 'status-live' : 'status-open') }}">{{ $statusLabel }}</span>
                </div>
                <div class="row">
                    @foreach([[$m->teamHome, $m->sets_home], [$m->teamAway, $m->sets_away]] as [$team, $sets])
                    <div class="col-md-5 mb-1">
                        @if($team)
                        <span class="gm-team-tag gm-c-{{ $team->meta['color'] ?? 'white' }}">{{ $team->name }}</span>
                        <div class="f-14 mt-05" style="opacity:.8">
                            {{ $team->members->map(fn($x) => trim(($x->user->last_name ?? '') . ' ' . mb_substr($x->user->first_name ?? '', 0, 1)) . ($x->user?->first_name ? '.' : ''))->implode(', ') }}
                        </div>
                        @endif
                    </div>
                    @if($loop->first)
                    <div class="col-md-2 mb-1" style="text-align:center">
                        @if($m->status === 'completed')
                        <div class="gm-match-score">{{ $m->sets_home }} : {{ $m->sets_away }}</div>
                        <div class="f-12" style="opacity:.6">{{ $setsText }}</div>
                        @else
                        <div class="gm-match-score" style="opacity:.3">—</div>
                        @endif
                    </div>
                    @endif
                    @endforeach
                </div>
                <div class="gm-actions mt-1">
                    @if($m->status !== 'completed')
                    <a class="btn btn-small" href="{{ route('tournament.matches.rally.form', $m) }}">▶ {{ __('games.btn_rally') }}</a>
                    <a class="btn btn-small btn-secondary" href="{{ route('tournament.matches.score.form', $m) }}">{{ __('games.btn_score') }}</a>
                    @else
                    <a class="btn btn-small btn-secondary" href="{{ route('tournament.matches.player_stats.form', $m) }}">📊 {{ __('games.btn_stats') }}</a>
                    @endif
                    <a class="btn btn-small btn-secondary" href="{{ route('game.manage', $event) }}?occurrence={{ $occurrence->id }}&from={{ $m->id }}#gm-form">🔁 {{ __('games.btn_rematch') }}</a>
                    <form method="POST" action="{{ route('game.matches.destroy', $m) }}" style="display:inline">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-small btn-danger btn-alert"
                            data-title="{{ __('games.delete_title') }}" data-text="{{ __('games.delete_text') }}" data-icon="warning"
                            data-confirm-text="{{ __('games.delete_yes') }}" data-cancel-text="{{ __('games.cancel') }}">🗑 {{ __('games.btn_delete') }}</button>
                    </form>
                </div>
            </div>
            @empty
            <div class="f-14" style="opacity:.6">{{ __('games.matches_empty') }}</div>
            @endforelse
        </div>

        {{-- Итоги вечера --}}
        <div class="ramka">
            <h2 class="-mt-05">{{ __('games.board_title') }}</h2>
            @include('games._board', ['board' => $board])
        </div>

        {{-- Формат матча --}}
        <div class="ramka">
            <h2 class="-mt-05">{{ __('games.format_title') }}</h2>
            <div class="f-14 mb-2" style="opacity:.65">{{ __('games.format_hint') }}</div>
            <form method="POST" action="{{ route('game.config.update', $event) }}" class="gm-color-row">
                @csrf
                <input type="hidden" name="occurrence_id" value="{{ $occurrence->id }}">
                <label>
                    <select name="match_format">
                        @foreach(['bo1' => __('games.format_bo1'), 'bo3' => __('games.format_bo3'), 'bo5' => __('games.format_bo5')] as $k => $label)
                        <option value="{{ $k }}" @selected(($config['match_format'] ?? 'bo1') === $k)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>{{ __('games.set_points') }}: <input type="number" name="set_points" min="5" max="50" value="{{ $config['set_points'] ?? 25 }}" style="width:7rem;padding:.6rem;border-radius:.8rem;border:1px solid rgba(0,0,0,.18);background:transparent;color:inherit"></label>
                <label>{{ __('games.deciding_points') }}: <input type="number" name="deciding_set_points" min="5" max="50" value="{{ $config['deciding_set_points'] ?? 15 }}" style="width:7rem;padding:.6rem;border-radius:.8rem;border:1px solid rgba(0,0,0,.18);background:transparent;color:inherit"></label>
                <button class="btn btn-secondary btn-small" type="submit">{{ __('games.save') }}</button>
            </form>
        </div>

    </div>

    <x-slot name="script">
    <script>
    (function () {
        var roster = document.getElementById('gm-roster');
        if (!roster) return;
        var rows = Array.prototype.slice.call(roster.querySelectorAll('.gm-row'));

        function setSide(row, side) {
            var r = row.querySelector('input[type=radio][value="' + side + '"]');
            if (r) r.checked = true;
        }
        function sideOf(row) {
            var c = row.querySelector('input[type=radio]:checked');
            return c ? c.value : '';
        }
        function recount() {
            var h = 0, a = 0;
            rows.forEach(function (r) { var s = sideOf(r); if (s === 'home') h++; else if (s === 'away') a++; });
            document.getElementById('gm-cnt-home').textContent = h;
            document.getElementById('gm-cnt-away').textContent = a;
        }
        function shuffle(arr) { for (var i = arr.length - 1; i > 0; i--) { var j = Math.floor(Math.random() * (i + 1)); var t = arr[i]; arr[i] = arr[j]; arr[j] = t; } return arr; }
        // Делим тех, кто уже отмечен; если никого не отметили — всех записанных
        function pool() {
            var chosen = rows.filter(function (r) { return sideOf(r) !== ''; });
            return chosen.length ? chosen : rows.slice();
        }
        document.getElementById('gm-split-random').addEventListener('click', function () {
            var p = shuffle(pool());
            p.forEach(function (r, i) { setSide(r, i % 2 === 0 ? 'home' : 'away'); });
            recount();
        });
        document.getElementById('gm-split-level').addEventListener('click', function () {
            // по убыванию уровня (равные — случайно), «змейкой» 1-2-2-1-1-2…, чтобы составы были равны по силе
            var p = shuffle(pool()).sort(function (a, b) { return (parseInt(b.dataset.level) || 0) - (parseInt(a.dataset.level) || 0); });
            p.forEach(function (r, i) { var k = i % 4; setSide(r, (k === 0 || k === 3) ? 'home' : 'away'); });
            recount();
        });
        document.getElementById('gm-reset').addEventListener('click', function () {
            rows.forEach(function (r) { setSide(r, ''); });
            recount();
        });
        roster.addEventListener('change', recount);
        recount();
    })();
    </script>
    </x-slot>

</x-voll-layout>
