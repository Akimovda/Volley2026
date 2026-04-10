<x-voll-layout body_class="subscriptions-page">
    <x-slot name="title">Абонементы</x-slot>
    <x-slot name="h1">Выданные абонементы</x-slot>
    <x-slot name="d_description">
        <div class="d-flex gap-2 mt-2">
            <a href="{{ route('subscription_templates.index') }}" class="btn btn-secondary">📋 Шаблоны</a>
        </div>
    </x-slot>
    <div class="container">
        @if(session('status'))
            <div class="ramka"><div class="alert alert-success">{{ session('status') }}</div></div>
        @endif
        <div class="ramka">
            @if($subs->isEmpty())
                <div class="alert alert-info">Абонементов пока нет.</div>
            @else
            <div class="table-scrollable">
                <table class="table f-16">
                    <thead>
                        <tr><th>ID</th><th>Игрок</th><th>Шаблон</th><th>Посещений</th><th>Статус</th><th>Истекает</th><th>Действия</th></tr>
                    </thead>
                    <tbody>
                        @foreach($subs as $sub)
                        <tr>
                            <td>#{{ $sub->id }}</td>
                            <td><a href="{{ route('users.show', $sub->user_id) }}">{{ $sub->user->name ?? '#'.$sub->user_id }}</a></td>
                            <td>{{ $sub->template->name }}</td>
                            <td>{{ $sub->visits_remaining }} / {{ $sub->visits_total }}</td>
                            <td>
                                @if($sub->status==='active') <span class="cs">✅ Активен</span>
                                @elseif($sub->status==='frozen') <span class="cd">❄️ Заморожен</span>
                                @elseif($sub->status==='expired') <span style="opacity:.5">⌛ Истёк</span>
                                @elseif($sub->status==='exhausted') <span style="opacity:.5">📭 Исчерпан</span>
                                @else {{ $sub->status }} @endif
                            </td>
                            <td>{{ $sub->expires_at ? $sub->expires_at->format('d.m.Y') : '∞' }}</td>
                            <td class="nowrap">
                                <a href="{{ route('subscriptions.usages', $sub) }}" class="btn btn-secondary btn-small">📋</a>
                                <form method="POST" action="{{ route('subscriptions.extend', $sub) }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="days" value="30">
                                    <button class="btn btn-small" onclick="return confirm('Продлить на 30 дней?')">+30д</button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $subs->links() }}
            @endif
        </div>

        {{-- Форма выдачи --}}
        <div class="ramka">
            <h2 class="-mt-05">Выдать абонемент</h2>
            <form method="POST" action="{{ route('subscriptions.issue') }}" class="form">
                @csrf
                <div class="row row2">
                    <div class="col-md-4">
                        <label>Шаблон</label>
                        <select name="template_id" required>
                            @foreach(\App\Models\SubscriptionTemplate::active()->when(!auth()->user()->isAdmin(), fn($q)=>$q->where('organizer_id',auth()->id()))->get() as $t)
                            <option value="{{ $t->id }}">{{ $t->name }} ({{ $t->visits_total }} поc.)</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label>ID пользователя</label>
                        <input type="number" name="user_id" required>
                    </div>
                    <div class="col-md-4">
                        <label>Причина</label>
                        <input type="text" name="reason" placeholder="Подарок, компенсация...">
                    </div>
                </div>
                <button type="submit" class="btn mt-2">Выдать абонемент</button>
            </form>
        </div>
    </div>
</x-voll-layout>
