{{-- resources/views/volleyball_school/show.blade.php --}}
<x-voll-layout body_class="volleyball-school-show-page">

    <x-slot name="title">{{ $school->name }}</x-slot>
    <x-slot name="description">{{ Str::limit($school->description ?? $school->name, 160) }}</x-slot>
    <x-slot name="canonical">{{ route('volleyball_school.show', $school->slug) }}</x-slot>
    <x-slot name="h1">{{ $school->name }}</x-slot>

    @php
        $dirLabel = match($school->direction) {
            'classic' => '🏐 Классический волейбол',
            'beach'   => '🏖 Пляжный волейбол',
            'both'    => '🏐🏖 Классика и пляж',
            default   => ''
        };
        $cover = $school->getFirstMediaUrl('cover', 'thumb') ?: $school->getFirstMediaUrl('cover');
        $logo  = $school->getFirstMediaUrl('logo', 'thumb') ?: $school->getFirstMediaUrl('logo');
        $organizer = $school->organizer;
    @endphp

    <x-slot name="h2">{{ $dirLabel }}</x-slot>
    <x-slot name="t_description">
        @if($school->city) 📍 {{ $school->city }} @endif
    </x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('volleyball_school.index') }}" itemprop="item">
                <span itemprop="name">Школы волейбола</span>
            </a>
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
                <a href="{{ route('volleyball_school.edit') }}" class="btn btn-secondary">✏️ Редактировать</a>
            </div>
        @endif
    </x-slot>

    <x-slot name="style">
        <style>
            .school-cover {
                width: 100%;
                max-height: 36rem;
                object-fit: cover;
                border-radius: 1rem;
                display: block;
            }
            .school-logo-big {
                width: 8rem;
                height: 8rem;
                border-radius: 50%;
                object-fit: cover;
                border: 3px solid var(--bg2);
            }
            .organizer-avatar {
                width: 6rem;
                height: 6rem;
                border-radius: 50%;
                object-fit: cover;
            }
        </style>
    </x-slot>

    <div class="container">

        @if (session('status'))
            <div class="ramka"><div class="alert alert-success">{{ session('status') }}</div></div>
        @endif

        {{-- ОБЛОЖКА --}}
        @if($cover)
            <div class="ramka">
                <img src="{{ $cover }}" alt="{{ $school->name }}" class="school-cover">
            </div>
        @endif

        {{-- ОСНОВНОЙ БЛОК --}}
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
                                @if($dirLabel)
                                    <div class="f-16 mt-05">{{ $dirLabel }}</div>
                                @endif
                                @if($school->city)
                                    <div class="f-16 mt-05" style="opacity:.6">📍 {{ $school->city }}</div>
                                @endif
                            </div>
                        </div>

                        @if($school->description)
                            <div class="f-18 mt-2" style="line-height:1.6">
                                {!! $school->description !!}
                            </div>
                        @endif
                    </div>
                </div>

                <div class="col-md-4">
                    {{-- КОНТАКТЫ --}}
                    <div class="card mb-2">
                        <div class="b-600 mb-1">📞 Контакты</div>
                        <ul class="list f-16">
                            @if($school->phone)
                                <li>📱 <a href="tel:{{ $school->phone }}">{{ $school->phone }}</a></li>
                            @endif
                            @if($school->email)
                                <li>✉️ <a href="mailto:{{ $school->email }}">{{ $school->email }}</a></li>
                            @endif
                            @if($school->website)
                                <li>🌐 <a href="{{ $school->website }}" target="_blank" rel="nofollow">{{ $school->website }}</a></li>
                            @endif
                            @if(!$school->phone && !$school->email && !$school->website)
                                <li style="opacity:.5">Не указаны</li>
                            @endif
                        </ul>
                   

                    {{-- ОРГАНИЗАТОР --}}
                    @if($organizer)

                            <div class="b-600 mb-1">👤 Организатор</div>
                            <a href="{{ route('users.show', $organizer->id) }}" class="d-flex fvc gap-2" style="text-decoration:none">
                                <img src="{{ $organizer->profile_photo_url }}"
                                     alt="{{ $organizer->first_name }}"
                                     class="organizer-avatar">
                                <div>
                                    <div class="b-600">{{ trim($organizer->first_name . ' ' . $organizer->last_name) }}</div>
                                    <div class="f-14 cd mt-05">Перейти в профиль →</div>
                                </div>
                            </a>
                        
                    @endif
					 </div>
                </div>
            </div>
        </div>

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
                        return [
                            'date'    => $dt->translatedFormat('j M Y'),
                            'time'    => $dt->format('H:i'),
                            'tz'      => $tz,
                            'tzLabel' => 'MSK (UTC+03:00)',
                            'raw'     => $dt,
                        ];
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
            @endif
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
                    @if($t->description)
                    <div class="f-14 mb-2" style="opacity:.7">{{ $t->description }}</div>
                    @endif
                    <div class="d-flex between fvc mb-2">
                        <div>
                            <span class="f-24 b-700 cd">{{ $t->visits_total }}</span>
                            <span class="f-14" style="opacity:.6"> посещений</span>
                        </div>
                        <div class="text-right">
                            <div class="f-24 b-700">{{ $t->price_minor > 0 ? number_format($t->price_minor/100, 0).' ₽' : 'Бесплатно' }}</div>
                        </div>
                    </div>
                    <ul class="list f-14 mb-2">
                        @if($t->valid_until)
                        <li>До: {{ $t->valid_until->format('d.m.Y') }}</li>
                        @else
                        <li>Бессрочный</li>
                        @endif
                        @if($t->freeze_enabled)
                        <li>❄️ Заморозка разрешена</li>
                        @endif
                        @if($t->transfer_enabled)
                        <li>🔄 Передача разрешена</li>
                        @endif
                        @if($t->sale_limit)
                        <li>Осталось: {{ max(0, $t->sale_limit - $t->sold_count) }} из {{ $t->sale_limit }}</li>
                        @endif
                    </ul>
                    @auth
                        @if(!$t->isSoldOut())
                        <form method="POST" action="{{ route('subscriptions.buy', $t->id) }}">
                            @csrf
                            <button type="submit" class="btn w-100">
                                {{ $t->price_minor > 0 ? '💳 Купить абонемент' : '🎫 Получить абонемент' }}
                            </button>
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

    {{-- JOIN MODAL --}}
    <div id="joinModalBackdrop" class="join-backdrop hidden" style="position:fixed;inset:0;z-index:1050;background:rgba(0,0,0,.55);">
        <div class="h-100 d-flex align-items-center justify-content-center p-3">
            <div class="join-modal" style="max-width:720px;width:100%;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.25);">
                <div class="p-3 border-bottom d-flex align-items-start justify-content-between gap-3">
                    <div>
                        <div id="jmTitle" class="fw-semibold fs-5">Запись</div>
                        <div id="jmMeta" class="text-muted small mt-1"></div>
                        <div id="jmAddr" class="text-muted small mt-1"></div>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm js-close-join">✕</button>
                </div>
                <div class="p-3">
                    <div id="jmError" class="alert alert-danger d-none mb-2"></div>
                    <div class="text-muted small mb-2">Выбери позицию:</div>
                    <div id="jmLoading" class="text-muted small d-none mb-2">Загружаю доступные позиции…</div>
                    <div id="jmPositions" class="row g-2"></div>
                </div>
            </div>
        </div>
    </div>
    <form id="joinForm" method="POST" action="" class="d-none">
        @csrf
        <input type="hidden" name="position" id="joinPosition" value="">
    </form>
    <style>.join-backdrop.hidden{display:none!important;}</style>

    <x-slot name="script">
    <script src="/assets/fas.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const backdrop  = document.getElementById('joinModalBackdrop');
        const titleEl   = document.getElementById('jmTitle');
        const metaEl    = document.getElementById('jmMeta');
        const addrEl    = document.getElementById('jmAddr');
        const posWrap   = document.getElementById('jmPositions');
        const errBox    = document.getElementById('jmError');
        const loadingEl = document.getElementById('jmLoading');
        const joinForm  = document.getElementById('joinForm');
        const joinPos   = document.getElementById('joinPosition');

        function showError(msg) { errBox.textContent = msg; errBox.classList.remove('d-none'); }
        function clearError()   { errBox.textContent = ''; errBox.classList.add('d-none'); }
        function setLoading(v)  { loadingEl.classList.toggle('d-none', !v); }

        function openModal(payload) {
            clearError(); setLoading(true);
            titleEl.textContent = payload.title || 'Запись';
            metaEl.textContent  = [payload.date, payload.time, payload.tz ? '('+payload.tz+')' : ''].filter(Boolean).join(' ');
            addrEl.textContent  = payload.address || '';
            posWrap.innerHTML   = '';
            backdrop.classList.remove('hidden');
        }
        function closeModal() {
            backdrop.classList.add('hidden');
            posWrap.innerHTML = '';
            clearError(); setLoading(false);
        }
        function renderPositions(occurrenceId, freePositions) {
            posWrap.innerHTML = '';
            if (!Array.isArray(freePositions) || !freePositions.length) {
                showError('Свободных мест больше нет.'); return;
            }
            freePositions.forEach(p => {
                const col = document.createElement('div');
                col.className = 'col-12';
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-primary w-100';
                btn.innerHTML = `${p.label || p.key} <span class="ms-2 small opacity-75">(${p.free ?? 0})</span>`;
                btn.addEventListener('click', () => {
                    joinForm.action = `/occurrences/${occurrenceId}/join`;
                    joinPos.value = p.key;
                    joinForm.submit();
                });
                col.appendChild(btn);
                posWrap.appendChild(col);
            });
        }
        async function fetchAvailability(occurrenceId) {
            const res = await fetch(`/occurrences/${occurrenceId}/availability`, {
                headers: { 'Accept': 'application/json' }, credentials: 'same-origin'
            });
            let data = null;
            try { data = await res.json(); } catch(e) {}
            if (!res.ok || !data || data.ok === false) {
                showError((data?.message) || 'Не удалось получить данные.'); return null;
            }
            return data;
        }

        document.querySelectorAll('.js-open-join').forEach(btn => {
            btn.addEventListener('click', async () => {
                const occurrenceId = btn.dataset.occurrenceId;
                openModal({ title: btn.dataset.title, date: btn.dataset.date, time: btn.dataset.time, tz: btn.dataset.tz, address: btn.dataset.address });
                const data = await fetchAvailability(occurrenceId);
                setLoading(false);
                if (!data) return;
                renderPositions(occurrenceId, data.free_positions || data.data?.free_positions || []);
            });
        });

        document.querySelectorAll('.js-close-join').forEach(btn => btn.addEventListener('click', closeModal));
        backdrop?.addEventListener('click', e => { if (e.target === backdrop) closeModal(); });
        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
    });
    </script>
    </x-slot>

</x-voll-layout>