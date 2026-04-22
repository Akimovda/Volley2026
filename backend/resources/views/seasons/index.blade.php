<x-voll-layout body_class="seasons-page">
<x-slot name="title">Мои сезоны и лиги</x-slot>
<x-slot name="h1">Мои сезоны и лиги</x-slot>

<div class="container">
<div class="ramka">

    <div class="d-flex between fvc mb-3">
        <h2 class="-mt-05 mb-0">🏆 Сезоны и лиги</h2>
        <a href="{{ route('seasons.create') }}" class="btn btn-primary">+ Создать сезон</a>
    </div>

    @if($seasons->isEmpty())
        <div class="card p-3" style="text-align:center;opacity:.6">
            <p class="f-16 b-600 mb-1">У вас пока нет сезонов</p>
            <p class="f-14">Сезон — это серия турниров с лигами, промоушеном и накопительным рейтингом игроков.</p>
        </div>
    @else
        <div class="row row2">
            @foreach($seasons as $season)
                <div class="col-md-6 col-lg-4 mb-2">
                    <div class="card p-3" style="height:100%">
                        <div class="d-flex between fvc mb-2">
                            <div class="b-700 f-17">{{ $season->name }}</div>
                            @php
                                $statusColors = [
                                    'active' => ['bg' => 'rgba(16,185,129,.15)', 'color' => '#10b981', 'label' => 'Активен'],
                                    'completed' => ['bg' => 'rgba(128,128,128,.15)', 'color' => '#6b7280', 'label' => 'Завершён'],
                                    'draft' => ['bg' => 'rgba(231,97,47,.15)', 'color' => '#E7612F', 'label' => 'Черновик'],
                                ];
                                $st = $statusColors[$season->status] ?? $statusColors['draft'];
                            @endphp
                            <span class="f-12 b-600 p-1 px-2" style="background:{{ $st['bg'] }};border-radius:6px;color:{{ $st['color'] }}">
                                {{ $st['label'] }}
                            </span>
                        </div>

                        <div class="f-13 mb-2" style="color:#6b7280">
                            {{ $season->direction === 'beach' ? '🏖 Пляжный' : '🏐 Классический' }}
                            @if($season->starts_at)
                                · {{ $season->starts_at->format('d.m.Y') }}
                                @if($season->ends_at) — {{ $season->ends_at->format('d.m.Y') }} @endif
                            @endif
                        </div>

                        @if($season->leagues->isNotEmpty())
                            <div class="f-13 mb-1">
                                <span class="b-600">Лиги:</span> {{ $season->leagues->pluck('name')->implode(', ') }}
                            </div>
                        @endif

                        <div class="f-13 mb-2" style="color:#9ca3af">
                            Туров: {{ $season->seasonEvents->count() ?? 0 }}
                        </div>

                        <div class="d-flex mt-auto" style="gap:8px;margin-top:auto">
                            <a href="{{ route('seasons.edit', $season) }}" class="btn btn-primary f-13" style="padding:6px 14px">⚙️</a>
                            @php $seasonEvent = $season->seasonEvents->unique('event_id')->first(); @endphp
                            @if($seasonEvent && $seasonEvent->event)
                            <a href="{{ route('tournament.setup', $seasonEvent->event) }}" class="btn btn-primary f-13" style="padding:6px 14px;background:#E7612F;border-color:#E7612F">🏐 ⚔️</a>
                            @endif
                            <a href="{{ route('seasons.show', $season) }}" class="btn btn-secondary f-13" style="padding:6px 14px">Публичная</a>
                            @if($season->slug)
                                <a href="{{ route('seasons.show.slug', $season->slug) }}" class="btn btn-secondary f-13" style="padding:6px 14px" title="Публичная ссылка">🔗</a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

</div>
</div>

</x-voll-layout>