{{-- resources/views/volleyball_school/show.blade.php --}}
<x-voll-layout body_class="volleyball-school-show-page">

    <x-slot name="title">{{ $school->name }}</x-slot>
    <x-slot name="description">{{ Str::limit($school->description ?? $school->name, 160) }}</x-slot>
    <x-slot name="canonical">{{ route('volleyball_school.show', $school->slug) }}</x-slot>
    <x-slot name="h1">{{ $school->name }}</x-slot>

    @php
        $dirLabel = match($school->direction) {
            'classic' => '🏐 Классический волейбол',
            'beach'   => '🏖 Пляжный волейбол',
            'both'    => '🏐🏖 Классика и пляж',
            default   => ''
        };
        $cover = $school->getFirstMediaUrl('cover', 'thumb') ?: $school->getFirstMediaUrl('cover');
        $logo  = $school->getFirstMediaUrl('logo', 'thumb') ?: $school->getFirstMediaUrl('logo');
        $organizer = $school->organizer;
    @endphp

    <x-slot name="h2">{{ $dirLabel }}</x-slot>
    <x-slot name="t_description">
        @if($school->city) 📍 {{ $school->city }} @endif
    </x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('volleyball_school.index') }}" itemprop="item">
                <span itemprop="name">Школы волейбола</span>
            </a>
            <meta itemprop="position" content="2">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">{{ $school->name }}</span>
            <meta itemprop="position" content="3">
        </li>
    </x-slot>

    <x-slot name="d_description">
        @if(auth()->check() && auth()->id() === $school->organizer_id)
            <div class="mt-2">
                <a href="{{ route('volleyball_school.edit') }}" class="btn btn-secondary">✏️ Редактировать</a>
            </div>
        @endif
    </x-slot>

    <x-slot name="style">
        <style>
            .school-cover {
                width: 100%;
                max-height: 36rem;
                object-fit: cover;
                border-radius: 1rem;
                display: block;
            }
            .school-logo-big {
                width: 8rem;
                height: 8rem;
                border-radius: 50%;
                object-fit: cover;
                border: 3px solid var(--bg2);
            }
            .organizer-avatar {
                width: 6rem;
                height: 6rem;
                border-radius: 50%;
                object-fit: cover;
            }
        </style>
    </x-slot>

    <div class="container">

        @if (session('status'))
            <div class="ramka"><div class="alert alert-success">{{ session('status') }}</div></div>
        @endif

        {{-- ОБЛОЖКА --}}
        @if($cover)
            <div class="ramka">
                <img src="{{ $cover }}" alt="{{ $school->name }}" class="school-cover">
            </div>
        @endif

        {{-- ОСНОВНОЙ БЛОК --}}
        <div class="ramka">
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="d-flex fvc gap-2 mb-2">
                            @if($logo)
                                <img src="{{ $logo }}" alt="logo" class="school-logo-big">
                            @endif
                            <div>
                                <div class="f-24 b-700">{{ $school->name }}</div>
                                @if($dirLabel)
                                    <div class="f-16 mt-05">{{ $dirLabel }}</div>
                                @endif
                                @if($school->city)
                                    <div class="f-16 mt-05" style="opacity:.6">📍 {{ $school->city }}</div>
                                @endif
                            </div>
                        </div>

                        @if($school->description)
                            <div class="f-18 mt-2" style="line-height:1.6">
                                {!! nl2br(e($school->description)) !!}
                            </div>
                        @endif
                    </div>
                </div>

                <div class="col-md-4">
                    {{-- КОНТАКТЫ --}}
                    <div class="card mb-2">
                        <div class="b-600 mb-1">📞 Контакты</div>
                        <ul class="list f-16">
                            @if($school->phone)
                                <li>📱 <a href="tel:{{ $school->phone }}">{{ $school->phone }}</a></li>
                            @endif
                            @if($school->email)
                                <li>✉️ <a href="mailto:{{ $school->email }}">{{ $school->email }}</a></li>
                            @endif
                            @if($school->website)
                                <li>🌐 <a href="{{ $school->website }}" target="_blank" rel="nofollow">{{ $school->website }}</a></li>
                            @endif
                            @if(!$school->phone && !$school->email && !$school->website)
                                <li style="opacity:.5">Не указаны</li>
                            @endif
                        </ul>
                    </div>

                    {{-- ОРГАНИЗАТОР --}}
                    @if($organizer)
                        <div class="card">
                            <div class="b-600 mb-1">👤 Организатор</div>
                            <a href="{{ route('users.show', $organizer->id) }}" class="d-flex fvc gap-2" style="text-decoration:none">
                                <img src="{{ $organizer->profile_photo_url }}"
                                     alt="{{ $organizer->first_name }}"
                                     class="organizer-avatar">
                                <div>
                                    <div class="b-600">{{ trim($organizer->first_name . ' ' . $organizer->last_name) }}</div>
                                    <div class="f-14 cd mt-05">Перейти в профиль →</div>
                                </div>
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- МЕРОПРИЯТИЯ --}}
        <div class="ramka">
            <h2 class="-mt-05">📅 Ближайшие мероприятия</h2>

            @if($occurrences->isEmpty())
                <div class="alert alert-info">Предстоящих мероприятий пока нет.</div>
            @else
                <div class="row row2">
                    @foreach($occurrences as $occ)
                        @php $event = $occ->event; @endphp
                        @if(!$event) @continue @endif
                        <div class="col-sm-6 col-lg-4">
                            @include('events._card', ['occ' => $occ, 'join' => null, 'cancel' => null])
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

    </div>

</x-voll-layout>