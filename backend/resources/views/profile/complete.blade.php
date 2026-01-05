<x-app-layout>
    <div class="v-container">
        <h1 class="text-2xl font-bold mb-4">Нужно заполнить профиль</h1>

        @if (!empty($requiredKeys))
            <div class="v-alert v-alert--warn">
                <div class="v-alert__title">Перед записью заполните:</div>
                <div class="v-alert__text">
                    <ul class="list-disc pl-6 mt-2">
                        @foreach ($requiredKeys as $key)
                            <li>
                                @switch($key)
                                    @case('full_name') Фамилия и имя @break
                                    @case('phone') Телефон @break
                                    @case('email') Email @break
                                    @case('classic_level') Уровень (классика) @break
                                    @case('beach_level') Уровень (пляж) @break
                                    @default {{ $key }}
                                @endswitch
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="v-actions">
                    <a class="v-btn v-btn--primary" href="/user/profile">Перейти в профиль</a>
                    <a class="v-btn v-btn--secondary" href="/events">К мероприятиям</a>
                </div>

                @if (!empty($eventId))
                    <div class="v-hint">После сохранения профиля мы попробуем автоматически записать вас на мероприятие.</div>
                @endif
            </div>
        @else
            <div class="v-alert v-alert--info">
                <div class="v-alert__text">Откройте профиль и заполните недостающие данные.</div>
                <div class="v-actions">
                    <a class="v-btn v-btn--primary" href="/user/profile">Перейти в профиль</a>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
