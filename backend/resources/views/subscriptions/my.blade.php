<x-voll-layout body_class="subscriptions-my-page">
    <x-slot name="title">{{ __('subscriptions.my_title') }}</x-slot>
    <x-slot name="h1">{{ __('subscriptions.my_title') }}</x-slot>

    <div class="container">
    <div class="row">
        <div class="col-lg-4 col-xl-3 order-2 d-none d-lg-block">
            <div class="sticky">
                <div class="card-ramka">
                    @include('profile._menu', [
                        'menuUser'   => auth()->user(),
                        'activeMenu' => 'subscriptions',
                    ])
                </div>
            </div>
        </div>
        <div class="col-lg-8 col-xl-9 order-1">
        @if(session('status'))
            <div class="ramka"><div class="alert alert-success"><x-ico name="check" /> {{ session('status') }}</div></div>
        @endif
        @if(session('error'))
            <div class="ramka"><div class="alert alert-danger"><x-ico name="warn" /> {{ session('error') }}</div></div>
        @endif

        @if($subs->isEmpty())
            <div class="ramka">
                <div class="alert alert-info">{{ __('subscriptions.my_empty') }}</div>
            </div>
        @else
            @foreach($subs as $sub)
            @php
                $org = $sub->organizer;
                $orgName = $org
                    ? (($org->last_name || $org->first_name)
                        ? trim(($org->last_name ?? '') . ' ' . ($org->first_name ?? ''))
                        : $org->name)
                    : __('subscriptions.my_organizer_fallback');
                // Мероприятия из event_ids шаблона
                $tmplEventIds = $sub->template->event_ids ?? [];
                $tmplEvents = !empty($tmplEventIds)
                    ? \App\Models\Event::whereIn('id', $tmplEventIds)->get(['id','title'])
                    : collect();
                $canAuto = $sub->status === 'active' && $sub->template->auto_booking_enabled;
                $needRenew = in_array($sub->status, ['expired', 'exhausted'], true)
                    || ($sub->status === 'active' && $sub->visits_remaining > 0 && $sub->visits_remaining <= \App\Models\Subscription::ENDING_VISITS);
                $lastUses = $sub->usages->where('action', 'used')->sortByDesc('used_at')->take(3);
            @endphp
            <div class="ramka">
                {{-- Широкий экран: карточка слева, информация и действия справа; узкий — всё друг под другом --}}
                <div class="sub-layout">
                    <div class="sub-layout__card">
    @include('subscriptions._card', ['sub' => $sub, 'card' => $cards[$sub->id]])
                    </div>
                    <div class="sub-layout__info">

                <div class="f-14 mt-2" style="opacity:.75">
                    {{ __('subscriptions.my_organizer') }}
                    @if($org)
                        <a href="{{ route('users.show', $org->id) }}" class="cd b-600">{{ $orgName }}</a>
                    @else
                        <span class="b-600">{{ $orgName }}</span>
                    @endif
                </div>
                @if($tmplEvents->isNotEmpty())
                <div class="f-13 mt-05" style="opacity:.6">
                    {{ __('subscriptions.my_events_label') }}
                    @foreach($tmplEvents as $ev)
                        <a href="{{ route('events.show', $ev->id) }}" class="cd">{{ $ev->title }}</a>@if(!$loop->last), @endif
                    @endforeach
                </div>
                @elseif($org)
                <div class="f-13 mt-05" style="opacity:.5">{{ __('subscriptions.my_all_events') }}</div>
                @endif

                {{-- Возможности абонемента --}}
                @php $tpl = $sub->template; @endphp
                @if($tpl->transfer_enabled || $tpl->freeze_enabled || $tpl->auto_booking_enabled)
                <div class="sub-feats mt-2">
                    @if($tpl->transfer_enabled)<span class="sub-feat sub-feat--transfer"><x-ico name="swap" size="1.6rem" color="#14b8a6" /> {{ __('subscriptions.feat_transfer') }}</span>@endif
                    @if($tpl->freeze_enabled)<span class="sub-feat sub-feat--freeze"><x-ico name="clock" size="1.6rem" color="#0ea5e9" /> {{ __('subscriptions.feat_freeze') }}</span>@endif
                    @if($tpl->auto_booking_enabled)<span class="sub-feat sub-feat--auto"><x-ico name="robot" size="1.6rem" color="#7c3aed" /> {{ __('subscriptions.feat_auto') }}</span>@endif
                </div>
                @endif

                {{-- Действия --}}
                <div class="sub-actions mt-2">
                    @if($needRenew && $org)
                    <a href="{{ route('users.show', $org->id) }}" class="btn btn-small"><x-ico name="refresh" size="1.8rem" color="#16a34a" /> {{ __('subscriptions.my_btn_renew') }}</a>
                    @endif

                    @if($canAuto)
                    <button type="button" class="btn btn-secondary btn-small js-open-sab" data-sub="{{ $sub->id }}">
                        <x-ico name="robot" size="1.8rem" color="#7c3aed" /> {{ __('subscriptions.my_btn_auto') }}@if($sub->autoBookings->count()) ({{ $sub->autoBookings->count() }})@endif
                    </button>
                    @endif

                    @if($sub->status === 'active' && $sub->template->freeze_enabled)
                    <button type="button" class="btn btn-secondary btn-small" onclick="toggleFreeze({{ $sub->id }})">
                        <x-ico name="clock" size="1.8rem" color="#0ea5e9" /> {{ __('subscriptions.my_btn_freeze_short') }}
                    </button>
                    @endif

                    @if($sub->status === 'frozen')
                    <form method="POST" action="{{ route('subscriptions.unfreeze', $sub) }}" class="d-inline-block">
                        @csrf
                        <button class="btn btn-small"><x-ico name="sun" /> {{ __('subscriptions.my_btn_unfreeze') }}</button>
                    </form>
                    @endif

                    @if($sub->status === 'active' && $sub->template->transfer_enabled)
                    <button type="button" class="btn btn-secondary btn-small" onclick="toggleTransfer({{ $sub->id }})">
                        <x-ico name="swap" size="1.8rem" color="#14b8a6" /> {{ __('subscriptions.my_btn_transfer_short') }}
                    </button>
                    @endif

                    <a href="{{ route('subscriptions.usages', $sub) }}" class="btn btn-secondary btn-small"><x-ico name="clipboard" size="1.8rem" color="#64748b" /> {{ __('subscriptions.my_btn_history_short') }}</a>
                </div>

                @if($lastUses->isNotEmpty())
                <div class="sub-last mt-2">
                    <div class="sub-last__title">{{ __('subscriptions.my_last_visits') }}</div>
                    @foreach($lastUses as $u)
                    <div class="sub-last__row">
                        <span>{{ $u->used_at?->translatedFormat('D, j F · H:i') }}</span>
                        <span class="sub-last__minus">{{ __('subscriptions.my_minus_one') }}</span>
                    </div>
                    @endforeach
                </div>
                @endif

                    </div>
                </div>

                @if($canAuto)
                {{-- Настройки автозаписи — в окне (открывается кнопкой «Автозапись» или после сохранения по ссылке #sab-ID) --}}
                <div id="sab-popup-{{ $sub->id }}" style="display:none;max-width:56rem;width:100%">
                    @include('subscriptions._auto_booking', ['sub' => $sub, 'autoEvents' => $autoEvents])
                </div>
                @endif

                {{-- Форма заморозки --}}
                <div id="freeze_form_{{ $sub->id }}" style="display:none" class="mt-2">
                    <form method="POST" action="{{ route('subscriptions.freeze', $sub) }}">
                        @csrf
                        <div class="d-flex gap-2 fvc">
                            <input type="date" name="until" min="{{ now()->addDay()->toDateString() }}"
                                max="{{ now()->addMonths($sub->template->freeze_max_months ?: 3)->toDateString() }}"
                                style="max-width:200px">
                            <button type="submit" class="btn btn-small"><x-ico name="clock" /> {{ __('subscriptions.my_btn_freeze_until') }}</button>
                        </div>
                    </form>
                </div>

                {{-- Форма передачи --}}
                <div id="transfer_form_{{ $sub->id }}" style="display:none" class="mt-2">
                    <form method="POST" action="{{ route('subscriptions.transfer', $sub) }}" id="transfer_sub_form_{{ $sub->id }}">
                        @csrf
                        <input type="hidden" name="to_user_id" id="transfer_user_id_{{ $sub->id }}" value="">
                        <div style="position:relative" class="mb-1">
                            <input type="text" id="transfer_ac_{{ $sub->id }}"
                                autocomplete="off"
                                placeholder="{{ __('subscriptions.my_transfer_ph') }}"
                                class="w-100">
                            <div id="transfer_dd_{{ $sub->id }}" class="form-select-dropdown trainer_dd"></div>
                        </div>
                        <div id="transfer_selected_{{ $sub->id }}" class="f-14 mb-1" style="opacity:.7"></div>
                        <button type="submit" id="transfer_btn_{{ $sub->id }}" class="btn btn-small" disabled
                            data-title="{{ __('subscriptions.my_transfer_title') }}" data-text="{{ __('subscriptions.my_transfer_text') }}" data-confirm-text="{{ __('subscriptions.my_transfer_yes') }}" data-cancel-text="{{ __('subscriptions.cancel') }}" class="btn btn-small btn-alert">
                            <x-ico name="swap" /> {{ __('subscriptions.my_btn_transfer') }}
                        </button>
                    </form>
                </div>
            </div>
            @endforeach
        @endif
    </div>

    <x-slot name="script">
    @php
        $sabEventsJson = $autoEvents;
    @endphp
    <script>
    (function() {
        var data = @json($sabEventsJson);
        var posPlaceholder = @json(__('subscriptions.ab_position_placeholder'));

        function fillPositions(subId, positions) {
            var sel = document.getElementById('sab-pos-' + subId);
            sel.innerHTML = '';
            var ph = document.createElement('option');
            ph.value = ''; ph.textContent = posPlaceholder; sel.appendChild(ph);
            positions.forEach(function(p) {
                var o = document.createElement('option');
                o.value = p.value; o.textContent = p.label; sel.appendChild(o);
            });
            sel.disabled = !positions.length;
            // select обёрнут createCustomSelect() — после перезаполнения <option> пересоздаём обёртку
            if (window.customSelect && typeof window.customSelect.destroy === 'function'
                && typeof window.createCustomSelect === 'function' && window.jQuery) {
                window.customSelect.destroy('sab-pos-' + subId);
                window.createCustomSelect(window.jQuery(sel));
            }
        }

        function refresh(subId) {
            var evSel = document.getElementById('sab-event-' + subId);
            var wrap = document.getElementById('sab-pos-wrap-' + subId);
            var posSel = document.getElementById('sab-pos-' + subId);
            var btn = document.getElementById('sab-submit-' + subId);
            var ev = (data[subId] || []).find(function(e) { return String(e.id) === String(evSel.value); });
            if (!ev) { wrap.style.display = 'none'; fillPositions(subId, []); btn.disabled = true; return; }
            if (ev.required) {
                wrap.style.display = '';
                fillPositions(subId, ev.positions);
                btn.disabled = true; // активируется, когда выбрана позиция
            } else {
                wrap.style.display = 'none';
                fillPositions(subId, []);
                btn.disabled = false;
            }
        }

        document.querySelectorAll('select.sab-event').forEach(function(sel) {
            var subId = sel.getAttribute('data-sub');
            sel.addEventListener('change', function() { refresh(subId); });
            var posSel = document.getElementById('sab-pos-' + subId);
            posSel.addEventListener('change', function() {
                document.getElementById('sab-submit-' + subId).disabled = !posSel.value;
            });
        });
    })();
    </script>
    <script>
    // Автозапись по абонементу — в окне; после сохранения/удаления страница открывается по #sab-ID и окно появляется снова
    function openSab(id) {
        if (!window.jQuery || !jQuery.fancybox) return;
        jQuery.fancybox.open({ src: '#sab-popup-' + id, type: 'inline',
            opts: { hideScrollbar: false, touch: false, toolbar: false, smallBtn: true, animationEffect: 'zoom-in-out' } });
    }
    document.querySelectorAll('.js-open-sab').forEach(function (b) { b.addEventListener('click', function () { openSab(b.dataset.sub); }); });
    if (location.hash.indexOf('#sab-') === 0) { var __sid = location.hash.slice(5); if (document.getElementById('sab-popup-' + __sid)) setTimeout(function () { openSab(__sid); }, 250); }
    function toggleFreeze(id) {
        const el = document.getElementById('freeze_form_' + id);
        el.style.display = el.style.display === 'none' ? '' : 'none';
    }
    function toggleTransfer(id) {
        const el = document.getElementById('transfer_form_' + id);
        el.style.display = el.style.display === 'none' ? '' : 'none';
    }
    </script>
    <script>
    (function() {
        function initTransferAc(subId) {
            var input    = document.getElementById('transfer_ac_' + subId);
            var dd       = document.getElementById('transfer_dd_' + subId);
            var hidden   = document.getElementById('transfer_user_id_' + subId);
            var selected = document.getElementById('transfer_selected_' + subId);
            var btn      = document.getElementById('transfer_btn_' + subId);
            if (!input) return;

            var timer = null;

            function showDd() { dd.classList.add('form-select-dropdown--active'); }
            function hideDd() { dd.classList.remove('form-select-dropdown--active'); }
            function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;'); }

            function pick(id, label) {
                hidden.value = id;
                input.value = label;
                selected.textContent = @json(__('subscriptions.my_user_selected', ['label' => ''])) + label;
                btn.disabled = false;
                hideDd();
            }

            input.addEventListener('input', function() {
                clearTimeout(timer);
                hidden.value = '';
                btn.disabled = true;
                selected.textContent = '';
                var q = input.value.trim();
                if (q.length < 2) { hideDd(); return; }
                dd.innerHTML = '<div class="city-message">' + @json(__('subscriptions.my_search_searching')) + '</div>';
                showDd();
                timer = setTimeout(function() {
                    fetch('/ajax/users/search?q=' + encodeURIComponent(q), {
                        headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                        credentials: 'same-origin'
                    })
                    .then(function(r) { return r.status === 401 ? { items: [] } : r.json(); })
                    .then(function(data) {
                        dd.innerHTML = '';
                        var items = data.items || [];
                        if (!items.length) {
                            dd.innerHTML = '<div class="city-message">' + @json(__('subscriptions.my_search_no_results')) + '</div>';
                            showDd(); return;
                        }
                        items.forEach(function(item) {
                            var div = document.createElement('div');
                            div.className = 'trainer-item form-select-option';
                            var botBadge = item.is_bot ? '<span style="display:inline-block;padding:1px 8px;border-radius:10px;font-size:11px;font-weight:600;background:#fef3c7;color:#92400e;margin-left:.5rem">бот</span>' : '';
                            div.innerHTML = '<div class="f-14">' + esc(item.label || item.name) + botBadge + '</div>';
                            div.addEventListener('click', function() { pick(item.id, item.label || item.name); });
                            dd.appendChild(div);
                        });
                        showDd();
                    });
                }, 250);
            });

            document.addEventListener('click', function(e) {
                if (!input.contains(e.target) && !dd.contains(e.target)) hideDd();
            });
        }

        // Инициализируем для каждого абонемента
        document.querySelectorAll('[id^="transfer_ac_"]').forEach(function(el) {
            var subId = el.id.replace('transfer_ac_', '');
            initTransferAc(subId);
        });
    })();
    </script>
    </x-slot>
</x-voll-layout>
