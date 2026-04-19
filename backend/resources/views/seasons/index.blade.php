<x-voll-layout body_class="seasons-page">
<x-slot name="title">Мои сезоны</x-slot>
<x-slot name="h1">Мои сезоны</x-slot>

<div class="container">
<div class="ramka">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="f-22 fw-bold mb-0">Сезоны и лиги</h2>
        <a href="{{ route('seasons.create') }}" class="btn btn-primary">+ Создать сезон</a>
    </div>

    @if($seasons->isEmpty())
        <div class="alert alert-info">
            У вас пока нет сезонов. Сезон — это серия турниров с лигами, авто-продвижением и накопительным рейтингом.
        </div>
    @else
        <div class="row">
            @foreach($seasons as $season)
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="card-title mb-0">{{ $season->name }}</h5>
                                <span class="badge bg-{{ $season->status === 'active' ? 'success' : ($season->status === 'completed' ? 'secondary' : 'warning') }}">
                                    {{ $season->status === 'active' ? 'Активен' : ($season->status === 'completed' ? 'Завершён' : 'Черновик') }}
                                </span>
                            </div>

                            <p class="text-muted f-14 mb-2">
                                {{ $season->direction === 'beach' ? 'Пляжный' : 'Классический' }}
                                @if($season->starts_at)
                                    · {{ $season->starts_at->format('d.m.Y') }}
                                    @if($season->ends_at) — {{ $season->ends_at->format('d.m.Y') }} @endif
                                @endif
                            </p>

                            @if($season->leagues->isNotEmpty())
                                <p class="f-14 mb-1">
                                    Лиги: {{ $season->leagues->pluck('name')->implode(', ') }}
                                </p>
                            @endif

                            <p class="f-14 text-muted mb-0">
                                Туров: {{ $season->seasonEvents->count() ?? 0 }}
                            </p>
                        </div>
                        <div class="card-footer bg-transparent">
                            <a href="{{ route('seasons.edit', $season) }}" class="btn btn-sm btn-outline-primary">Управление</a>
                            <a href="{{ route('seasons.show', $season) }}" class="btn btn-sm btn-outline-secondary">Публичная</a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

</div>
</div>

</x-voll-layout>
