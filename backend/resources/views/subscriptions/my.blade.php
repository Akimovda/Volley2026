<x-voll-layout body_class="subscriptions-my-page">
    <x-slot name="title">Мои абонементы</x-slot>
    <x-slot name="h1">Мои абонементы</x-slot>

    <div class="container">
        @if(session('status'))
            <div class="ramka"><div class="alert alert-success">{{ session('status') }}</div></div>
        @endif
        @if(session('error'))
            <div class="ramka"><div class="alert alert-danger">{{ session('error') }}</div></div>
        @endif

        @if($subs->isEmpty())
            <div class="ramka">
                <div class="alert alert-info">У вас нет абонементов.</div>
            </div>
        @else
            @foreach($subs as $sub)
            <div class="ramka">
                <div class="d-flex between fvc mb-2">
                    <div>
                        <h2 class="-mt-05">{{ $sub->template->name }}</h2>
                        <div class="f-14" style="opacity:.6">{{ $sub->organizer->name ?? 'Организатор' }}</div>
                    </div>
                    <div class="text-right">
                        @if($sub->status === 'active')
                            <span class="cs b-600 f-18">✅ Активен</span>
                        @elseif($sub->status === 'frozen')
                            <span class="cd b-600 f-18">❄️ Заморожен</span>
                        @elseif($sub->status === 'expired')
                            <span style="opacity:.5" class="f-18">⌛ Истёк</span>
                        @elseif($sub->status === 'exhausted')
                            <span style="opacity:.5" class="f-18">📭 Исчерпан</span>
                        @endif
                    </div>
                </div>

                <div class="row row2">
                    <div class="col-6 col-md-3">
                        <div class="card text-center">
                            <div class="f-13" style="opacity:.6">Осталось посещений</div>
                            <div class="f-32 b-700 {{ $sub->visits_remaining > 0 ? 'cs' : 'red' }}">
                                {{ $sub->visits_remaining }}
                            </div>
                            <div class="f-13" style="opacity:.5">из {{ $sub->visits_total }}</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card text-center">
                            <div class="f-13" style="opacity:.6">Действует до</div>
                            <div class="f-18 b-600">
                                {{ $sub->expires_at ? $sub->expires_at->format('d.m.Y') : '∞' }}
                            </div>
                        </div>
                    </div>
                    @if($sub->status === 'frozen')
                    <div class="col-6 col-md-3">
                        <div class="card text-center">
                            <div class="f-13" style="opacity:.6">Заморожен до</div>
                            <div class="f-18 b-600 cd">{{ $sub->frozen_until?->format('d.m.Y') }}</div>
                        </div>
                    </div>
                    @endif
                </div>

                {{-- Прогресс-бар --}}
                <div class="mt-2 mb-2">
                    @php $pct = $sub->visits_total > 0 ? round(($sub->visits_remaining / $sub->visits_total) * 100) : 0; @endphp
                    <div style="background:#eee;border-radius:8px;height:10px">
                        <div style="width:{{ $pct }}%;background:{{ $pct > 50 ? 'var(--cs)' : ($pct > 20 ? 'var(--cd)' : 'var(--red)') }};height:10px;border-radius:8px;transition:width .3s"></div>
                    </div>
                    <div class="f-13 mt-05 text-right" style="opacity:.6">{{ $pct }}% осталось</div>
                </div>

                {{-- Действия --}}
                <div class="d-flex gap-2 flex-wrap mt-2">
                    <a href="{{ route('subscriptions.usages', $sub) }}" class="btn btn-secondary btn-small">
                        📋 История посещений
                    </a>

                    @if($sub->status === 'active' && $sub->template->freeze_enabled)
                    <button class="btn btn-secondary btn-small" onclick="toggleFreeze({{ $sub->id }})">
                        ❄️ Заморозить
                    </button>
                    @endif

                    @if($sub->status === 'frozen')
                    <form method="POST" action="{{ route('subscriptions.unfreeze', $sub) }}">
                        @csrf
                        <button class="btn btn-small">🔥 Разморозить</button>
                    </form>
                    @endif

                    @if($sub->status === 'active' && $sub->template->transfer_enabled)
                    <button class="btn btn-secondary btn-small" onclick="toggleTransfer({{ $sub->id }})">
                        🔄 Передать
                    </button>
                    @endif
                </div>

                {{-- Форма заморозки --}}
                <div id="freeze_form_{{ $sub->id }}" style="display:none" class="mt-2">
                    <form method="POST" action="{{ route('subscriptions.freeze', $sub) }}">
                        @csrf
                        <div class="d-flex gap-2 fvc">
                            <input type="date" name="until" min="{{ now()->addDay()->toDateString() }}"
                                max="{{ now()->addMonths($sub->template->freeze_max_months ?: 3)->toDateString() }}"
                                style="max-width:200px">
                            <button type="submit" class="btn btn-small">❄️ Заморозить до</button>
                        </div>
                    </form>
                </div>

                {{-- Форма передачи --}}
                <div id="transfer_form_{{ $sub->id }}" style="display:none" class="mt-2">
                    <form method="POST" action="{{ route('subscriptions.transfer', $sub) }}">
                        @csrf
                        <div class="d-flex gap-2 fvc">
                            <input type="number" name="to_user_id" placeholder="ID игрока" style="max-width:150px">
                            <button type="submit" class="btn btn-small"
                                onclick="return confirm('Передать абонемент? Это действие необратимо.')">
                                🔄 Передать
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            @endforeach
        @endif
    </div>

    <x-slot name="script">
    <script>
    function toggleFreeze(id) {
        const el = document.getElementById('freeze_form_' + id);
        el.style.display = el.style.display === 'none' ? '' : 'none';
    }
    function toggleTransfer(id) {
        const el = document.getElementById('transfer_form_' + id);
        el.style.display = el.style.display === 'none' ? '' : 'none';
    }
    </script>
    </x-slot>
</x-voll-layout>
