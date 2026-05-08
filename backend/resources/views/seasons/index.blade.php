<x-voll-layout body_class="seasons-page">
<x-slot name="title">{{ __('seasons.idx_title') }}</x-slot>
<x-slot name="h1">{{ __('seasons.idx_h1') }}</x-slot>

		<x-slot name="canonical">{{ route('seasons.index') }}</x-slot>
		
		<x-slot name="breadcrumbs">
			<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
				<a href="{{ route('seasons.index') }}" itemprop="item">
					<span itemprop="name">{{ __('seasons.idx_breadcrumb') }}</span>
				</a>
				<meta itemprop="position" content="2">
			</li>
		</x-slot>
		<x-slot name="t_description">
			{{ __('seasons.idx_t_description') }}
		</x-slot>
		
		<x-slot name="d_description">
				<div class="mt-2" data-aos-delay="250" data-aos="fade-up">
					<a href="{{ route('seasons.create') }}" class="btn btn-primary">{{ __('seasons.idx_btn_create') }}</a>
				</div>					
		</x-slot>

<div class="container">
<div class="ramka">

    <div class="d-flex between fvc mb-3">
        <h2 class="-mt-05 mb-0">{{ __('seasons.idx_section_h2') }}</h2>
        
    </div>

    @if($seasons->isEmpty())
        <div class="alert alert-info">
            <p><strong>{{ __('seasons.idx_empty_lead') }}</strong></p>
            <p>{{ __('seasons.idx_empty_text') }}</p>
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
                                    'active' => ['bg' => 'rgba(16,185,129,.15)', 'color' => '#10b981', 'label' => __('seasons.status_active')],
                                    'completed' => ['bg' => 'rgba(128,128,128,.15)', 'color' => '#6b7280', 'label' => __('seasons.status_completed')],
                                    'draft' => ['bg' => 'rgba(231,97,47,.15)', 'color' => '#E7612F', 'label' => __('seasons.status_draft')],
                                ];
                                $st = $statusColors[$season->status] ?? $statusColors['draft'];
                            @endphp
                            <span class="f-13 b-600" style="background:{{ $st['bg'] }}; padding: 0.4rem 1rem; border-radius:1rem;color:{{ $st['color'] }}">
                                {{ $st['label'] }}
                            </span>
                        </div>

                        <div class="f-16 mb-1">
                            {{ $season->direction === 'beach' ? __('seasons.dir_beach') : __('seasons.dir_classic') }}
                            @if($season->starts_at)
                                · {{ $season->starts_at->format('d.m.Y') }}
                                @if($season->ends_at) — {{ $season->ends_at->format('d.m.Y') }} @endif
                            @endif
                        </div>

                        @if($season->leagues->isNotEmpty())
                            <div class="f-16 mb-1">
                                <span class="b-600">{{ __('seasons.leagues_label') }}</span> {{ $season->leagues->pluck('name')->implode(', ') }}
                            </div>
                        @endif

                        <div class="f-16 mb-1" style="color:#9ca3af">
                            {{ __('seasons.rounds_label') }} {{ $season->seasonEvents->count() ?? 0 }}
                        </div>

                        <div class="d-flex mt-auto" style="gap:8px;margin-top:auto">
                            <a href="{{ route('seasons.edit', $season) }}" class="btn btn-primary f-13" style="padding:6px 14px">⚙️</a>
                            @php $seasonEvent = $season->seasonEvents->unique('event_id')->first(); @endphp
                            @if($seasonEvent && $seasonEvent->event)
                            <a href="{{ route('tournament.setup', $seasonEvent->event) }}" class="btn btn-primary f-13" style="padding:6px 14px;background:#E7612F;border-color:#E7612F">🏐 ⚔️</a>
                            @endif
                            <a href="{{ route('seasons.show', $season) }}" class="btn btn-secondary f-13" style="padding:6px 14px">{{ __('seasons.btn_public') }}</a>
                            @if($season->slug)
                                <a href="{{ route('seasons.show.slug', [$season->league?->slug ?? 'league', $season->slug]) }}" class="btn btn-secondary f-13" style="padding:6px 14px" title="{{ __('seasons.btn_public_link_title') }}">🔗</a>
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