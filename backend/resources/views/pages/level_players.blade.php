{{-- resources/views/pages/level_players.blade.php --}}
{{-- resources/views/pages/level_players.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Уровни игроков
        </h2>
    </x-slot>
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 sm:p-8">
        <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900">Уровни игроков</h1>

        <p class="mt-3 text-gray-600">
            Определения с форума <span class="font-semibold">volleymsk.ru</span> + наши дополнения к уровню
            «Продолжающий любитель (уверенный)».
        </p>

        <div class="mt-6 grid gap-3 sm:grid-cols-2">
            <a href="#classic" class="block rounded-xl border border-gray-200 p-4 hover:bg-gray-50">
                <div class="font-bold text-gray-900">Классический волейбол</div>
                <div class="text-sm text-gray-600 mt-1">Подростки и взрослые</div>
            </a>
            <a href="#beach" class="block rounded-xl border border-gray-200 p-4 hover:bg-gray-50">
                <div class="font-bold text-gray-900">Пляжный волейбол</div>
                <div class="text-sm text-gray-600 mt-1">Подростки и взрослые</div>
            </a>
        </div>

        {{-- CLASSIC --}}
        <hr class="my-8">
        <h2 id="classic" class="text-xl sm:text-2xl font-bold text-gray-900">Классический волейбол</h2>

        <h3 class="mt-6 text-lg font-bold text-gray-900">Подростки</h3>
        <div class="mt-3 space-y-4">
            <div class="rounded-xl border border-gray-200 p-4">
                <div class="font-bold text-gray-900">Подростки — начальный <span class="text-sm text-gray-500 font-semibold">(0 баллов)</span></div>
                <div class="mt-2 text-gray-700 leading-relaxed">
                    Обучение с нуля, когда ученик впервые приходит на тренировку и обучается всем навыкам с самого начала.
                    Углублённое обучение всем техническим навыкам: передача сверху, передача снизу, техника нападающего удара,
                    правильный разбег, правильные постановки рук и т.д. А также обучение теории, знание расстановки.
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 p-4">
                <div class="font-bold text-gray-900">Подростки — начальный + <span class="text-sm text-gray-500 font-semibold">(0 баллов)</span></div>
                <div class="mt-2 text-gray-700 leading-relaxed">
                    Кто уже имеет понимание в расстановке, знает где нужно стоять, а также владеет техническими навыками на начальной стадии.
                    И также есть начальные физические навыки.
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 p-4">
                <div class="font-bold text-gray-900">Подростки — средний уровень <span class="text-sm text-gray-500 font-semibold">(0 баллов)</span></div>
                <div class="mt-2 text-gray-700 leading-relaxed">
                    Ребята знающие расстановку, понимающие как можно меняться и играющие по схеме 4/2.
                    Технически оснащены и все действия выполняют стабильно (подача, атака, передача), хорошо физически подготовлены.
                </div>
            </div>
        </div>

        <h3 class="mt-8 text-lg font-bold text-gray-900">Взрослые</h3>

        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-sm border border-gray-200 rounded-xl overflow-hidden">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left px-4 py-3 font-bold text-gray-900">Уровень</th>
                        <th class="text-left px-4 py-3 font-bold text-gray-900">Описание</th>
                        <th class="text-left px-4 py-3 font-bold text-gray-900 whitespace-nowrap">Баллы</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr class="align-top">
                        <td class="px-4 py-3 font-semibold text-gray-900 whitespace-nowrap">Начальный</td>
                        <td class="px-4 py-3 text-gray-700 leading-relaxed">
                            Перебрасываем мяч через сетку в свое удовольствие, двумя руками или как получится.
                            Кто умеет — подает сверху и атакует выше троса. Понятия амплуа отсутствуют — играют кто как может!
                        </td>
                        <td class="px-4 py-3 font-bold text-gray-900">1</td>
                    </tr>

                    <tr class="align-top">
                        <td class="px-4 py-3 font-semibold text-gray-900 whitespace-nowrap">Начальный +</td>
                        <td class="px-4 py-3 text-gray-700 leading-relaxed">
                            Играем со всеми основными элементами: подача, прием, передача, атака, блок.
                            Выделенные пасующие (обычно двое). Иногда получается «гасить», «ставить блок в тапки» и т.д.
                            Понятия амплуа — только в части связующего. Техника паса у связующего отсутствует — пасует, как получится.
                        </td>
                        <td class="px-4 py-3 font-bold text-gray-900">2</td>
                    </tr>

                    <tr class="align-top">
                        <td class="px-4 py-3 font-semibold text-gray-900 whitespace-nowrap">Средний −</td>
                        <td class="px-4 py-3 text-gray-700 leading-relaxed">
                            Стабильная и уверенная подача, прием, передача, атака, блок.
                            Активная игра на первой линии и в защите. Выделенные пасующие (обычно двое).
                            Знание расстановки игроков на площадке в каждой зоне.
                            Понятия амплуа — только в части связующего. Техника паса есть, но не всегда получается — есть к чему стремиться.
                        </td>
                        <td class="px-4 py-3 font-bold text-gray-900">3</td>
                    </tr>

                    <tr class="align-top">
                        <td class="px-4 py-3 font-semibold text-gray-900 whitespace-nowrap">Средний (любительский)</td>
                        <td class="px-4 py-3 text-gray-700 leading-relaxed">
                            Стабильная атака, двойной блок. Как правило игра с одним пасующим (5-1) и с игроками первого темпа.
                            Примерно на этом уровне играют средние и лидирующие команды 3–4 лиги ЛВЛ.
                            Присутствуют понятия амплуа. У связующего хорошая техника передач, но не всегда получается играть с первым темпом и диагональными.
                            Либеро: прием 70% и нормальная доводка до связующего.
                        </td>
                        <td class="px-4 py-3 font-bold text-gray-900">4</td>
                    </tr>

                    <tr class="align-top">
                        <td class="px-4 py-3 font-semibold text-gray-900 whitespace-nowrap">Средний +</td>
                        <td class="px-4 py-3 text-gray-700 leading-relaxed">
                            Темповая и комбинационная игра (взлеты-волны-прострелы).
                            Связка 5-1, может довести мяч до любой зоны и поддержать любую комбинацию.
                            Уровень примерно 2 лиги ЛВЛ или перворазрядники-выпускники спортшкол.
                            Либеро: 80% приема и доводка до связующего.
                        </td>
                        <td class="px-4 py-3 font-bold text-gray-900">5</td>
                    </tr>

                    <tr class="align-top">
                        <td class="px-4 py-3 font-semibold text-gray-900 whitespace-nowrap">Полупрофи (К.М.С)</td>
                        <td class="px-4 py-3 text-gray-700 leading-relaxed">
                            Игроки уровня перворазрядников и КМС, бывшие профессионалы, текущие профессиональные пляжники.
                            Команды уровня 1 и Высшей лиги ЛВЛ, КФК, высшей лиги МО.
                        </td>
                        <td class="px-4 py-3 font-bold text-gray-900">6</td>
                    </tr>

                    <tr class="align-top">
                        <td class="px-4 py-3 font-semibold text-gray-900 whitespace-nowrap">Профи (М.С.)</td>
                        <td class="px-4 py-3 text-gray-700 leading-relaxed">
                            Игрок состоит в проф. команде, его работа — тренироваться и играть за клуб.
                        </td>
                        <td class="px-4 py-3 font-bold text-gray-900">7</td>
                    </tr>

                    <tr class="align-top">
                        <td class="px-4 py-3 font-semibold text-gray-900 whitespace-nowrap">Уровень «БОГ»</td>
                        <td class="px-4 py-3 text-gray-700 leading-relaxed">
                            «Бог» — самоуверенный средний любитель, неадекватно оценивающий свой уровень.
                        </td>
                        <td class="px-4 py-3 font-bold text-gray-900">БАН</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- BEACH --}}
        <hr class="my-10">
        <h2 id="beach" class="text-xl sm:text-2xl font-bold text-gray-900">Пляжный волейбол</h2>

        <h3 class="mt-6 text-lg font-bold text-gray-900">Подростки</h3>
        <div class="mt-3 space-y-4">
            <div class="rounded-xl border border-gray-200 p-4">
                <div class="font-bold text-gray-900">Подростки — начальный <span class="text-sm text-gray-500 font-semibold">(0 баллов)</span></div>
                <div class="mt-2 text-gray-700 leading-relaxed">
                    Обучение с нуля… (передача сверху/снизу, техника удара, разбег, постановка рук и т.д.), теория и расстановка.
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 p-4">
                <div class="font-bold text-gray-900">Подростки — начальный + <span class="text-sm text-gray-500 font-semibold">(0 баллов)</span></div>
                <div class="mt-2 text-gray-700 leading-relaxed">
                    Понимание расстановки, где стоять, базовые навыки на начальной стадии + начальная физ. подготовка.
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 p-4">
                <div class="font-bold text-gray-900">Подростки — средний уровень <span class="text-sm text-gray-500 font-semibold">(0 баллов)</span></div>
                <div class="mt-2 text-gray-700 leading-relaxed">
                    Знание расстановки, схема 4/2, техническая стабильность (подача/атака/передача), хорошая физическая подготовка.
                </div>
            </div>
        </div>

        <h3 class="mt-8 text-lg font-bold text-gray-900">Взрослые</h3>

        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-sm border border-gray-200 rounded-xl overflow-hidden">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left px-4 py-3 font-bold text-gray-900">Уровень</th>
                        <th class="text-left px-4 py-3 font-bold text-gray-900">Описание</th>
                        <th class="text-left px-4 py-3 font-bold text-gray-900 whitespace-nowrap">Баллы</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr class="align-top">
                        <td class="px-4 py-3 font-semibold text-gray-900 whitespace-nowrap">Начальный</td>
                        <td class="px-4 py-3 text-gray-700 leading-relaxed">
                            Игрок только учится делать элементы: пас сверху/снизу, удар, подача, перемещения и стойки,
                            базовые расстановки. Игра ещё не вырисовывается, элементы нестабильны.
                            Цель — правильная техника в простых условиях.
                        </td>
                        <td class="px-4 py-3 font-bold text-gray-900">1</td>
                    </tr>

                    <tr class="align-top">
                        <td class="px-4 py-3 font-semibold text-gray-900 whitespace-nowrap">Начальный +</td>
                        <td class="px-4 py-3 text-gray-700 leading-relaxed">
                            Умеет базовые элементы: стабильно подаёт, умеет напрыжку и удар в прыжке, пас в зону атаки,
                            приём простых подач и несильных ударов.
                            Цель — применять технику в сложных условиях (приём ударов/отскоков, точные пасы, усиление подачи).
                        </td>
                        <td class="px-4 py-3 font-bold text-gray-900">2</td>
                    </tr>

                    <tr class="align-top">
                        <td class="px-4 py-3 font-semibold text-gray-900 whitespace-nowrap">Средний −</td>
                        <td class="px-4 py-3 text-gray-700 leading-relaxed">
                            Умеет атаковать/защищаться/пасовать/принимать, но есть ошибки в точности и качестве + не хватает физики
                            (прыжок, сила удара, «дотянуться» в защите, подача в прыжке).
                            Есть знания зон и расстановки; понимает знаки партнера, но не всегда может подсказать зону атаки напарнику.
                        </td>
                        <td class="px-4 py-3 font-bold text-gray-900">3</td>
                    </tr>

                    <tr class="align-top">
                        <td class="px-4 py-3 font-semibold text-gray-900 whitespace-nowrap">Средний</td>
                        <td class="px-4 py-3 text-gray-700 leading-relaxed">
                            Стабильная атака/защита, точные пасы и приёмы. Ошибка чаще возникает при сильной подаче/атаке соперника
                            или при риске на подаче/атаке. Цель — тактика: защита от ударов/скидок, обвод блока, игра «на подачах».
                        </td>
                        <td class="px-4 py-3 font-bold text-gray-900">4</td>
                    </tr>

                    <tr class="align-top">
                        <td class="px-4 py-3 font-semibold text-gray-900 whitespace-nowrap">Средний +</td>
                        <td class="px-4 py-3 text-gray-700 leading-relaxed">
                            Хорошая техника, упор на тактику: подачи вразрез, удары мимо блока, блок-аут, скидки,
                            блокирование (если позволяет высота), грамотный выбор позиции в защите.
                            Часто специализация по амплуа или универсал. Уровень 1–2 разряда; по ЛВЛ — выше 2 лиги.
                        </td>
                        <td class="px-4 py-3 font-bold text-gray-900">5</td>
                    </tr>

                    <tr class="align-top">
                        <td class="px-4 py-3 font-semibold text-gray-900 whitespace-nowrap">Полупрофи (К.М.С)</td>
                        <td class="px-4 py-3 text-gray-700 leading-relaxed">
                            1 разряд или КМС. Играет на крупных турнирах, высокий уровень техники и тактики,
                            но не состоит на постоянке в проф. клубе.
                        </td>
                        <td class="px-4 py-3 font-bold text-gray-900">6</td>
                    </tr>

                    <tr class="align-top">
                        <td class="px-4 py-3 font-semibold text-gray-900 whitespace-nowrap">Профи (М.С.)</td>
                        <td class="px-4 py-3 text-gray-700 leading-relaxed">
                            Игрок состоит в проф. команде, его работа — тренироваться и играть за клуб.
                        </td>
                        <td class="px-4 py-3 font-bold text-gray-900">7</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- RATING --}}
        <hr class="my-10">
        <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Расчёт коэффициента качества игроков на мероприятии</h2>

        <div class="mt-3 text-gray-700 leading-relaxed space-y-3">
            <p>
                В описании мероприятия вы будете видеть среднее значение уровня записавшихся игроков:
                <span class="font-semibold">«Уровень игроков: 4.25 из 7»</span>.
                Этот коэффициент сделан специально для вас — чтобы оценить динамику игры или тренировки.
            </p>

            <div class="rounded-xl border border-gray-200 p-4">
                <div class="font-bold text-gray-900">Как считается (если записались только мужчины)</div>
                <p class="mt-2">
                    Система складывает баллы и делит на количество записавшихся.
                </p>
                <p class="mt-2 text-sm text-gray-600">
                    Пример: 18 игроков: 14 × (4 балла), 3 × (5 баллов), 1 × (6 баллов)
                </p>
                <pre class="mt-2 p-3 bg-gray-50 rounded-lg text-sm overflow-x-auto">((4*14) + (5*3) + 6) / 18 = 4.28</pre>
            </div>

            <div class="rounded-xl border border-gray-200 p-4">
                <div class="font-bold text-gray-900">Если есть девушки (и девушек меньше, чем мужчин)</div>
                <p class="mt-2">
                    Формула другая: у девушек понижающий коэффициент (равен количеству записавшихся девушек).
                </p>
                <p class="mt-2 text-sm text-gray-600">
                    Пример: 18 игроков: 10 × (3 балла), 5 × (4 балла), 3 девушки × (4 балла)
                </p>
                <pre class="mt-2 p-3 bg-gray-50 rounded-lg text-sm overflow-x-auto">((3*10) + (4*5) + ((4*3) - 3)) / 18 = 3.28</pre>
                <p class="mt-2 text-sm text-gray-600">
                    Если девушек большинство — формула как в первом примере, без понижения.
                </p>
            </div>

            <p class="pt-2 font-semibold">С уважением, Your Volley Club!</p>
        </div>
    </div>
</div>
</x-app-layout>

