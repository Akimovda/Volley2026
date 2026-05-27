<x-voll-layout>
<x-slot name="title">Подтвердите участие — {{ $team->name }}</x-slot>
<x-slot name="h1">🏆 Место в турнире!</x-slot>

<div class="container" style="max-width:56rem">
    @if(session('success'))
    <div class="alert alert-success">✅ {{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="alert alert-danger">❌ {{ session('error') }}</div>
    @endif

    @if($expired)
    <div class="ramka text-center">
        <div style="font-size:3rem;margin-bottom:1rem">⏰</div>
        <h2>Время истекло</h2>
        <p class="f-16" style="opacity:.7">Время для подтверждения участия команды «{{ $team->name }}» истекло. Место перешло следующей команде.</p>
        <a href="{{ route('events.show', $event) }}" class="btn btn-secondary mt-1">← К мероприятию</a>
    </div>
    @else
    <div class="ramka">
        <h2 class="-mt-05">Место для вашей команды освободилось!</h2>
        <div class="card mb-2">
            <div class="f-16 b-600 mb-05">{{ $team->name }}</div>
            <div class="f-15" style="opacity:.7">{{ $event->title }}</div>
            @if($team->confirmation_expires_at)
            <div class="f-14 mt-1" style="color:#dc2626">
                ⏰ Подтвердите до <strong>{{ $team->confirmation_expires_at->format('d.m.Y H:i') }}</strong>
            </div>
            @endif
        </div>
        <p class="f-15" style="opacity:.7">
            Если вы не подтвердите участие вовремя, место перейдёт следующей команде в очереди.
        </p>
        <div class="d-flex gap-1 mt-2" style="flex-wrap:wrap">
            <form method="POST" action="{{ route('tournamentTeams.reserveConfirm', [$event, $team]) }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <button type="submit" class="btn btn-alert"
                        data-title="Подтвердить участие?"
                        data-text="Ваша команда «{{ $team->name }}» будет переведена в основной состав."
                        data-icon="success"
                        data-confirm-text="Да, подтверждаю"
                        data-cancel-text="Отмена">
                    ✅ Подтвердить участие
                </button>
            </form>
            <form method="POST" action="{{ route('tournamentTeams.reserveDecline', [$event, $team]) }}">
                @csrf
                <button type="submit" class="btn btn-secondary btn-alert"
                        data-title="Отказаться от места?"
                        data-text="Место перейдёт следующей команде. Вы останетесь в конце очереди."
                        data-icon="warning"
                        data-confirm-text="Да, отказаться"
                        data-cancel-text="Отмена">
                    ✗ Отказаться
                </button>
            </form>
        </div>
    </div>
    @endif
</div>
</x-voll-layout>
