@php
    // ========== ДЛИТЕЛЬНОСТЬ ==========
    $durationSec = $occurrence->duration_sec ?: ($event->duration_sec ?: 7200);
    $durH = (int) floor($durationSec / 3600);
    $durM = (int) floor(($durationSec % 3600) / 60);

    // ========== РЕГИСТРАЦИЯ ==========
    $allowReg = !is_null($occurrence->allow_registration) ? (bool) $occurrence->allow_registration : (bool) ($event->allow_registration ?? false);
    $maxPlayers = $occurrence->max_players ?? $event->gameSettings?->max_players ?? 0;

    $regStartsDays = 3;
    $regEndsMin = 15;
    $cancelMin = 60;

    $occStarts = $occurrence->starts_at ? \Carbon\Carbon::parse($occurrence->starts_at) : null;

    if ($occStarts && $occurrence->registration_starts_at) {
        $regStartsDays = (int) \Carbon\Carbon::parse($occurrence->registration_starts_at)->diffInDays($occStarts);
    } elseif ($event->starts_at && $event->registration_starts_at) {
        $regStartsDays = (int) \Carbon\Carbon::parse($event->registration_starts_at)->diffInDays(\Carbon\Carbon::parse($event->starts_at));
    }

    if ($occStarts && $occurrence->registration_ends_at) {
        $regEndsMin = (int) \Carbon\Carbon::parse($occurrence->registration_ends_at)->diffInMinutes($occStarts);
    } elseif ($event->starts_at && $event->registration_ends_at) {
        $regEndsMin = (int) \Carbon\Carbon::parse($event->registration_ends_at)->diffInMinutes(\Carbon\Carbon::parse($event->starts_at));
    }

    if ($occStarts && $occurrence->cancel_self_until) {
        $cancelMin = (int) \Carbon\Carbon::parse($occurrence->cancel_self_until)->diffInMinutes($occStarts);
    } elseif ($event->starts_at && $event->cancel_self_until) {
        $cancelMin = (int) \Carbon\Carbon::parse($event->cancel_self_until)->diffInMinutes(\Carbon\Carbon::parse($event->starts_at));
    }

    $regEndsHours = (int) floor($regEndsMin / 60);
    $regEndsMins = $regEndsMin % 60;
    $cancelHours = (int) floor($cancelMin / 60);
    $cancelMins = $cancelMin % 60;

    $remEnabled = !is_null($occurrence->remind_registration_enabled) ? (bool) $occurrence->remind_registration_enabled : (bool) ($event->remind_registration_enabled ?? false);
    $remMin = (int) ($occurrence->remind_registration_minutes_before ?? $event->remind_registration_minutes_before ?? 600);
    $remH = (int) floor($remMin / 60);
    $remM = $remMin % 60;

    $showParts = !is_null($occurrence->show_participants) ? (bool) $occurrence->show_participants : (bool) ($event->show_participants ?? true);

    // ========== НАЗВАНИЕ И ОПИСАНИЕ ==========
    $titleVal = $occurrence->title ?? $event->title;
    $descVal  = $occurrence->description_html ?? $event->description_html ?? '';

    // ========== ОПЛАТА ==========
    $isPaid = !is_null($occurrence->is_paid) ? (bool) $occurrence->is_paid : (bool) ($event->is_paid ?? false);
    $priceMinor    = $occurrence->price_minor    ?? $event->price_minor    ?? null;
    $priceCurrency = $occurrence->price_currency ?? $event->price_currency ?? 'RUB';
    $priceText     = $occurrence->price_text     ?? $event->price_text     ?? '';
    $paymentMethod = $occurrence->payment_method ?? $event->payment_method ?? '';
    $paymentLink   = $occurrence->payment_link   ?? $event->payment_link   ?? '';

    $priceRub = $priceMinor ? ($priceMinor / 100) : '';

    // ========== ВОЗВРАТ ==========
    $refundFull    = $occurrence->refund_hours_full    ?? $event->refund_hours_full    ?? null;
    $refundPartial = $occurrence->refund_hours_partial ?? $event->refund_hours_partial ?? null;
    $refundPct     = $occurrence->refund_partial_pct   ?? $event->refund_partial_pct   ?? null;

    // ========== ТРЕНЕР ==========
    $trainerId = $occurrence->trainer_user_id ?? $event->trainer_user_id ?? null;
    $trainerName = '';
    if ($trainerId) {
        $trainerUser = \App\Models\User::find($trainerId);
        if ($trainerUser) {
            $trainerName = trim(($trainerUser->first_name ?? '') . ' ' . ($trainerUser->last_name ?? '')) ?: ($trainerUser->name ?? '');
        }
    }

    // ========== ПЕРСОНАЛЬНЫЕ ДАННЫЕ ==========
    $reqPersonal = !is_null($occurrence->requires_personal_data) ? (bool) $occurrence->requires_personal_data : (bool) ($event->requires_personal_data ?? false);

    // ========== ВОЗРАСТ ДЕТЕЙ ==========
    $agePolicy = $occurrence->age_policy ?? $event->age_policy ?? 'adult';
    $childMin  = $occurrence->child_age_min ?? $event->child_age_min ?? null;
    $childMax  = $occurrence->child_age_max ?? $event->child_age_max ?? null;

    // ========== ИГРОВАЯ СХЕМА (из effectiveGameSettings) ==========
    $subtypeVal = $gs->subtype ?? null;
    $teamsCountVal = $gs->teams_count ?? 2;
    $minPlayersVal = $gs->min_players ?? null;
    $genderPolicyVal = $gs->gender_policy ?? 'any';
    $genderLimitedSideVal = $gs->gender_limited_side ?? null;
    $genderLimitedMaxVal = $gs->gender_limited_max ?? null;
    $girlsMaxVal = $gs->girls_max ?? null;
    $allowGirlsVal = (bool) ($gs->allow_girls ?? false);
@endphp

<x-voll-layout body_class="occurrence-edit-page">
    <x-slot name="title">Редактирование даты #{{ $occurrence->id }}</x-slot>
    <x-slot name="h1">Редактирование даты</x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('events.create.event_management') }}" itemprop="item">
                <span itemprop="name">Управление</span>
            </a>
            <meta itemprop="position" content="2">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('events.event_management.occurrences', $event) }}" itemprop="item">
                <span itemprop="name">{{ $event->title }}</span>
            </a>
            <meta itemprop="position" content="3">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">#{{ $occurrence->id }}</span>
            <meta itemprop="position" content="4">
        </li>
    </x-slot>

    <x-slot name="h2">#{{ $occurrence->id }} · {{ $event->title }}</x-slot>
    <x-slot name="t_description">Изменения применяются только к этой дате. Поля предзаполнены значениями из серии — при сохранении они становятся override.</x-slot>

    <x-slot name="style">
    <link rel="stylesheet" type="text/css" href="/assets/trix.css?v={{ time() }}">
    </x-slot>

    <div class="container form">
        @if(session('status'))
        <div class="ramka"><div class="alert alert-success">{{ session('status') }}</div></div>
        @endif
        @if(session('error'))
        <div class="ramka"><div class="alert alert-danger">{{ session('error') }}</div></div>
        @endif

        <form action="{{ route('events.occurrences.update', [$event, $occurrence]) }}" method="POST">
            @csrf @method('PUT')

            {{-- ============ ПЛАШКА: УНАСЛЕДОВАНО ОТ СЕРИИ ============ --}}
            @include('events._partials.series_badge')

            {{-- ============ НАЗВАНИЕ И ОПИСАНИЕ ============ --}}
            @include('events._partials.title_desc')

            {{-- ============ ДАТА И ВРЕМЯ ============ --}}
            @include('events._partials.datetime')

            {{-- ============ ЛОКАЦИЯ И УЧАСТНИКИ ============ --}}
            @include('events._partials.location_players')

            {{-- ============ КОМАНДЫ И ИГРОВАЯ СХЕМА ============ --}}
            @include('events._partials.team_config')

            {{-- ============ УРОВЕНЬ И ВОЗРАСТ ============ --}}
            @include('events._partials.level_age')

            {{-- ============ ГЕНДЕРНЫЕ ОГРАНИЧЕНИЯ ============ --}}
            @include('events._partials.gender')

            {{-- ============ ОПЛАТА ============ --}}
            @include('events._partials.payment')

            {{-- ============ ВОЗВРАТ ============ --}}
            @include('events._partials.refund')

            {{-- ============ ТРЕНЕР ============ --}}
            @include('events._partials.trainer')

            {{-- ============ ПЕРСОНАЛЬНЫЕ ДАННЫЕ ============ --}}
            @include('events._partials.personal_data')

            {{-- ============ РЕГИСТРАЦИЯ ============ --}}
            @include('events._partials.registration')

            {{-- ============ НАПОМИНАНИЕ ============ --}}
            @include('events._partials.reminder')

            {{-- ============ КНОПКИ ============ --}}
            <div class="ramka">
                <div class="d-flex" style="gap:1rem;justify-content:center;flex-wrap:wrap">
                    <button type="submit" class="btn">Сохранить</button>
                    <a href="{{ route('events.event_management.occurrences', $event) }}" class="btn btn-secondary">Отмена</a>
                    <a href="{{ route('events.event_management.edit', $event) }}" class="btn btn-secondary">Остальные настройки (серия)</a>
                </div>
            </div>
        </form>
    </div>

    <x-slot name="script">
    <script src="/assets/trix.js?v={{ time() }}"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // ===== Часы/минуты → скрытое поле минут =====
        function syncHM(hSel, mSel, hidden) {
            hidden.value = parseInt(hSel.value||0)*60 + parseInt(mSel.value||0);
        }
        [
            ['occ_reg_ends_h','occ_reg_ends_m','occ_reg_ends_min'],
            ['occ_cancel_h','occ_cancel_m','occ_cancel_min'],
            ['occ_rem_h','occ_rem_m','occ_rem_min'],
        ].forEach(function(ids) {
            var h = document.getElementById(ids[0]);
            var m = document.getElementById(ids[1]);
            var hid = document.getElementById(ids[2]);
            if (h && m && hid) {
                h.addEventListener('change', function() { syncHM(h,m,hid); });
                m.addEventListener('change', function() { syncHM(h,m,hid); });
            }
        });

        // ===== Платное/бесплатное — показ блока оплаты =====
        var isPaid = document.getElementById('occ_is_paid');
        var payBlock = document.getElementById('occ_payment_block');
        if (isPaid && payBlock) {
            isPaid.addEventListener('change', function() {
                payBlock.style.display = this.checked ? '' : 'none';
            });
        }

        // ===== Возрастная политика — показ полей возраста детей =====
        var agePol = document.getElementById('occ_age_policy');
        var childRow = document.getElementById('occ_child_age_row');
        if (agePol && childRow) {
            agePol.addEventListener('change', function() {
                childRow.style.display = this.value === 'child' ? '' : 'none';
            });
        }

        // ===== Гендерная политика — показ блока лимитов =====
        var genderLimitedWrap = document.getElementById('gender_limited_wrap');
        document.querySelectorAll('input[name="gender_policy"]').forEach(function(r) {
            r.addEventListener('change', function() {
                if (genderLimitedWrap) {
                    genderLimitedWrap.style.display = this.value === 'women_limited' ? '' : 'none';
                }
            });
        });

        // ===== Trix — очистка стилей при вставке =====
        document.addEventListener('trix-paste', function(e) {
            var editor = e.target.editor;
            if (!editor) return;
            setTimeout(function() {
                var html = editor.getDocument().toString();
                // можно добавить стриппинг тут если нужно
            }, 10);
        });

        // ===== Поиск тренера (reuse /api/users/search если есть) =====
        var trainerInput = document.getElementById('occ_trainer_search');
        var trainerId = document.getElementById('occ_trainer_id');
        var trainerResults = document.getElementById('occ_trainer_results');
        var searchTimer = null;

        if (trainerInput && trainerId && trainerResults) {
            trainerInput.addEventListener('input', function() {
                var q = this.value.trim();
                clearTimeout(searchTimer);

                if (q === '') {
                    trainerId.value = '';
                    trainerResults.innerHTML = '';
                    return;
                }
                if (q.length < 2) return;

                searchTimer = setTimeout(function() {
                    fetch('/api/users/search?q=' + encodeURIComponent(q), {
                        headers: { 'Accept': 'application/json' }
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        var list = Array.isArray(data) ? data : (data.data || []);
                        if (!list.length) {
                            trainerResults.innerHTML = '<div class="card f-13" style="margin-top:.5rem">Ничего не найдено</div>';
                            return;
                        }
                        var html = '<div class="card" style="margin-top:.5rem;max-height:250px;overflow:auto">';
                        list.slice(0,10).forEach(function(u) {
                            var nm = (u.first_name||'') + ' ' + (u.last_name||'');
                            nm = nm.trim() || u.name || ('#'+u.id);
                            html += '<div class="occ-trainer-opt" data-id="'+u.id+'" data-name="'+nm.replace(/"/g,'&quot;')+'" style="padding:.5rem;cursor:pointer;border-bottom:1px solid rgba(0,0,0,.05)">'+nm+'</div>';
                        });
                        html += '</div>';
                        trainerResults.innerHTML = html;

                        trainerResults.querySelectorAll('.occ-trainer-opt').forEach(function(el) {
                            el.addEventListener('click', function() {
                                trainerId.value = this.dataset.id;
                                trainerInput.value = this.dataset.name;
                                trainerResults.innerHTML = '';
                            });
                        });
                    })
                    .catch(function() {});
                }, 300);
            });
        }
    });
    </script>
    </x-slot>
</x-voll-layout>
