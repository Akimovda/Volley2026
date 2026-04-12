{{-- resources/views/volleyball_school/show.blade.php --}}
<x-voll-layout body_class="volleyball-school-show-page">

    <x-slot name="title">{{ $school->name }}</x-slot>
    <x-slot name="description">{{ Str::limit(strip_tags($school->description ?? $school->name), 160) }}</x-slot>
    <x-slot name="canonical">{{ route('volleyball_school.show', $school->slug) }}</x-slot>
    <x-slot name="h1">{{ $school->name }}</x-slot>

    @php
        $dirLabel = match($school->direction) {
            'classic' => '🏐 Классический волейбол',
            'beach'   => '🏖 Пляжный волейбол',
            'both'    => '🏐🏖 Классика и пляж',
            default   => ''
        };
        $organizer  = $school->organizer;
        $coverMedia = $organizer?->getMedia('school_cover')->sortByDesc('created_at')->first();
        $logoMedia  = $organizer?->getMedia('school_logo')->sortByDesc('created_at')->first();
        $cover = $coverMedia
            ? ($coverMedia->hasGeneratedConversion('school_cover_thumb') ? $coverMedia->getUrl('school_cover_thumb') : $coverMedia->getUrl())
            : ($school->getFirstMediaUrl('cover', 'thumb') ?: $school->getFirstMediaUrl('cover'));
        $logo = $logoMedia
            ? ($logoMedia->hasGeneratedConversion('school_logo_thumb') ? $logoMedia->getUrl('school_logo_thumb') : $logoMedia->getUrl())
            : ($school->getFirstMediaUrl('logo', 'thumb') ?: $school->getFirstMediaUrl('logo'));
    @endphp

    <x-slot name="h2">{{ $dirLabel }}</x-slot>
    <x-slot name="t_description">@if($school->city) 📍 {{ $school->city }} @endif</x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('volleyball_school.index') }}" itemprop="item"><span itemprop="name">Школы волейбола</span></a>
            <meta itemprop="position" content="2">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">{{ $school->name }}</span>
            <meta itemprop="position" content="3">
        </li>
    </x-slot>

    <x-slot name="d_description">
        @if(auth()->check() && auth()->id() === $school->organizer_id)
        <div class="mt-2">
            <a href="{{ route('volleyball_school.edit') }}" class="btn btn-secondary">Редактировать</a>
        </div>
        @endif
    </x-slot>

    <x-slot name="style">
    <style>
        .school-cover { width:100%; max-height:36rem; object-fit:cover; border-radius:1rem; display:block; }
        .school-cover-placeholder { width:100%; height:24rem; border-radius:1rem; background:linear-gradient(135deg,var(--bg2,#f0f0f0),var(--bg3,#e0e0e0)); display:flex; align-items:center; justify-content:center; flex-direction:column; gap:1rem; }
        .school-logo-big { width:8rem; height:8rem; border-radius:50%; object-fit:cover; border:0.3rem solid var(--bg2); flex-shrink:0; }
        .organizer-avatar { width:6rem; height:6rem; border-radius:50%; object-fit:cover; flex-shrink:0; }
    </style>
    </x-slot>

    <div class="container">

        @if(session('status'))
        <div class="ramka"><div class="alert alert-success">{{ session('status') }}</div></div>
        @endif

        {{-- ОБЛОЖКА --}}
        <div class="ramka">
            @if($cover)
            <img src="{{ $cover }}" alt="{{ $school->name }}" class="school-cover">
            @else
            <div class="school-cover-placeholder">
                <div style="font-size:5rem;">🏐</div>
                <div class="f-16" style="opacity:.4;">Обложка не добавлена</div>
                @if(auth()->check() && auth()->id() === $school->organizer_id)
                <a href="{{ route('user.photos') }}" class="btn btn-secondary">+ Добавить обложку</a>
                @endif
            </div>
            @endif
        </div>

        {{-- ОСНОВНАЯ ИНФОРМАЦИЯ --}}
        <div class="ramka">
            <div class="row row2">
                <div class="col-md-8">
                    <div class="card">
                        <div class="d-flex fvc gap-2 mb-2">
                            @if($logo)
                            <img src="{{ $logo }}" alt="logo" class="school-logo-big">
                            @endif
                            <div>
                                <div class="f-24 b-700">{{ $school->name }}</div>
                                @if($dirLabel)<div class="f-16 mt-05">{{ $dirLabel }}</div>@endif
                                @if($school->city)<div class="f-16 mt-05" style="opacity:.6;">📍 {{ $school->city }}</div>@endif
                            </div>
                        </div>
                        @if($school->description)
                        <div class="f-18 mt-2" style="line-height:1.6;">{!! $school->description !!}</div>
                        @endif
                    </div>
                </div>
                <div class="col-md-4" style="display:flex;flex-direction:column;gap:2rem;">
                    <div class="card">
                        <div class="b-600 mb-1">📞 Контакты</div>
                        <ul class="list f-16">
                            @if($school->phone)<li>📱 <a href="tel:{{ $school->phone }}">{{ $school->phone }}</a></li>@endif
                            @if($school->email)<li>✉️ <a href="mailto:{{ $school->email }}">{{ $school->email }}</a></li>@endif
                            @if($school->website)<li>🌐 <a href="{{ $school->website }}" target="_blank" rel="nofollow">{{ $school->website }}</a></li>@endif
                            @if($school->vk_url)<li><span class="icon-vk"></span> <a href="{{ $school->vk_url }}" target="_blank">ВКонтакте</a></li>@endif
                            @if($school->tg_url)<li><span class="icon-tg"></span> <a href="{{ $school->tg_url }}" target="_blank">Telegram</a></li>@endif
                            @if($school->max_url)<li><span class="icon-max"></span> <a href="{{ $school->max_url }}" target="_blank">Max</a></li>@endif
                            @if(!$school->phone && !$school->email && !$school->website && !$school->vk_url && !$school->tg_url && !$school->max_url)
                            <li style="opacity:.5;">Не указаны</li>
                            @endif
                        </ul>
                    </div>
                    @if($organizer)
                    <div class="card">
                        <div class="b-600 mb-1">👤 Организатор</div>
                        <a href="{{ route('users.show', $organizer->id) }}" class="d-flex fvc gap-2" style="text-decoration:none;">
                            <img src="{{ $organizer->profile_photo_url }}" alt="{{ $organizer->first_name }}" class="organizer-avatar">
                            <div>
                                <div class="b-600">{{ trim($organizer->first_name . ' ' . $organizer->last_name) }}</div>
                                <div class="f-14 cd mt-05">Перейти в профиль →</div>
                            </div>
                        </a>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- АБОНЕМЕНТЫ --}}
        @if(isset($subscriptionTemplates) && $subscriptionTemplates->isNotEmpty())
        <div class="ramka">
            <h2 class="-mt-05">🎫 Абонементы</h2>
            <div class="row row2">
                @foreach($subscriptionTemplates as $t)
                <div class="col-md-4">
                    <div class="card">
                        <div class="b-600 f-18 mb-1">{{ $t->name }}</div>
                        @if($t->description)<div class="f-14 mb-2" style="opacity:.7;">{{ $t->description }}</div>@endif
                        <div class="d-flex between fvc mb-2">
                            <div>
                                <span class="f-24 b-700 cd">{{ $t->visits_total }}</span>
                                <span class="f-14" style="opacity:.6;"> посещений</span>
                            </div>
                            <div class="f-24 b-700">{{ $t->price_minor > 0 ? number_format($t->price_minor/100, 0).' ₽' : 'Бесплатно' }}</div>
                        </div>
                        <ul class="list f-14 mb-2">
                            @if($t->valid_until)<li>До: {{ $t->valid_until->format('d.m.Y') }}</li>@else<li>Бессрочный</li>@endif
                            @if($t->freeze_enabled)<li>❄️ Заморозка разрешена</li>@endif
                            @if($t->transfer_enabled)<li>🔄 Передача разрешена</li>@endif
                            @if($t->sale_limit)<li>Осталось: {{ max(0, $t->sale_limit - $t->sold_count) }} из {{ $t->sale_limit }}</li>@endif
                        </ul>
                        @auth
                            @if(!$t->isSoldOut())
                            <form method="POST" action="{{ route('subscriptions.buy', $t->id) }}">
                                @csrf
                                <button type="submit" class="btn w-100">{{ $t->price_minor > 0 ? '💳 Купить абонемент' : '🎫 Получить абонемент' }}</button>
                            </form>
                            @else
                            <button class="btn w-100" disabled>Продано</button>
                            @endif
                        @else
                        <a href="{{ route('login') }}" class="btn w-100">Войти для покупки</a>
                        @endauth
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- МЕРОПРИЯТИЯ --}}
        @if($occurrences->isEmpty())
        <div class="ramka">
            <div class="alert alert-info">Предстоящих мероприятий пока нет.</div>
        </div>
        @else
        @php
            $fmtDate = function($occ) {
                $tz = $occ->timezone ?: 'Europe/Moscow';
                if (!$occ->starts_at) return ['date'=>'—','time'=>'—','tz'=>$tz,'tzLabel'=>$tz,'raw'=>null];
                $dt = \Carbon\Carbon::parse($occ->starts_at)->setTimezone($tz);
                return ['date'=>$dt->translatedFormat('j M Y'),'time'=>$dt->format('H:i'),'tz'=>$tz,'tzLabel'=>'MSK (UTC+03:00)','raw'=>$dt];
            };
            $joinedOccurrenceIds     = [];
            $restrictedOccurrenceIds = [];
            $trainerColumn           = null;
            $trainersById            = [];
            $guard                   = app(\App\Services\EventRegistrationGuard::class);
            $nowUtc                  = \Carbon\Carbon::now('UTC');
            $userTz                  = auth()->user()?->timezone ?? 'Europe/Moscow';
            $authUser                = auth()->user();
        @endphp
        <div class="ramka">
            <h2 class="-mt-05">📅 Ближайшие мероприятия</h2>
            <div class="row">
                @foreach($occurrences as $occ)
                @php
                    $event = $occ->event;
                    if (!$event) continue;
                    if (!isset($occ->join)) {
                        $occ->join   = $authUser ? $guard->quickCheck($authUser, $occ) : null;
                        $occ->cancel = null;
                    }
                @endphp
                @include('events._card', ['occ' => $occ, 'join' => $occ->join, 'cancel' => $occ->cancel])
                @endforeach
            </div>
        </div>
        @endif

    </div>

    {{-- JOIN MODAL (Fancybox inline) --}}
    <div id="joinModalContent" style="display:none;max-width:420px;width:100%;padding:1.5rem">
        <h2 id="jmTitle" class="-mt-05 f-20 b-600">Запись на мероприятие</h2>
        <div id="jmMeta" class="f-14 mb-05" style="opacity:.6"></div>
        <div id="jmAddr" class="f-14 mb-2" style="opacity:.6"></div>
        <div id="jmError" class="alert alert-error" style="display:none"></div>
        <div id="jmLoading" class="f-14 mb-1" style="display:none;opacity:.6">Загружаю позиции…</div>
        <div id="jmPositions"></div>
        <div class="f-13 mt-2" style="opacity:.5">После выбора позиции вы сразу будете записаны.</div>
    </div>
    <form id="joinForm" method="POST" action="" style="display:none">
        @csrf
        <input type="hidden" name="position" id="joinPosition" value="">
    </form>

    <x-slot name="script">
    <script src="/assets/fas.js"></script>
    <script>
    const positionNames = {
        outside: 'Доигровщик', opposite: 'Диагональный',
        middle: 'ЦБ', setter: 'Связующий', libero: 'Либеро', player: 'Игрок',
    };
    const titleEl   = document.getElementById('jmTitle');
    const metaEl    = document.getElementById('jmMeta');
    const addrEl    = document.getElementById('jmAddr');
    const posWrap   = document.getElementById('jmPositions');
    const errBox    = document.getElementById('jmError');
    const loadingEl = document.getElementById('jmLoading');
    const joinForm  = document.getElementById('joinForm');
    const joinPos   = document.getElementById('joinPosition');

    function showError(msg) { if(errBox){errBox.textContent=msg;errBox.style.display='';} }
    function clearError()   { if(errBox){errBox.textContent='';errBox.style.display='none';} }
    function setLoading(v)  { if(loadingEl) loadingEl.style.display = v ? '' : 'none'; }

    function openJoinModal(payload) {
        clearError(); setLoading(true);
        posWrap.innerHTML = '';
        titleEl.textContent = payload.title || 'Запись на мероприятие';
        metaEl.textContent  = [payload.date, payload.time, payload.tz ? '('+payload.tz+')' : ''].filter(Boolean).join(' ');
        addrEl.textContent  = payload.address || '';
        jQuery.fancybox.open({
            src: '#joinModalContent', type: 'inline',
            opts: { touch: false, animationEffect: false, toolbar: false, smallBtn: true }
        });
    }

    function renderPositions(occurrenceId, freePositions) {
        posWrap.innerHTML = '';
        setLoading(false);
        if (!Array.isArray(freePositions) || !freePositions.length) {
            showError('Свободных мест нет или нет доступных позиций.'); return;
        }
        freePositions.forEach(p => {
            const label = positionNames[p.key] || p.key;
            const free  = p.free ?? 0;
            const btn   = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn w-100 mb-1';
            btn.innerHTML = label + ' <span style="opacity:.6;font-size:1.4rem;">(' + free + ')</span>';
            btn.addEventListener('click', () => {
                joinForm.action = '/occurrences/' + occurrenceId + '/join';
                joinPos.value = p.key;
                joinForm.submit();
            });
            posWrap.appendChild(btn);
        });
    }

    async function fetchAvailability(occurrenceId) {
        const res  = await fetch('/occurrences/' + occurrenceId + '/availability', {
            headers: { 'Accept': 'application/json' }, credentials: 'same-origin'
        });
        const data = await res.json().catch(() => null);
        if (!res.ok || !data) { showError('Не удалось получить данные.'); return null; }
        return data;
    }

    document.querySelectorAll('.js-open-join').forEach(btn => {
        btn.addEventListener('click', async () => {
            const occurrenceId = btn.dataset.occurrenceId;
            openJoinModal({ title: btn.dataset.title, date: btn.dataset.date, time: btn.dataset.time, tz: btn.dataset.tz, address: btn.dataset.address });
            const data = await fetchAvailability(occurrenceId);
            setLoading(false);
            if (!data) return;
            renderPositions(occurrenceId, data.free_positions || data.data?.free_positions || []);
        });
    });
    </script>
    </x-slot>

</x-voll-layout>
