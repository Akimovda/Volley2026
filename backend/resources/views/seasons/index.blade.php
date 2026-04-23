<x-voll-layout body_class="seasons-page">
<x-slot name="title">Мои сезоны и лиги</x-slot>
<x-slot name="h1">Мои сезоны и лиги</x-slot>

		<x-slot name="canonical">{{ route('seasons.index') }}</x-slot>
		
		<x-slot name="breadcrumbs">
			<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
				<a href="{{ route('seasons.index') }}" itemprop="item">
					<span itemprop="name">Мои сезоны</span>
				</a>
				<meta itemprop="position" content="2">
			</li>
		</x-slot>
		<x-slot name="t_description">
			Серия турниров с лигами, промоушеном и накопительным рейтингом игроков.
		</x-slot>
		
		<x-slot name="d_description">
				<div class="mt-2" data-aos-delay="250" data-aos="fade-up">
					<a href="{{ route('seasons.create') }}" class="btn btn-primary">Создать сезон</a>
				</div>					
		</x-slot>

<div class="container">
<div class="ramka">

    <div class="d-flex between fvc mb-3">
        <h2 class="-mt-05 mb-0">Сезоны и лиги</h2>
        
    </div>

    @if($seasons->isEmpty())
        <div class="alert alert-info">
            <p><strong>У вас пока нет сезонов</strong></p>
            <p>Сезон — это серия турниров с лигами, промоушеном и накопительным рейтингом игроков.</p>
        </div>
    @else
        <div class="row">
            @foreach($seasons as $season)
                <div class="col-md-6 col-lg-4">
                    <div class="card">
                        <div class="d-flex between fvc mb-1">
                            <div class="b-600">{{ $season->name }}</div>
                            @php
                                $statusColors = [
                                    'active' => ['bg' => 'rgba(16,185,129,.15)', 'color' => '#10b981', 'label' => 'Активен'],
                                    'completed' => ['bg' => 'rgba(128,128,128,.15)', 'color' => '#6b7280', 'label' => 'Завершён'],
                                    'draft' => ['bg' => 'rgba(231,97,47,.15)', 'color' => '#E7612F', 'label' => 'Черновик'],
                                ];
                                $st = $statusColors[$season->status] ?? $statusColors['draft'];
                            @endphp
                            <span class="f-13 b-600" style="background:{{ $st['bg'] }}; padding: 0.4rem 1rem; border-radius:1rem;color:{{ $st['color'] }}">
                                {{ $st['label'] }}
                            </span>
                        </div>

                        <div class="f-16 mb-1">
                            {{ $season->direction === 'beach' ? '🏖 Пляжный' : '🏐 Классический' }}
                            @if($season->starts_at)
                                · {{ $season->starts_at->format('d.m.Y') }}
                                @if($season->ends_at) — {{ $season->ends_at->format('d.m.Y') }} @endif
                            @endif
                        </div>

                        @if($season->leagues->isNotEmpty())
                            <div class="f-16 mb-1">
                                <span class="b-600">Лиги:</span> {{ $season->leagues->pluck('name')->implode(', ') }}
                            </div>
                        @endif

                        <div class="f-16 mb-1" style="color:#9ca3af">
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