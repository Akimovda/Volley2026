<x-app-layout>
    <div class="v-container">
        <h1 class="text-2xl font-bold mb-4">Привязать аккаунт по коду</h1>

        @if ($errors->any())
            <div class="v-alert v-alert--warn mb-4">
                <div class="v-alert__title">Проверьте поле</div>
                <div class="v-alert__text">
                    <ul class="list-disc pl-6 mt-2">
                        @foreach ($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('account.link.store') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block mb-1 font-medium">Одноразовый код</label>
                <input name="code" class="v-input w-full" value="{{ old('code') }}" placeholder="Например: A7K9M2QX">
                <div class="v-hint mt-1">Код можно сгенерировать в профиле основного аккаунта. Срок жизни ~10 минут.</div>
            </div>

            <div class="v-actions">
                <button type="submit" class="v-btn v-btn--primary">Привязать</button>
                <a class="v-btn v-btn--secondary" href="/user/profile">← Назад</a>
            </div>
        </form>
    </div>
</x-app-layout>
