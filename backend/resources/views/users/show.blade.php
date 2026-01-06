<x-app-layout>
    <div class="v-container">
        <div class="v-card">
            <div class="v-card__body">
                <div class="flex items-start gap-4">
                    <img
                        src="{{ $u->profile_photo_url }}"
                        alt="avatar"
                        class="rounded-full"
                        style="width:84px;height:84px;object-fit:cover;"
                    />

                    <div class="min-w-0">
                        <h1 class="text-2xl font-bold">{{ $u->displayName() }}</h1>

                        <div class="text-sm text-gray-600 mt-1">
                            @if($u->city)
                                {{ $u->city->name }}@if($u->city->region) ({{ $u->city->region }})@endif
                                ·
                            @endif

                            @php $age = $u->ageYears(); @endphp
                            @if(!is_null($age))
                                {{ $age }} лет ·
                            @endif

                            @if($u->gender === 'm')
                                Мужчина
                            @elseif($u->gender === 'f')
                                Женщина
                            @endif

                            @if(!empty($u->height_cm))
                                · {{ $u->height_cm }} см
                            @endif
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div class="v-card">
                                <div class="v-card__body">
                                    <div class="font-semibold mb-2">Классический волейбол</div>
                                    <div>Уровень: <span class="font-semibold">{{ $u->classic_level ?? '—' }}</span></div>
                                </div>
                            </div>

                            <div class="v-card">
                                <div class="v-card__body">
                                    <div class="font-semibold mb-2">Пляжный волейбол</div>
                                    <div>Уровень: <span class="font-semibold">{{ $u->beach_level ?? '—' }}</span></div>
                                </div>
                            </div>
                        </div>

                        @can('view-sensitive-profile', $u)
                            <div class="v-alert v-alert--info mt-4">
                                <div class="v-alert__title">Персональные данные (видны вам/организаторам/админу)</div>
                                <div class="v-alert__text">
                                    @if(!empty($u->patronymic))
                                        <div>Отчество: <span class="font-semibold">{{ $u->patronymic }}</span></div>
                                    @endif
                                    @if(!empty($u->phone))
                                        <div>Телефон: <span class="font-semibold">{{ $u->phone }}</span></div>
                                    @endif
                                </div>
                            </div>
                        @endcan

                        <div class="v-actions mt-4">
                            <a class="v-btn v-btn--secondary" href="{{ route('users.index') }}">← К списку игроков</a>
                            <a class="v-btn v-btn--secondary" href="/events">К мероприятиям</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</x-app-layout>
