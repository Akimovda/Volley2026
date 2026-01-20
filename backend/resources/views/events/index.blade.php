{{-- resources/views/events/index.blade.php --}}
<x-app-layout>
    {{-- =========================
         PAGE HEADER
    ========================== --}}
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Мероприятия
        </h2>
    </x-slot>

    {{-- =========================
         FLASH MESSAGES
         - success: session('status')
         - error:   session('error')
    ========================== --}}
    @if (session('status'))
        <div class="v-container mt-6">
            <div class="v-alert v-alert--success">
                <div class="v-alert__text">{{ session('status') }}</div>
            </div>
        </div>
    @endif

    @if (session('error'))
        <div class="v-container mt-6">
            <div class="v-alert v-alert--warn">
                <div class="v-alert__text">{{ session('error') }}</div>
            </div>
        </div>
    @endif

    {{-- =========================
         CONTENT
    ========================== --}}
    <div class="py-10">
        <div class="v-container">

            {{-- No events --}}
            @if ($events->isEmpty())
                <div class="v-alert v-alert--info">
                    <div class="v-alert__text">Пока мероприятий нет. Но скоро появятся 🙂</div>
                </div>
            @else
                <div class="grid gap-4">
                    @foreach ($events as $event)
                        @php
                            // -------------------------
                            // Per-event computed flags
                            // -------------------------
                            $isJoined = in_array((int) $event->id, $joinedEventIds ?? [], true);

                            // restrictedEventIds приходит из EventsController
                            $restrictedEventIds = $restrictedEventIds ?? [];
                            $joinDisabled = in_array((int) $event->id, $restrictedEventIds, true);
                        @endphp

                        <div class="v-card">
                            {{-- Title --}}
                            <div class="v-card__title">{{ $event->title }}</div>

                            {{-- Meta badges --}}
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

                            {{-- Actions --}}
                            <div class="v-actions">
                                @auth
                                    {{-- If already joined -> show "leave" --}}
                                    @if ($isJoined)
                                        <form method="POST" action="{{ route('events.leave', ['event' => $event->id]) }}">
                                            @csrf
                                            @method('DELETE')

                                            <button type="submit" class="v-btn v-btn--secondary">
                                                Отменить запись
                                            </button>
                                        </form>
                                    @else
                                        {{-- Not joined -> show "join" (can be disabled by restriction) --}}
                                        <form method="POST" action="{{ route('events.join', ['event' => $event->id]) }}">
                                            @csrf

                                            @if ($joinDisabled)
                                                <button
                                                    type="button"
                                                    class="v-btn v-btn--primary"
                                                    disabled
                                                    style="opacity:.5;cursor:not-allowed;"
                                                    title="Запись на это мероприятие ограничена"
                                                >
                                                    Записаться
                                                </button>

                                                <div class="text-xs mt-2" style="color:#b91c1c;">
                                                    У вашей учетной записи есть ограничения для этого мероприятия.
                                                </div>
                                            @else
                                                <button type="submit" class="v-btn v-btn--primary">
                                                    Записаться
                                                </button>
                                            @endif
                                        </form>
                                    @endif
                                @else
                                    {{-- Guest --}}
                                    <a class="v-btn v-btn--primary" href="/login">Войти, чтобы записаться</a>
                                @endauth

                                {{-- Quick link to profile --}}
                                <a class="v-btn v-btn--secondary" href="/user/profile">Профиль</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
