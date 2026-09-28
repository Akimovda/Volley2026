{{-- resources/views/volleyball_school/index.blade.php --}}
<x-voll-layout body_class="volleyball-school-page">

    <x-slot name="title">{{ __('profile.school_idx_title') }}</x-slot>
    <x-slot name="description">{{ __('profile.school_idx_description') }}</x-slot>
    <x-slot name="canonical">{{ route('volleyball_school.index') }}</x-slot>
    <x-slot name="h1">{{ __('profile.school_idx_h1') }}</x-slot>
    <x-slot name="t_description">{{ __('profile.school_idx_t_description') }}</x-slot>

    <x-slot name="image">
        <div class="top-section-img" data-aos="fade" data-aos-duration="1000">
            <div class="top-section-light-img">
                <img src="/img/volleyball-school-light.webp" alt="img">
            </div>
            <div class="top-section-dark-img">
                <img src="/img/volleyball-school-dark.webp" alt="img">
            </div>
        </div>
    </x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('volleyball_school.index') }}" itemprop="item">
                <span itemprop="name">Школы волейбола</span>
            </a>
            <meta itemprop="position" content="2">
        </li>
    </x-slot>

    <x-slot name="d_description">
        @if(auth()->check() && (auth()->user()->isOrganizer() || auth()->user()->isAdmin()))
            @php $mySchool = \App\Models\VolleyballSchool::where('organizer_id', auth()->id())->first(); @endphp
            <div class="mt-2" data-aos="fade-up" data-aos-delay="200">
                @if($mySchool)
                    <div class="d-flex gap-1" style="flex-wrap:wrap">
                        <a href="{{ route('volleyball_school.edit') }}" class="btn btn-secondary">✏️ Ред. мою школу</a>
                        <a href="{{ route('volleyball_school.show', $mySchool->slug) }}" class="btn btn-secondary">👁 Моя страница</a>
                    </div>
                @else
                    <a href="{{ route('volleyball_school.create') }}" class="btn">+ Создать страницу школы</a>
                @endif
            </div>
        @endif
    </x-slot>

    <x-slot name="style">
        <style>
            .school-thumb {
                width: 100%;
                aspect-ratio: 16/9;
                object-fit: cover;
                border-radius: 0.8rem 0.8rem 0 0;
                display: block;
            }
            .school-nophoto {
                width: 100%;
                aspect-ratio: 16/9;
                background: var(--bg2, #f3f4f6);
                border-radius: 0.8rem 0.8rem 0 0;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 4rem;
            }
            .school-card-link {
                display: block;
                text-decoration: none;
                color: inherit;
                transition: transform .15s;
            }
            .school-card-link:hover { transform: translateY(-2px); }
            .school-card-link .card { padding: 0; overflow: hidden; }
            .school-card-body { padding: 1.2rem 1.4rem 1.4rem; }
            .school-logo {
                width: 4.8rem;
                height: 4.8rem;
                border-radius: 50%;
                object-fit: cover;
                border: 2px solid var(--bg2);
            }
            .school-card-cover-wrap {
                position: relative;
                width: 100%;
                aspect-ratio: 16/9;
                overflow: hidden;
                border-radius: 1rem;
            }
            .school-card-cover-slide {
                position: absolute;
                inset: 0;
                width: 100%;
                height: 100%;
                object-fit: cover;
                opacity: 0;
            }
            .school-card-cover-mask {
                position: absolute;
                inset: 0;
                background: rgba(6, 5, 1, 0.27);
                z-index: 1;
            }
            .school-card-cover-slides--2 { animation: schoolCoverFade2 8s infinite; }
            .school-card-cover-slides--3 { animation: schoolCoverFade3 12s infinite; }
            .school-card-cover-slides--4 { animation: schoolCoverFade4 16s infinite; }
            .school-card-cover-slides--5 { animation: schoolCoverFade5 20s infinite; }
            @keyframes schoolCoverFade2 {
                0%   { opacity: 0; } 2% { opacity: 1; }
                48%  { opacity: 1; } 50% { opacity: 0; }
                100% { opacity: 0; }
            }
            @keyframes schoolCoverFade3 {
                0%     { opacity: 0; } 2%   { opacity: 1; }
                31%    { opacity: 1; } 33.33% { opacity: 0; }
                100%   { opacity: 0; }
            }
            @keyframes schoolCoverFade4 {
                0%   { opacity: 0; } 2%  { opacity: 1; }
                23%  { opacity: 1; } 25% { opacity: 0; }
                100% { opacity: 0; }
            }
            @keyframes schoolCoverFade5 {
                0%   { opacity: 0; } 2%  { opacity: 1; }
                18%  { opacity: 1; } 20% { opacity: 0; }
                100% { opacity: 0; }
            }
            .school-card-logo-wrap--overlap {
                margin-top: -5rem;
                position: relative;
                z-index: 2;
            }
        </style>
    </x-slot>

    <div class="container">

        @if (session('status'))
            <div class="ramka"><div class="alert alert-success">{{ session('status') }}</div></div>
        @endif

        @if($schools->isEmpty())
            <div class="ramka">
                <div class="alert alert-info">
                    Школ пока нет.
                    @if(auth()->check() && (auth()->user()->isOrganizer() || auth()->user()->isAdmin()))
                        <a href="{{ route('volleyball_school.create') }}">Создайте первую!</a>
                    @endif
                </div>
            </div>
        @else
            <div class="ramka">
                <div class="row">
                    @foreach($schools as $school)
                        @php
                            $organizer = $school->organizer;
                            $logoMedia = $organizer?->getMedia('school_logo')->firstWhere('id', $school->logo_media_id)
                                ?? $organizer?->getMedia('school_logo')->sortByDesc('created_at')->first();
                            $logo = $logoMedia
                                ? ($logoMedia->hasGeneratedConversion('school_logo_thumb') ? $logoMedia->getUrl('school_logo_thumb') : $logoMedia->getUrl())
                                : ($school->getFirstMediaUrl('logo', 'thumb') ?: $school->getFirstMediaUrl('logo'));
                            $organizerCovers = $organizer?->getMedia('school_cover') ?? collect();
                            $coverIds = $school->cover_media_ids !== null
                                ? $school->cover_media_ids
                                : ($school->cover_media_id ? [$school->cover_media_id] : []);
                            $coverSlides = collect($coverIds)
                                ->map(fn ($id) => $organizerCovers->firstWhere('id', $id))
                                ->filter()
                                ->map(fn ($m) => $m->hasGeneratedConversion('school_cover_thumb') ? $m->getUrl('school_cover_thumb') : $m->getUrl())
                                ->take(5)
                                ->values();
                            if ($coverSlides->isEmpty()) {
                                $legacyCover = $school->getFirstMediaUrl('cover', 'thumb') ?: $school->getFirstMediaUrl('cover');
                                if ($legacyCover) $coverSlides = collect([$legacyCover]);
                            }
                            $cover = $coverSlides->first();
                            $dirLabel = match($school->direction) {
                                'classic' => '🏐 Классический волейбол',
                                'beach'   => '🏖 Пляжный волейбол',
                                'both'    => '🏐🏖 Классика + Пляжка',
                                default   => ''
                            };
                        @endphp
                        <div class="col-sm-6 col-lg-4">
                            <a href="{{ route('volleyball_school.show', $school->slug) }}" class="school-card-link">
                                <div class="card">
                                    @if($coverSlides->isNotEmpty())
                                    <div class="school-card-cover-wrap">
                                        @foreach($coverSlides as $i => $slideUrl)
                                        <img src="{{ $slideUrl }}" alt="{{ $school->name }}"
                                             class="school-card-cover-slide {{ $coverSlides->count() > 1 ? 'school-card-cover-slides--'.$coverSlides->count() : '' }}"
                                             @if($coverSlides->count() > 1) style="opacity:0; animation-delay: {{ -($i * 4) }}s" @else style="opacity:1" @endif>
                                        @endforeach
                                        <div class="school-card-cover-mask"></div>
                                    </div>
                                    @endif
                                    <div class="school-card-body">
                                        {{-- Логотип --}}
                                        @if($logo)
                                        <div class="text-center mb-2 {{ $cover ? 'school-card-logo-wrap--overlap' : '' }}">
                                            <img src="{{ $logo }}" alt="logo"
                                                 style="width:8rem;height:8rem;border-radius:50%;object-fit:cover;border:0.2rem solid var(--border-color,#eee);{{ $cover ? 'background:#fff;' : '' }}">
                                        </div>
                                        @elseif(!$cover)
                                        <div class="text-center mb-2">
                                            <div style="width:8rem;height:8rem;border-radius:50%;background:var(--bg2,#f0f0f0);display:flex;align-items:center;justify-content:center;font-size:3rem;margin:0 auto;">🏐</div>
                                        </div>
                                        @endif

                                        {{-- Название --}}
                                        <div class="b-600 f-18 text-center mb-1">{{ $school->name }}</div>

                                        {{-- Направление --}}
                                        @if($dirLabel)
                                        <div class="f-15 text-center mb-05">{{ $dirLabel }}</div>
                                        @endif

                                        {{-- Город --}}
                                        @if($school->city)
                                        <div class="f-14 text-center mb-05" style="opacity:.6;"><x-menu-icon name="pin" class="cd" /> {{ $school->city }}</div>
                                        @endif

                                        {{-- Организатор --}}
                                        @if($organizer)
                                        <div class="f-14 text-center mt-1" style="opacity:.6;">
                                            👤 {{ trim($organizer->first_name . ' ' . $organizer->last_name) }}
                                        </div>
                                        @endif

                                        {{-- Кнопки для Админа --}}
                                        @if(auth()->check() && auth()->user()->isAdmin())
                                        <div class="d-flex gap-1 mt-2">
                                            <a href="{{ route('volleyball_school.edit') }}?id={{ $school->id }}"
                                               class="btn btn-secondary btn-small w-100">✏️ Редактировать</a>
                                            <form method="POST" action="{{ route('volleyball_school.destroy', $school->id) }}">
                                                @csrf @method('DELETE')
                                                <button type="submit"
                                                    class="btn-alert btn btn-danger btn-small"
                                                    data-title="Удалить школу?"
                                                    data-text="{{ $school->name }}"
                                                    data-confirm-text="Да, удалить"
                                                    data-cancel-text="Отмена">🗑</button>
                                            </form>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>

          {{ $schools->links() }}
        @endif

    </div>

</x-voll-layout>