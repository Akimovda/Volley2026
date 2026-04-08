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
                            $cover = $school->getFirstMediaUrl('cover', 'thumb') ?: $school->getFirstMediaUrl('cover');
                            $logo  = $school->getFirstMediaUrl('logo', 'thumb') ?: $school->getFirstMediaUrl('logo');
                            $dirLabel = match($school->direction) {
                                'classic' => '🏐 Классика',
                                'beach'   => '🏖 Пляж',
                                'both'    => '🏐🏖 Классика + Пляж',
                                default   => ''
                            };
                        @endphp
                        <div class="col-sm-6 col-lg-4">
                            <a href="{{ route('volleyball_school.show', $school->slug) }}" class="school-card-link">
                                <div class="card">
                                    @if($cover)
                                        <img src="{{ $cover }}" alt="{{ $school->name }}" class="school-thumb">
                                    @else
                                        <div class="school-nophoto">🏐</div>
                                    @endif

                                    <div class="school-card-body">
                                        <div class="d-flex fvc gap-2 mb-1">
                                            @if($logo)
                                                <img src="{{ $logo }}" alt="logo" class="school-logo">
                                            @endif
                                            <div>
                                                <div class="b-600 f-18">{{ $school->name }}</div>
                                                @if($school->city)
                                                    <div class="f-14" style="opacity:.6">📍 {{ $school->city }}</div>
                                                @endif
                                            </div>
                                        </div>
                                        @if($dirLabel)
                                            <div class="f-14 mt-05">{{ $dirLabel }}</div>
                                        @endif
                                        @if($school->description)
                                            <div class="f-16 mt-1" style="overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;opacity:.7">
                                                {{ $school->description }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="ramka">{{ $schools->links() }}</div>
        @endif

    </div>

</x-voll-layout>