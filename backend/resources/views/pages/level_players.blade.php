{{-- resources/views/pages/level_players.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Уровни игроков
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <div class="v-card">
                <div class="v-card__body space-y-6">

                    <div>
                        <h1 class="text-2xl font-bold">Как мы определяем уровень игрока</h1>
                        <p class="text-sm text-gray-600 mt-2">
                            Уровень помогает организаторам собирать равные игры. Выберите уровень честно — так всем будет комфортнее.
                        </p>
                    </div>

                    <div class="v-alert v-alert--info">
                        <div class="v-alert__text">
                            <div class="font-semibold mb-1">Подсказка</div>
                            <div class="text-sm">
                                Если сомневаетесь между двумя уровнями — выбирайте <b>ниже</b>.
                                На первой игре организатор может помочь уточнить уровень.
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        {{-- LEVEL: Beginner --}}
                        <div class="v-card">
                            <div class="v-card__body">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <div class="text-lg font-semibold">Beginner — новичок</div>
                                        <div class="text-sm text-gray-600 mt-1">
                                            Играю недавно / учусь базовым элементам.
                                        </div>
                                    </div>
                                    <span style="display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:9999px;background:#F3F4F6;color:#374151;font-weight:700;font-size:12px;">
                                        уровень 0–1
                                    </span>
                                </div>

                                <ul class="list-disc ml-5 mt-3 text-sm space-y-1 text-gray-700">
                                    <li>Подача чаще снизу или простая сверху.</li>
                                    <li>Приём/пас нестабилен, много ошибок по технике.</li>
                                    <li>Редко читаю игру, часто “не успеваю на мяч”.</li>
                                </ul>
                            </div>
                        </div>

                        {{-- LEVEL: Amateur --}}
                        <div class="v-card">
                            <div class="v-card__body">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <div class="text-lg font-semibold">Amateur — любитель</div>
                                        <div class="text-sm text-gray-600 mt-1">
                                            Понимаю правила, играю регулярно, могу держать розыгрыш.
                                        </div>
                                    </div>
                                    <span style="display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:9999px;background:#ECFDF5;color:#065F46;font-weight:700;font-size:12px;">
                                        уровень 2–3
                                    </span>
                                </div>

                                <ul class="list-disc ml-5 mt-3 text-sm space-y-1 text-gray-700">
                                    <li>Стабильная подача (верхняя), иногда — силовая/планер.</li>
                                    <li>Приём и пас в целом контролируемы, ошибки бывают.</li>
                                    <li>Есть понимание расстановок и базовой тактики.</li>
                                </ul>
                            </div>
                        </div>

                        {{-- LEVEL: Advanced --}}
                        <div class="v-card">
                            <div class="v-card__body">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <div class="text-lg font-semibold">Advanced — продвинутый</div>
                                        <div class="text-sm text-gray-600 mt-1">
                                            Уверенно играю на позиции, стабильно атакую/блокирую.
                                        </div>
                                    </div>
                                    <span style="display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:9999px;background:#EEF2FF;color:#3730A3;font-weight:700;font-size:12px;">
                                        уровень 4
                                    </span>
                                </div>

                                <ul class="list-disc ml-5 mt-3 text-sm space-y-1 text-gray-700">
                                    <li>Уверенный приём (в том числе сложных подач), точный пас.</li>
                                    <li>Понимаю тактику, читаю блок/защиту, играю комбинации.</li>
                                    <li>Стабильная атака, есть варианты (удар/скидка/по блоку).</li>
                                </ul>
                            </div>
                        </div>

                        {{-- LEVEL: Pro --}}
                        <div class="v-card">
                            <div class="v-card__body">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <div class="text-lg font-semibold">Pro — высокий уровень</div>
                                        <div class="text-sm text-gray-600 mt-1">
                                            Играю очень стабильно, скорость и качество близки к соревновательным.
                                        </div>
                                    </div>
                                    <span style="display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:9999px;background:#FEF2F2;color:#991B1B;font-weight:700;font-size:12px;">
                                        уровень 5+
                                    </span>
                                </div>

                                <ul class="list-disc ml-5 mt-3 text-sm space-y-1 text-gray-700">
                                    <li>Сильная подача, стабильно держу высокий темп.</li>
                                    <li>Системная игра: тактика, блок‑защита, переходы.</li>
                                    <li>Высокая техника, малое количество ошибок.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="v-alert v-alert--info">
                        <div class="v-alert__text text-sm">
                            <div class="font-semibold mb-1">Важно</div>
                            <div>
                                Организатор может предложить скорректировать уровень после первой игры, чтобы всем было комфортно.
                            </div>
                        </div>
                    </div>

                    <div class="pt-2">
                        <a href="{{ url('/events') }}" class="v-btn v-btn--primary">
                            Перейти к мероприятиям
                        </a>
                    </div>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
