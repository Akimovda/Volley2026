<x-voll-layout body_class="tournament-setup-page">
<x-slot name="title">Управление турниром — {{ $event->title }}</x-slot>
<x-slot name="h1">Турнир: {{ $event->title }}</x-slot>

<x-slot name="breadcrumbs">
    <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
        <a href="{{ route('events.show', $event) }}" itemprop="item"><span itemprop="name">{{ $event->title }}</span></a>
        <meta itemprop="position" content="2">
    </li>
    <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
        <span itemprop="name">Управление турниром</span>
        <meta itemprop="position" content="3">
    </li>
</x-slot>

<div class="container">

{{-- ========================= ЗАЯВКИ ========================= --}}
@if(($applicationMode ?? 'manual') === 'manual' && isset($pendingApplications) && $pendingApplications->count())
<div class="ramka">
    <h2 class="-mt-05">📋 Заявки на участие ({{ $pendingApplications->count() }})</h2>
    
    <div class="alert alert-info mb-2">
        Режим: <b>ручное одобрение</b>. Одобрите или отклоните заявки команд.
    </div>

    @foreach($pendingApplications as $app)
    <div class="card mb-1">
        <div class="d-flex fvc" style="justify-content:space-between;flex-wrap:wrap;gap:.5rem">
            <div>
                <div class="b-700 f-17">{{ $app->team->name ?? '?' }}</div>
                <div class="f-13" style="opacity:.6">
                    Капитан: 
                    <a class="blink" href="{{ route('users.show', $app->team->captain_user_id) }}">
                        {{ trim(($app->team->captain->last_name ?? '') . ' ' . ($app->team->captain->first_name ?? '')) ?: $app->team->captain->name ?? '?' }}
                    </a>
                    &middot; Подана: {{ $app->applied_at?->format('d.m.Y H:i') }}
                </div>
                @if($app->team->members->count())
                <div class="f-13 mt-05">
                    Состав: 
                    @foreach($app->team->members as $m)
                        <a class="blink" href="{{ route('users.show', $m->user_id) }}">{{ trim(($m->user->last_name ?? '') . ' ' . ($m->user->first_name ?? '')) ?: $m->user->name ?? '?' }}</a>@if(!$loop->last), @endif
                    @endforeach
                </div>
                @endif
            </div>
            <div class="d-flex" style="gap:.5rem">
                <form method="POST" action="{{ route('tournament.application.approve', [$event, $app]) }}">
                    @csrf
                    <button type="submit" class="btn btn-small btn-primary btn-alert" data-title="Одобрить заявку?" data-icon="question" data-confirm-text="Да, одобрить" data-cancel-text="Отмена">✅ Одобрить</button>
                </form>
                <form method="POST" action="{{ route('tournament.application.reject', [$event, $app]) }}">
                    @csrf
                    <button type="submit" class="btn btn-small btn-secondary btn-alert" data-title="Отклонить заявку?" data-icon="warning" data-confirm-text="Да, отклонить" data-cancel-text="Отмена">❌ Отклонить</button>
                </form>
            </div>
        </div>
    </div>
    @endforeach
</div>
@elseif(($applicationMode ?? 'manual') === 'auto')
<div class="ramka">
    <div class="alert alert-success mb-0">
        ✅ Режим: <b>автоматическое одобрение</b>. Заявки одобряются автоматически при подаче.
    </div>
</div>
@endif

<div class="ramka">

    @if(session('success'))
        <div class="p-3 mb-3" style="background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.3);border-radius:10px;color:#10b981">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="p-3 mb-3" style="background:rgba(220,38,38,.1);border:1px solid rgba(220,38,38,.3);border-radius:10px;color:#dc2626">
            {{ session('error') }}
        </div>
    @endif
    @if($errors->any())
        <div class="p-3 mb-3" style="background:rgba(220,38,38,.1);border:1px solid rgba(220,38,38,.3);border-radius:10px;color:#dc2626">
            @foreach($errors->all() as $err)
                {{ $err }}<br>
            @endforeach
        </div>
    @endif

    {{-- ============================================================
         Команды
    ============================================================ --}}
    <div class="card p-3 mb-3">
        <h3 class="mb-2">Команды ({{ $teams->count() }})</h3>
        @if($teams->isEmpty())
            <p style="opacity:.6">Нет подтверждённых команд. Команды создаются на странице события.</p>
        @else
            <div class="d-flex" style="flex-wrap:wrap;gap:8px">
                @foreach($teams as $team)
                    <span class="p-2" style="background:rgba(41,103,186,.15);border-radius:8px;font-size:13px;font-weight:600">
                        {{ $team->name }}
                        @if($team->captain)
                            <span style="opacity:.6">({{ $team->captain->displayName() }})</span>
                        @endif
                    </span>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ============================================================
         Создание стадии
    ============================================================ --}}
    <div class="card p-3 mb-3">
        <h3 class="mb-2">Добавить стадию</h3>
        <form method="POST" action="{{ route('tournament.stages.store', $event) }}">
            @csrf
            <div class="row">
                <div class="col-md-4 mb-2">
                    <label class="f-13 b-600 mb-1 d-block">Тип</label>
                    <select name="type" id="stage_type_select">
                        <option value="round_robin">Round Robin</option>
                        <option value="groups_playoff">Группы + плей-офф</option>
                        <option value="single_elim">Олимпийка</option>
                        <option value="swiss">Швейцарская</option>
                        <option value="double_elim">Double Elimination</option>
                        <option value="king_of_court">King of the Court</option>
                        <option value="thai">Тайский формат</option>
                    </select>
                </div>
                <div class="col-md-4 mb-2">
                    <label class="f-13 b-600 mb-1 d-block">Название</label>
                    <input name="name" value="{{ old('name', 'Групповой этап') }}" required>
                </div>
                <div class="col-md-4 mb-2">
                    <label class="f-13 b-600 mb-1 d-block">Формат матча</label>
                    <select name="match_format">
                        <option value="bo3">Bo3</option>
                        <option value="bo1">Bo1</option>
                        <option value="bo5">Bo5</option>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4 mb-2">
                    <label class="f-13 b-600 mb-1 d-block">Очки в сете</label>
                    <select name="set_points">
                        <option value="25">25 (классика)</option>
                        <option value="21">21 (пляж)</option>
                        <option value="15">15 (мини)</option>
                    </select>
                </div>
                <div class="col-md-4 mb-2">
                    <label class="f-13 b-600 mb-1 d-block">Решающий сет</label>
                    <select name="deciding_set_points">
                        <option value="15">15</option>
                        <option value="25">25</option>
                    </select>
                </div>
            </div>

            <div class="row" id="group_fields">
                <div class="col-md-3 mb-2">
                    <label class="f-13 b-600 mb-1 d-block">Кол-во групп</label>
                    <input name="groups_count" type="number" value="2" min="1" max="16">
                </div>
                <div class="col-md-3 mb-2">
                    <label class="f-13 b-600 mb-1 d-block">Выходят из группы</label>
                    <input name="advance_count" type="number" value="2" min="1" max="8">
                </div>
                <div class="col-md-3 mb-2">
                    <label class="f-13 b-600 mb-1 d-block">Матч за 3-е место</label>
                    <select name="third_place_match">
                        <option value="0">Нет</option>
                        <option value="1">Да</option>
                    </select>
                </div>
                <div class="col-md-3 mb-2">
                    <label class="f-13 b-600 mb-1 d-block">Площадки</label>
                    <input name="courts" placeholder="Корт 1, Корт 2">
                </div>
            </div>

            <button type="submit" class="btn btn-primary mt-2">Создать стадию</button>
        </form>
    </div>

    {{-- ============================================================
         Стадии
    ============================================================ --}}

    {{-- Фото турнира --}}
    <div class="card p-3 mb-3">
        <h3 class="mb-2">Фото турнира</h3>

        @php $tournamentPhotos = $event->getMedia('tournament_photos'); @endphp

        @if($tournamentPhotos->isNotEmpty())
            <div class="d-flex mb-2" style="flex-wrap:wrap;gap:8px">
                @foreach($tournamentPhotos as $media)
                    <div style="position:relative">
                        <img src="{{ $media->getUrl('thumb') }}" style="width:100px;height:75px;object-fit:cover;border-radius:8px">
                        <form method="POST" action="{{ route('tournament.photos.destroy', [$event, $media->id]) }}" style="position:absolute;top:2px;right:2px" onsubmit="return confirm('Удалить?')">
                            @csrf @method('DELETE')
                            <button style="background:rgba(0,0,0,.6);color:#fff;border:none;border-radius:50%;width:20px;height:20px;font-size:11px;cursor:pointer">✕</button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('tournament.photos.store', $event) }}" enctype="multipart/form-data" class="d-flex fvc" style="gap:10px;flex-wrap:wrap">
            @csrf
            <input type="file" name="photos[]" multiple accept="image/*" required>
            <button type="submit" class="btn btn-primary f-13">Загрузить</button>
        </form>
    </div>

    @foreach($stages as $stage)
        @php
            $borderColor = $stage->isCompleted() ? '#10b981' : ($stage->isInProgress() ? '#2967BA' : '#555');
        @endphp
        <div class="card p-3 mb-3" style="border-left:4px solid {{ $borderColor }}">
            <div class="d-flex between fvc mb-2" style="flex-wrap:wrap;gap:8px">
                <div>
                    <h3 class="mb-1" style="font-size:1.3rem">
                        {{ $stage->name }}
                        @if($stage->isCompleted())
                            <span class="f-12 b-600 p-1 px-2 ml-1" style="background:rgba(16,185,129,.15);border-radius:6px;color:#10b981">Завершена</span>
                        @elseif($stage->isInProgress())
                            <span class="f-12 b-600 p-1 px-2 ml-1" style="background:rgba(41,103,186,.15);border-radius:6px;color:#5b9ef0">В процессе</span>
                        @else
                            <span class="f-12 b-600 p-1 px-2 ml-1" style="opacity:.5">Ожидание</span>
                        @endif
                    </h3>
                    <div class="f-13" style="opacity:.5">
                        {{ $stage->type }} · {{ strtoupper($stage->matchFormat()) }} · до {{ $stage->setPoints() }} очков
                    </div>
                </div>
                <div class="d-flex" style="gap:6px">
                    @if($stage->isInProgress())
                    <form method="POST" action="{{ route('tournament.stages.revert', $stage) }}" onsubmit="return confirm('Откатить стадию? Все счета будут сброшены!')">
                        @csrf
                        <button class="btn btn-secondary f-12" style="color:#ca8a04">Откатить</button>
                    </form>
                    @endif
                    <form method="POST" action="{{ route('tournament.stages.destroy', $stage) }}" onsubmit="return confirm('Удалить стадию и все её матчи?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-secondary f-12" style="color:#dc2626">Удалить</button>
                    </form>
                </div>
            </div>

            {{-- Жеребьёвка --}}
            @if($stage->isPending())
                <div class="p-3 mb-3" style="background:rgba(41,103,186,.08);border-radius:10px">
                    <div class="b-700 mb-2">Жеребьёвка</div>
                    <form method="POST" action="{{ route('tournament.draw', $event) }}" id="drawForm_{{ $stage->id }}">
                        @csrf
                        <input type="hidden" name="stage_id" value="{{ $stage->id }}">
                        <div class="d-flex fvc mb-2" style="gap:10px;flex-wrap:wrap">
                            <div>
                                <label class="f-13 b-600 mb-1 d-block">Режим</label>
                                <select name="mode" class="draw-mode-select" data-stage="{{ $stage->id }}">
                                    <option value="random">Случайная</option>
                                    <option value="seeded">По посеву</option>
                                    <option value="manual">Ручная</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">Провести жеребьёвку</button>
                        </div>

                        {{-- Ручная расстановка --}}
                        @if($stage->groups->isNotEmpty())
                        <div class="manual-draw-block" data-stage="{{ $stage->id }}" style="display:none">
                            <div class="f-13 mb-2" style="opacity:.6">Выберите команды для каждой группы:</div>
                            <div class="row">
                                @foreach($stage->groups as $group)
                                    <div class="col-md-6 mb-2">
                                        <div class="card p-2">
                                            <div class="b-600 f-13 mb-1">{{ $group->name }}</div>
                                            @foreach($teams as $team)
                                                <label class="d-flex fvc f-13 mb-1" style="gap:6px;cursor:pointer">
                                                    <input type="checkbox" name="assignments[{{ $group->id }}][]" value="{{ $team->id }}">
                                                    {{ $team->name }}
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        @endif
                    </form>
                </div>
            @endif

            {{-- Группы --}}
            @if($stage->groups->isNotEmpty())
                <div class="row mb-3">
                    @foreach($stage->groups as $group)
                        <div class="col-md-6 mb-2">
                            <div class="card p-2">
                                <div class="b-700 f-14 mb-2">{{ $group->name }}</div>

                                @if($group->standings->isNotEmpty())
                                    <table style="width:100%;border-collapse:collapse;font-size:13px">
                                        <thead>
                                            <tr style="border-bottom:2px solid rgba(128,128,128,.2)">
                                                <th class="p-1" style="text-align:left">#</th>
                                                <th class="p-1" style="text-align:left">Команда</th>
                                                <th class="p-1" style="text-align:center">И</th>
                                                <th class="p-1" style="text-align:center">В</th>
                                                <th class="p-1" style="text-align:center">П</th>
                                                <th class="p-1" style="text-align:center">Сеты</th>
                                                <th class="p-1" style="text-align:center">Оч.</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($group->standings->sortBy('rank') as $standing)
                                                <tr style="border-bottom:1px solid rgba(128,128,128,.1)">
                                                    <td class="p-1 b-700">{{ $standing->rank }}</td>
                                                    <td class="p-1">{{ $standing->team->name ?? '—' }}</td>
                                                    <td class="p-1" style="text-align:center">{{ $standing->played }}</td>
                                                    <td class="p-1" style="text-align:center;color:#10b981">{{ $standing->wins }}</td>
                                                    <td class="p-1" style="text-align:center;color:#dc2626">{{ $standing->losses }}</td>
                                                    <td class="p-1" style="text-align:center">{{ $standing->sets_won }}:{{ $standing->sets_lost }}</td>
                                                    <td class="p-1" style="text-align:center" class="b-700">{{ $standing->rating_points }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                @elseif($group->teams->isNotEmpty())
                                    <div class="d-flex" style="flex-wrap:wrap;gap:6px">
                                        @foreach($group->teams as $team)
                                            <span class="p-1 px-2 f-12 b-600" style="background:rgba(41,103,186,.15);border-radius:6px">{{ $team->name }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Матчи --}}
            @if($stage->matches->isNotEmpty())
                <div class="card p-2">
                    <div class="b-700 f-14 mb-2">Матчи</div>
                    <div style="overflow-x:auto">
                        <table style="width:100%;border-collapse:collapse;font-size:13px">
                            <thead>
                                <tr style="border-bottom:2px solid rgba(128,128,128,.2)">
                                    <th class="p-1" style="text-align:left">#</th>
                                    <th class="p-1" style="text-align:left">Тур</th>
                                    <th class="p-1" style="text-align:left">Дома</th>
                                    <th class="p-1" style="text-align:left">Гости</th>
                                    <th class="p-1" style="text-align:center">Счёт</th>
                                    <th class="p-1" style="text-align:center">Статус</th>
                                    <th class="p-1"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($stage->matches->sortBy(['round', 'match_number']) as $match)
                                    <tr style="border-bottom:1px solid rgba(128,128,128,.1);{{ $match->isCompleted() ? 'background:rgba(16,185,129,.06)' : '' }}">
                                        <td class="p-1">{{ $match->match_number }}</td>
                                        <td class="p-1">R{{ $match->round }}</td>
                                        <td class="p-1 {{ $match->winner_team_id === $match->team_home_id ? 'b-700' : '' }}">
                                            {{ $match->teamHome->name ?? 'TBD' }}
                                        </td>
                                        <td class="p-1 {{ $match->winner_team_id === $match->team_away_id ? 'b-700' : '' }}">
                                            {{ $match->teamAway->name ?? 'TBD' }}
                                        </td>
                                        <td class="p-1" style="text-align:center">{{ $match->setsScore() ?? '—' }}</td>
                                        <td class="p-1" style="text-align:center">
                                            @if($match->isCompleted())
                                                <span class="f-11 b-600 p-1 px-2" style="background:rgba(16,185,129,.15);border-radius:6px;color:#10b981">✓</span>
                                            @elseif($match->status === 'live')
                                                <span class="f-11 b-600 p-1 px-2" style="background:rgba(220,38,38,.15);border-radius:6px;color:#dc2626">LIVE</span>
                                            @else
                                                <span class="f-11" style="opacity:.5">ожидание</span>
                                            @endif
                                        </td>
                                        <td class="p-1">
                                            @if($match->isScheduled() && $match->hasTeams())
                                                <a href="{{ route('tournament.matches.score.form', $match) }}" class="btn btn-primary f-12" style="padding:4px 10px">
                                                    Счёт
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>


                {{-- Следующий тур (Swiss/King) --}}
                @if($stage->isInProgress() && in_array($stage->type, ['swiss', 'king_of_court']))
                    <div class="p-3 mt-2" style="background:rgba(231,97,47,.08);border-radius:10px">
                        <form method="POST" action="{{ route('tournament.stages.nextRound', $stage) }}" class="d-flex fvc" style="gap:10px">
                            @csrf
                            <div class="b-600">
                                {{ $stage->type === 'swiss' ? 'Сгенерировать следующий тур' : 'Следующий матч King of the Court' }}
                            </div>
                            <button type="submit" class="btn btn-primary">Далее →</button>
                        </form>
                    </div>
                @endif


                {{-- Расписание --}}
                @if($stage->isInProgress() && $stage->matches->whereNotNull('team_home_id')->isNotEmpty())
                    <div class="p-3 mt-2" style="background:rgba(41,103,186,.08);border-radius:10px">
                        <div class="b-600 mb-2">Расписание</div>
                        <form method="POST" action="{{ route('tournament.stages.schedule', $stage) }}" class="d-flex fvc" style="gap:10px;flex-wrap:wrap">
                            @csrf
                            <div>
                                <label class="f-12 b-600 mb-1 d-block">Начало</label>
                                <input type="datetime-local" name="start_time" required style="font-size:13px">
                            </div>
                            <div>
                                <label class="f-12 b-600 mb-1 d-block">Матч (мин)</label>
                                <input type="number" name="match_duration" value="60" min="15" max="180" style="width:70px;font-size:13px">
                            </div>
                            <div>
                                <label class="f-12 b-600 mb-1 d-block">Перерыв (мин)</label>
                                <input type="number" name="break_duration" value="10" min="0" max="60" style="width:70px;font-size:13px">
                            </div>
                            <button type="submit" class="btn btn-primary f-13">Сгенерировать</button>
                        </form>
                    </div>
                @endif

                {{-- Продвижение --}}
                @if($stage->isCompleted() && in_array($stage->type, ['round_robin', 'groups_playoff']))
                    @php $nextStages = $stages->where('type', 'single_elim')->where('status', 'pending'); @endphp
                    @if($nextStages->isNotEmpty())
                        <div class="p-3 mt-2" style="background:rgba(41,103,186,.08);border-radius:10px">
                            <div class="b-700 mb-2">Продвижение в плей-офф</div>
                            <form method="POST" action="{{ route('tournament.stages.advance', $stage) }}" class="d-flex fvc" style="gap:10px;flex-wrap:wrap">
                                @csrf
                                <div>
                                    <label class="f-13 b-600 mb-1 d-block">Стадия</label>
                                    <select name="playoff_stage_id">
                                        @foreach($nextStages as $ns)
                                            <option value="{{ $ns->id }}">{{ $ns->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="f-13 b-600 mb-1 d-block">Выходят</label>
                                    <input name="advance_per_group" type="number" value="{{ $stage->cfg('advance_count', 2) }}" min="1" max="8" style="width:60px">
                                </div>
                                <button type="submit" class="btn btn-primary">Продвинуть</button>
                            </form>
                        </div>
                    @endif
                @endif
            @endif
        </div>
    @endforeach

    @if($stages->isEmpty())
        <div style="text-align:center;opacity:.5;padding:40px 0">
            <p class="f-18 b-600">Турнир пока не настроен</p>
            <p>Создайте первую стадию выше, затем проведите жеребьёвку.</p>
        </div>
    @endif

</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var typeSelect = document.getElementById('stage_type_select');
    var groupFields = document.getElementById('group_fields');
    if (typeSelect && groupFields) {
        function toggle() {
            var t = typeSelect.value;
            groupFields.style.display = (t === 'round_robin' || t === 'groups_playoff' || t === 'thai') ? '' : 'none';
        }
        typeSelect.addEventListener('change', toggle);
        toggle();
    }
});
</script>


<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.draw-mode-select').forEach(function(sel) {
        sel.addEventListener('change', function() {
            var stageId = this.dataset.stage;
            var block = document.querySelector('.manual-draw-block[data-stage="' + stageId + '"]');
            if (block) {
                block.style.display = this.value === 'manual' ? '' : 'none';
            }
        });
    });
});
</script>

</x-voll-layout>
