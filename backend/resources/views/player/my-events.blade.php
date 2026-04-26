<x-voll-layout>
<x-slot name="title">Мои мероприятия</x-slot>
<x-slot name="h1">Мои мероприятия</x-slot>

<div class="container">
<div class="row">
    <div class="col-lg-3 col-md-4 d-none d-md-block">
        @include('profile._menu', ['activeMenu' => 'my_events'])
    </div>
    <div class="col-lg-9 col-md-8">

        {{-- Фильтр --}}
        <div class="ramka">
            <div class="d-flex" style="gap:8px">
                <a href="{{ route('player.my-events', ['filter' => 'current']) }}"
                   class="btn {{ $filter === 'current' ? 'btn-primary' : 'btn-secondary' }}">
                    Текущие
                </a>
                <a href="{{ route('player.my-events', ['filter' => 'archive']) }}"
                   class="btn {{ $filter === 'archive' ? 'btn-primary' : 'btn-secondary' }}">
                    Архивные
                </a>
            </div>
        </div>

        {{-- Список --}}
        @if($registrations->isEmpty())
        <div class="ramka">
            <div class="alert alert-info mb-0">
                {{ $filter === 'current' ? 'Нет предстоящих мероприятий.' : 'История мероприятий пуста.' }}
            </div>
        </div>
        @else
        @foreach($registrations as $reg)
        @php
            $startsAt = \Carbon\Carbon::parse($reg->starts_at, 'UTC')->setTimezone($userTz);
            $cancelUntil = $reg->cancel_self_until
                ? \Carbon\Carbon::parse($reg->cancel_self_until, 'UTC')
                : ($reg->event_cancel_self_until
                    ? \Carbon\Carbon::parse($reg->event_cancel_self_until, 'UTC')
                    : null);
            $canCancel = $filter === 'current'
                && (!$cancelUntil || now('UTC')->lt($cancelUntil))
                && $startsAt->isFuture();
            $posLabel = $reg->position ? position_name($reg->position) : null;
        @endphp
        <div class="ramka mb-1" style="padding:1rem 1.2rem">
            <div class="d-flex between fvc" style="gap:8px;flex-wrap:wrap">

                {{-- Название и дата --}}
                <div style="flex:1;min-width:0">
                    <a href="{{ url('/events/' . $reg->event_id . '?occurrence=' . $reg->occurrence_id) }}"
                       class="blink b-600 f-17 d-block mb-05" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                        {{ $reg->title }}
                    </a>
                    <div class="f-15 text-muted d-flex" style="gap:12px;flex-wrap:wrap">
                        <span>🗓 {{ $startsAt->locale('ru')->translatedFormat('d F Y, H:i') }}</span>
                        @if($reg->location_name)
                        <span>📍 {{ $reg->location_name }}{{ $reg->city_name ? ', ' . $reg->city_name : '' }}</span>
                        @endif
                        @if($posLabel)
                        <span>🎯 {{ $posLabel }}</span>
                        @endif
                    </div>
                </div>

                {{-- Кнопки --}}
                <div class="d-flex" style="gap:6px;flex-shrink:0">
                    <a href="{{ url('/events/' . $reg->event_id . '?occurrence=' . $reg->occurrence_id) }}"
                       class="btn btn-secondary btn-small" title="Перейти к мероприятию">🔗</a>
                    @if($canCancel)
                    <form method="POST"
                          action="{{ route('occurrences.leave', $reg->occurrence_id) }}"
                          onsubmit="return confirm('Отменить запись на «{{ addslashes($reg->title) }}»?')"
                          style="margin:0">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-small" title="Отменить запись">✖️</button>
                    </form>
                    @endif
                </div>

            </div>
        </div>
        @endforeach

        {{-- Пагинация --}}
        @if($registrations->hasPages())
        <div class="mt-2">
            {{ $registrations->links() }}
        </div>
        @endif
        @endif

    </div>
</div>
</div>
</x-voll-layout>
