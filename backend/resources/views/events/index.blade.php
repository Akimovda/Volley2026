<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Мероприятия
        </h2>
    </x-slot>

    @if (session('status'))
        <div class="v-container mt-6">
            <div class="v-alert v-alert--success">
                <div class="v-alert__text">{{ session('status') }}</div>
            </div>
        </div>
    @endif

    <div class="py-10">
        <div class="v-container">
            @if ($events->isEmpty())
                <div class="v-alert v-alert--info">
                    <div class="v-alert__text">Пока мероприятий нет. Но скоро появятся 🙂</div>
                </div>
            @else
                <div class="grid gap-4">
                    @foreach ($events as $event)
                        @php($isJoined = in_array($event->id, $joinedEventIds ?? []))

                        <div class="v-card">
                            <div class="v-card__title">{{ $event->title }}</div>

                            <div class="v-card__meta">
                                @if ($event->requires_personal_data)
                                    <span class="v-badge v-badge--warn">Нужны ваши персональные данные</span>
                                @endif

                                @if (!is_null($event->classic_level_min))
                                    <span class="v-badge v-badge--info">Классика от {{ $event->classic_level_min }}</span>
                                @endif

                                @if (!is_null($event->beach_level_min))
                                    <span class="v-badge v-badge--info">Пляж от {{ $event->beach_level_min }}</span>
                                @endif

                                @auth
                                    @if ($isJoined)
                                        <span class="v-badge v-badge--success">Уже записан</span>
                                    @endif
                                @endauth
                            </div>

                            <div class="v-actions">
                                @auth
                                    @if ($isJoined)
                                        <form method="POST" action="{{ route('events.leave', ['event' => $event->id]) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="v-btn v-btn--secondary">
                                                Отменить запись
                                            </button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('events.join', ['event' => $event->id]) }}">
                                            @csrf
                                            <button type="submit" class="v-btn v-btn--primary">
                                                Записаться
                                            </button>
                                        </form>
                                    @endif
                                @else
                                    <a class="v-btn v-btn--primary" href="/login">Войти, чтобы записаться</a>
                                @endauth

                                <a class="v-btn v-btn--secondary" href="/user/profile">Профиль</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
