{{-- resources/views/volleyball_school/index.blade.php --}}
<x-voll-layout body_class="volleyball-school-page">

    <x-slot name="title">Школы волейбола</x-slot>
    <x-slot name="description">Школы и сообщества волейбола — тренировки, обучение, команды</x-slot>
    <x-slot name="canonical">{{ route('volleyball_school.index') }}</x-slot>
    <x-slot name="h1">Школы волейбола</x-slot>
    <x-slot name="t_description">Школы, клубы и волейбольные сообщества</x-slot>

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
                    <a href="{{ route('volleyball_school.edit') }}" class="btn btn-secondary">✏️ Редактировать мою школу</a>
                    <a href="{{ route('volleyball_school.show', $mySchool->slug) }}" class="btn btn-secondary ml-1">👁 Моя страница</a>
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
                <div class="row row2">
                    @foreach($schools as $school)
                        @php
                            $organizer = $school->organizer;
                            $logoMedia = $organizer?->getMedia('school_logo')->sortByDesc('created_at')->first();
                            $logo = $logoMedia
                                ? ($logoMedia->hasGeneratedConversion('school_logo_thumb') ? $logoMedia->getUrl('school_logo_thumb') : $logoMedia->getUrl())
                                : ($school->getFirstMediaUrl('logo', 'thumb') ?: $school->getFirstMediaUrl('logo'));
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
                                    <div class="school-card-body">
                                        {{-- Логотип --}}
                                        <div class="text-center mb-2">
                                            @if($logo)
                                            <img src="{{ $logo }}" alt="logo"
                                                 style="width:8rem;height:8rem;border-radius:50%;object-fit:cover;border:0.2rem solid var(--border-color,#eee);">
                                            @else
                                            <div style="width:8rem;height:8rem;border-radius:50%;background:var(--bg2,#f0f0f0);display:flex;align-items:center;justify-content:center;font-size:3rem;margin:0 auto;">🏐</div>
                                            @endif
                                        </div>

                                        {{-- Название --}}
                                        <div class="b-600 f-18 text-center mb-1">{{ $school->name }}</div>

                                        {{-- Направление --}}
                                        @if($dirLabel)
                                        <div class="f-15 text-center mb-05">{{ $dirLabel }}</div>
                                        @endif

                                        {{-- Город --}}
                                        @if($school->city)
                                        <div class="f-14 text-center mb-05" style="opacity:.6;">📍 {{ $school->city }}</div>
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