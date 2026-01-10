<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Привязка аккаунтов
        </h2>
    </x-slot>

    @php
        /** @var \App\Models\User $u */
        $u = auth()->user();

        // ===== [AUDIT] История привязок (если таблица есть) =====
        $audits = [];
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('account_link_audits')) {
                $audits = \Illuminate\Support\Facades\DB::table('account_link_audits')
                    ->where('user_id', $u->id)
                    ->orderByDesc('id')
                    ->limit(20)
                    ->get();
            }
        } catch (\Throwable $e) {
            $audits = [];
        }
    @endphp

    <div class="py-10">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- =============================================================== --}}
            {{-- ===== [TITLE] Ввести код ====================================== --}}
            {{-- =============================================================== --}}
            <div class="v-card">
                <div class="v-card__body">
                    <div class="text-2xl font-bold mb-2">Ввести код</div>

                    {{-- ===== [HINT] Как правильно пользоваться кодом ===== --}}
                    <div class="text-sm text-gray-600 mb-4 leading-relaxed">
                        <div class="font-semibold text-gray-800 mb-1">Важно:</div>
                        <ol class="list-decimal ml-5 space-y-1">
                            <li>Сгенерируйте код в “первом” аккаунте (в профиле).</li>
                            <li><b>Выйдите</b> из первого аккаунта.</li>
                            <li>Войдите <b>вторым способом</b> (Telegram или VK).</li>
                            <li>Вернитесь на эту страницу и вставьте код ниже.</li>
                        </ol>
                        <div class="mt-2">
                            Подсказка: если видите ошибку “Нельзя привязать аккаунт к самому себе” — вы вводите код в том же аккаунте, где его сгенерировали.
                        </div>
                    </div>

                    {{-- ===== [FLASH] ===== --}}
                    @if (session('status'))
                        <div class="v-alert v-alert--success mb-4">
                            <div class="v-alert__text">{{ session('status') }}</div>
                        </div>
                    @endif

                    {{-- ===== [FORM] ===== --}}
                    <form method="POST" action="{{ route('account.link.store') }}" class="space-y-3">
                        @csrf

                        <div>
                            <label class="block mb-1 font-medium">Код</label>
                            <input
                                name="code"
                                value="{{ old('code') }}"
                                class="v-input w-full"
                                placeholder="Например: 7F3K"
                                autocomplete="one-time-code"
                                required
                            />
                            @error('code')
                                <div class="text-sm text-red-600 mt-1 whitespace-pre-line">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="v-actions">
                            <button type="submit" class="v-btn v-btn--primary">Привязать аккаунт</button>
                            <a href="{{ url('/user/profile') }}" class="v-btn v-btn--secondary">Вернуться в профиль</a>
                        </div>
                    </form>
                </div>
            </div>

            {{-- =============================================================== --}}
            {{-- ===== [SECTION] История привязок =============================== --}}
            {{-- =============================================================== --}}
            <div class="v-card">
                <div class="v-card__body">
                    <div class="text-lg font-semibold mb-3">История привязок</div>

                    @if (empty($audits) || count($audits) === 0)
                        <div class="text-sm text-gray-600">
                            Пока нет записей.
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="text-gray-600">
                                    <tr>
                                        <th class="text-left py-2 pr-4">Когда</th>
                                        <th class="text-left py-2 pr-4">Провайдер</th>
                                        <th class="text-left py-2 pr-4">Provider ID</th>
                                        <th class="text-left py-2 pr-4">Метод</th>
                                    </tr>
                                </thead>
                                <tbody class="text-gray-800">
                                    @foreach ($audits as $a)
                                        <tr class="border-t">
                                            <td class="py-2 pr-4 whitespace-nowrap">
                                                {{ \Illuminate\Support\Carbon::parse($a->created_at)->format('Y-m-d H:i') }}
                                            </td>
                                            <td class="py-2 pr-4">
                                                {{ $a->provider ?? '—' }}
                                            </td>
                                            <td class="py-2 pr-4">
                                                <span class="font-mono text-xs">
                                                    {{ $a->provider_user_id ?? '—' }}
                                                </span>
                                            </td>
                                            <td class="py-2 pr-4">
                                                {{ $a->method ?? '—' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="text-xs text-gray-500 mt-2">
                            Показаны последние {{ count($audits) }} записей.
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
