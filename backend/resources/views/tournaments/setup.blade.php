<x-voll-layout body_class="tournament-setup-page">
@php
    $direction = $event->direction ?? 'classic';
    $isBeach = $direction === 'beach';
@endphp
<x-slot name="title">Управление турниром — {{ $event->title }}</x-slot>

    <x-slot name="style">
        <style>
        .tournament-setup-page .card label {
            font-size: 13px;
            font-weight: 600;
            margin-bottom: .25rem;
            display: block;
        }
        .tournament-setup-page input:not([type="radio"]):not([type="checkbox"]):not([type="hidden"]):not([type="file"]),
        .tournament-setup-page select {
            display: block;
            width: 100%;
            padding: .55rem .75rem;
            font-size: 15px;
            line-height: 1.5;
            border: 1px solid rgba(128,128,128,.2);
            border-radius: 8px;
            background-color: var(--bg-card, #fff);
            color: var(--text, #1a1a1a);
            transition: border-color .15s, box-shadow .15s;
            -webkit-appearance: none;
        }
        .tournament-setup-page input:not([type="radio"]):not([type="checkbox"]):not([type="hidden"]):not([type="file"]):hover,
        .tournament-setup-page select:hover {
            border-color: rgba(41,103,186,.4);
        }
        .tournament-setup-page input:not([type="radio"]):not([type="checkbox"]):not([type="hidden"]):not([type="file"]):focus,
        .tournament-setup-page select:focus {
            outline: none;
            border-color: #2967BA;
            box-shadow: 0 0 0 3px rgba(41,103,186,.15);
        }
        body.dark .tournament-setup-page input:not([type="radio"]):not([type="checkbox"]):not([type="hidden"]):not([type="file"]),
        body.dark .tournament-setup-page select {
            background-color: var(--bg-card, #1e1e1e);
            color: var(--text, #e0e0e0);
            border-color: rgba(255,255,255,.12);
        }
        body.dark .tournament-setup-page input:not([type="radio"]):not([type="checkbox"]):not([type="hidden"]):not([type="file"]):hover,
        body.dark .tournament-setup-page select:hover {
            border-color: rgba(41,103,186,.5);
        }
        .tournamentPhotosSwiper .swiper-slide {
            max-width: 200px;
        }
        .tournamentPhotosSwiper .hover-image {
            width: 100%;
            height: 150px;
            overflow: hidden;
            border-radius: 8px;
        }
        .tournamentPhotosSwiper .hover-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        </style>
    </x-slot>

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
            <p style="opacity:.6">Нет подтверждённых команд.</p>
        @else
            <div class="row row2">
                @foreach($teams as $team)
                    <div class="col-6 col-md-3 mb-1">
                        <div class="card h-100" style="padding:.75rem">
                            <a href="{{ route('tournamentTeams.show', [$event, $team]) }}" class="blink b-600 f-14 d-block mb-05">
                                {{ $team->name }}
                            </a>
                            @if($team->captain)
                                <div class="f-12" style="opacity:.5">{{ $team->captain->displayName() }}</div>
                            @endif
                            <div class="f-12 mt-05" style="opacity:.4">{{ $team->members_count ?? $team->members->count() }} чел.</div>
                            <form method="POST" action="{{ route('tournamentTeams.destroy', [$event, $team]) }}" class="mt-1">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-alert f-12" data-title="Удалить команду {{ $team->name }}?" data-icon="warning" data-confirm-text="Да, удалить" data-cancel-text="Отмена" style="background:none;border:1px solid rgba(220,38,38,.3);border-radius:6px;cursor:pointer;padding:3px 8px;color:#dc2626">
                                    🗑 Удалить
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Создать команду организатором --}}
        <div class="mt-2">
            <details>
                <summary class="btn btn-small btn-secondary" style="cursor:pointer">➕ Создать команду</summary>
                <div class="card mt-1" style="padding:1rem">
                    <form method="POST" action="{{ route('tournamentTeams.store', $event) }}">
                        @csrf
                        <div class="row row2">
                            <div class="col-md-4 mb-1">
                                <div class="card">
                                    <label>Название команды</label>
                                    <input type="text" name="name" placeholder="Название (авто по фамилии капитана)">
                                </div>
                            </div>
                            <div class="col-md-4 mb-1">
                                <div class="card">
                                    <label>Капитан (поиск)</label>
                                    <div style="position:relative">
                                        <input type="text" id="org-captain-search" placeholder="Имя или ID..." autocomplete="off">
                                        <input type="hidden" name="captain_user_id" id="org-captain-id">
                                        <div id="org-captain-dd" class="form-select-dropdown" style="position:absolute;z-index:10;width:100%;display:none"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 d-flex mb-1" style="align-items:flex-end">
                                <button type="submit" class="btn btn-primary">Создать</button>
                            </div>
                        </div>
                    </form>
                </div>
            </details>
        </div>
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
                    <div class="card">
                        <label>Тип</label>
                        <select name="type" id="stage_type_select">
                            <option value="round_robin">Круговая система (Round Robin)</option>
                            <option value="groups_playoff">Группы + плей-офф</option>
                            <option value="single_elim">Олимпийка</option>
                            <option value="swiss">Швейцарская</option>
                            <option value="double_elim">Двойное выбывание (Double Elimination)</option>
                            <option value="king_of_court">Король площадки (King of the Court)</option>
                            <option value="thai">Тайский формат</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4 mb-2">
                    <div class="card">
                        <label>Название</label>
                        <input name="name" value="{{ old('name', 'Групповой этап') }}" required>
                    </div>
                </div>
                <div class="col-md-4 mb-2">
                    <div class="card">
                        <label>Формат матча</label>
                        <select name="match_format" id="match_format_select">
                            <option value="bo3">Best of 3 (Bo3)</option>
                            <option value="bo1">Best of 1 (Bo1)</option>
                            @if(!$isBeach)
                            <option value="bo5">Best of 5 (Bo5)</option>
                            @endif
                        </select>
                        <div id="match_format_hint" class="f-13 mt-05" style="opacity:.6"></div>
                        <script>
                        (function(){
                            var hints = {
                                bo1: 'Играют 1 сет. Кто выиграл сет — выиграл матч. Быстрый формат для пулов.',
                                bo3: 'Играют до 2 побед в сетах. Максимум 3 сета (2:0 или 2:1). Стандарт для пляжки и групповых этапов.',
                                bo5: 'Играют до 3 побед в сетах. Максимум 5 сетов. Обычно только для финалов классики 6×6.'
                            };
                            var sel = document.getElementById('match_format_select');
                            var hint = document.getElementById('match_format_hint');
                            function upd() { hint.textContent = hints[sel.value] || ''; }
                            sel.addEventListener('change', upd);
                            upd();
                        })();
                        </script>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4 mb-2">
                    <div class="card"><label>Очки в сете</label>
                    <select name="set_points">
                        @if(!$isBeach)
                        <option value="25" selected>25 (классика)</option>
                        @endif
                        <option value="21" @if($isBeach) selected @endif>21 (пляж)</option>
                        <option value="15">15 (мини)</option>
                    </select>
                    </div>
                </div>
                <div class="col-md-4 mb-2">
                    <div class="card"><label>Решающий сет</label>
                    <select name="deciding_set_points">
                        <option value="15" selected>15</option>
                        @if(!$isBeach)
                        <option value="25">25</option>
                        @endif
                    </select>
                    </div>
                </div>
            </div>

            <div class="row" id="group_fields">
                <div class="col-md-3 mb-2">
                    <div class="card"><label>Кол-во групп</label>
                    <input name="groups_count" type="number" value="2" min="1" max="16">
                    </div>
                </div>
                <div class="col-md-3 mb-2">
                    <div class="card"><label>Выходят из группы</label>
                    <input name="advance_count" type="number" value="2" min="1" max="8">
                    </div>
                </div>
                <div class="col-md-3 mb-2">
                    <div class="card"><label>Матч за 3-е место</label>
                    <select name="third_place_match">
                        <option value="0">Нет</option>
                        <option value="1">Да</option>
                    </select>
                    </div>
                </div>
                <div class="col-md-3 mb-2">
                    <div class="card"><label>Площадки</label>
                    <input name="courts" placeholder="Корт 1, Корт 2">
                    </div>
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

        @php
            $tournamentPhotos = $event->getMedia('tournament_photos');
            $currentPhotoIds = $tournamentPhotos->pluck('id')->toArray();
        @endphp

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

        @if(($userEventPhotos ?? collect())->count() > 0)
        <div class="card">
            <label>Выберите фото из вашей галереи</label>

            <div class="event-photos-selector" id="tournament-photos-selector"
                 data-selected='{{ json_encode($currentPhotoIds) }}'>
                <div class="swiper tournamentPhotosSwiper">
                    <div class="swiper-wrapper">
                        @foreach($userEventPhotos as $photo)
                        <div class="swiper-slide">
                            <div class="hover-image mb-1">
                                <img src="{{ $photo->getUrl('event_thumb') }}" alt="photo" loading="lazy"/>
                            </div>
                            <div class="mt-1 d-flex between fvc">
                                <label class="checkbox-item mb-0">
                                    <input type="checkbox" class="t-photo-select" value="{{ $photo->id }}">
                                    <div class="custom-checkbox"></div>
                                    <span>Выбрать</span>
                                </label>
                                <div class="photo-order-badge f-16 b-600 cd"></div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    <div class="swiper-pagination"></div>
                </div>

                <ul class="list f-16 mt-1">
                    <li>Выберите фото для турнира. Первое отмеченное фото будет главным.</li>
                    <li>Фотографии можно добавить в разделе <a target="_blank" href="{{ route('user.photos') }}">Ваши фотографии</a> (с галочкой «Для мероприятий»)</li>
                </ul>
            </div>

            <form method="POST" action="{{ route('tournament.photos.store', $event) }}" id="tournament-photos-form" class="mt-1">
                @csrf
                <input type="hidden" name="photo_ids" id="tournament_photos_input" value="">
                <button type="submit" class="btn btn-primary" id="tournament-photos-submit" style="display:none">Сохранить фото</button>
            </form>
        </div>
        @else
            <div class="alert alert-info f-14">
                Нет фото в галерее. <a href="{{ route('user.photos') }}" target="_blank">Загрузите фото</a> с пометкой «Для мероприятий».
            </div>
        @endif
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
                        @php
$stageTypeLabels = [
    'round_robin' => 'Круговая система',
    'groups_playoff' => 'Группы + плей-офф',
    'single_elim' => 'Олимпийка',
    'swiss' => 'Швейцарская',
    'double_elim' => 'Двойное выбывание',
    'king_of_court' => 'Король площадки',
    'thai' => 'Тайский формат',
];
$matchFormatLabels = ['bo1' => 'Best of 1', 'bo3' => 'Best of 3', 'bo5' => 'Best of 5'];
@endphp
{{ $stageTypeLabels[$stage->type] ?? $stage->type }} · {{ $matchFormatLabels[$stage->matchFormat()] ?? strtoupper($stage->matchFormat()) }} · до {{ $stage->setPoints() }} очков
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


<script>
(function(){
    var inp = document.getElementById('org-captain-search');
    var hidden = document.getElementById('org-captain-id');
    var dd = document.getElementById('org-captain-dd');
    if (!inp || !dd || !hidden) return;
    var timer = null;

    inp.addEventListener('input', function() {
        clearTimeout(timer);
        var q = inp.value.trim();
        if (q.length < 2) { dd.innerHTML = ''; dd.style.display = 'none'; return; }
        timer = setTimeout(function() {
            fetch('/api/users/search?q=' + encodeURIComponent(q))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    dd.innerHTML = '';
                    var items = data.items || data || [];
                    if (!items.length) { dd.innerHTML = '<div style="padding:8px 12px;font-size:13px;opacity:.5">Не найдено</div>'; dd.style.display = 'block'; return; }
                    items.slice(0,6).forEach(function(u) {
                        var div = document.createElement('div');
                        div.className = 'form-select-option';
                        div.style.cssText = 'padding:8px 12px;cursor:pointer';
                        div.textContent = u.label || u.name || '#' + u.id;
                        div.addEventListener('click', function() {
                            inp.value = div.textContent;
                            hidden.value = u.id;
                            dd.style.display = 'none';
                        });
                        dd.appendChild(div);
                    });
                    dd.style.display = 'block';
                });
        }, 300);
    });

    document.addEventListener('click', function(e) {
        if (!dd.contains(e.target) && e.target !== inp) dd.style.display = 'none';
    });
})();
</script>


<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tournament Photos Swiper
    if (document.querySelector('.tournamentPhotosSwiper')) {
        new Swiper('.tournamentPhotosSwiper', {
            slidesPerView: 3,
            spaceBetween: 10,
            pagination: { el: '.tournamentPhotosSwiper .swiper-pagination', clickable: true },
            breakpoints: {
                320: { slidesPerView: 2 },
                640: { slidesPerView: 3 },
                1024: { slidesPerView: 4 }
            }
        });

        var container = document.getElementById('tournament-photos-selector');
        if (container) {
            var savedPhotos = JSON.parse(container.dataset.selected || '[]');
            var selectedPhotos = savedPhotos.slice();

            function updateTournamentUI() {
                document.querySelectorAll('.t-photo-select').forEach(function(cb) {
                    var id = parseInt(cb.value);
                    var isSelected = selectedPhotos.indexOf(id) !== -1;
                    cb.checked = isSelected;
                    var badge = cb.closest('.swiper-slide').querySelector('.photo-order-badge');
                    if (isSelected) {
                        var order = selectedPhotos.indexOf(id) + 1;
                        badge.textContent = order === 1 ? '★ Главное' : 'Фото: ' + order;
                    } else {
                        badge.textContent = '';
                    }
                });
                document.getElementById('tournament_photos_input').value = JSON.stringify(selectedPhotos);
                var btn = document.getElementById('tournament-photos-submit');
                btn.style.display = selectedPhotos.length > 0 ? '' : 'none';
            }

            document.querySelectorAll('.t-photo-select').forEach(function(cb) {
                cb.addEventListener('change', function() {
                    var id = parseInt(this.value);
                    if (this.checked) {
                        selectedPhotos.push(id);
                    } else {
                        var idx = selectedPhotos.indexOf(id);
                        if (idx !== -1) selectedPhotos.splice(idx, 1);
                    }
                    updateTournamentUI();
                });
            });

            updateTournamentUI();
        }
    }
});
</script>

<script src="/assets/fas.js"></script>
</x-voll-layout>
