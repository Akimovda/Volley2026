<x-voll-layout body_class="rating-info-page">
<x-slot name="title">{{ __('players.rating_info_title') }}</x-slot>
<x-slot name="h1">{{ __('players.rating_info_title') }}</x-slot>
<x-slot name="t_description">{{ __('players.rating_info_subtitle') }}</x-slot>
<x-slot name="breadcrumbs">
    <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
        <a href="{{ route('players.rating') }}" itemprop="item"><span itemprop="name">{{ __('players.rating_title') }}</span></a>
        <meta itemprop="position" content="2">
    </li>
    <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
        <span itemprop="name">{{ __('players.rating_info_title') }}</span>
        <meta itemprop="position" content="3">
    </li>
</x-slot>
<x-slot name="d_description">
    <div class="d-flex gap-1 mt-2">
        <a href="{{ route('players.rating') }}" class="btn btn-secondary">← {{ __('players.rating_title') }}</a>
    </div>
</x-slot>

<div class="container">

    {{-- Три компонента --}}
    <div class="row row2 mb-3">
        <div class="col-md-4">
            <div class="ramka h-100">
                <div class="f-32 b-800" style="color:#E7612F">μ</div>
                <h3 class="f-18 b-700">{{ __('players.rating_info_mu_title') }}</h3>
                <p class="f-15" style="opacity:.8">{{ __('players.rating_info_mu_desc') }}</p>
                <div class="d-flex gap-2 mt-2 f-13">
                    <div class="card text-center p-2">
                        <div class="b-700">25.0</div>
                        <div style="opacity:.5">Старт</div>
                    </div>
                    <div class="card text-center p-2">
                        <div class="b-700 cs">30–35</div>
                        <div style="opacity:.5">Хороший игрок</div>
                    </div>
                    <div class="card text-center p-2">
                        <div class="b-700" style="color:#E7612F">40+</div>
                        <div style="opacity:.5">Элита</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="ramka h-100">
                <div class="f-32 b-800" style="color:#6366f1">σ</div>
                <h3 class="f-18 b-700">{{ __('players.rating_info_sigma_title') }}</h3>
                <p class="f-15" style="opacity:.8">{{ __('players.rating_info_sigma_desc') }}</p>
                <div class="d-flex gap-2 mt-2 f-13">
                    <div class="card text-center p-2">
                        <div class="b-700">8.33</div>
                        <div style="opacity:.5">0 игр</div>
                    </div>
                    <div class="card text-center p-2">
                        <div class="b-700">~6.0</div>
                        <div style="opacity:.5">20 игр</div>
                    </div>
                    <div class="card text-center p-2">
                        <div class="b-700 cs">~4.0</div>
                        <div style="opacity:.5">50+ игр</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="ramka h-100">
                <div class="f-32 b-800 cs">CR</div>
                <h3 class="f-18 b-700">{{ __('players.rating_info_cr_title') }}</h3>
                <p class="f-15" style="opacity:.8">{{ __('players.rating_info_cr_desc') }}</p>
                <div class="card p-2 mt-2 f-13 text-center b-600" style="background:rgba(231,97,47,.08)">
                    Рейтинг = μ − 3 × σ
                </div>
            </div>
        </div>
    </div>

    {{-- Таблица примеров --}}
    <div class="ramka">
        <h2 class="-mt-05">Примеры расчёта</h2>
        <div class="table-scrollable">
            <table class="table f-15">
                <thead>
                    <tr>
                        <th>Игрок</th>
                        <th>μ</th>
                        <th>σ</th>
                        <th class="b-600" style="color:#E7612F">Рейтинг</th>
                        <th>Пояснение</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Новичок (0 игр)</td>
                        <td>25.0</td>
                        <td>8.33</td>
                        <td class="b-700" style="color:#E7612F">≈ 0.01</td>
                        <td style="opacity:.7">Система не знает уровень</td>
                    </tr>
                    <tr>
                        <td>Начинающий (10 игр)</td>
                        <td>26.0</td>
                        <td>7.0</td>
                        <td class="b-700" style="color:#E7612F">5.0</td>
                        <td style="opacity:.7">Первые данные собраны</td>
                    </tr>
                    <tr>
                        <td>Средний (20 игр)</td>
                        <td>27.5</td>
                        <td>6.0</td>
                        <td class="b-700" style="color:#E7612F">9.5</td>
                        <td style="opacity:.7">Рейтинг набирает вес</td>
                    </tr>
                    <tr>
                        <td>Опытный (50+ игр)</td>
                        <td>30.2</td>
                        <td>4.0</td>
                        <td class="b-700" style="color:#E7612F">18.2</td>
                        <td style="opacity:.7">Рейтинг стабилен</td>
                    </tr>
                    <tr>
                        <td>Топ-игрок (100+ игр)</td>
                        <td>34.5</td>
                        <td>4.0</td>
                        <td class="b-700" style="color:#E7612F">22.5</td>
                        <td style="opacity:.7">Элитный уровень</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="alert alert-info mt-2">
            💡 <strong>{{ __('players.rating_info_tip') }}</strong> —
            в первых играх σ уменьшается быстро, и рейтинг значительно меняется.
            После 30 игр система хорошо знает ваш уровень.
        </div>
    </div>

    {{-- Почему лучше WinRate --}}
    <div class="ramka">
        <h2 class="-mt-05">Почему OpenSkill лучше WinRate?</h2>
        <div class="row row2">
            <div class="col-md-6">
                <div class="card">
                    <div class="f-13 b-600 mb-2" style="color:#dc3545">❌ Проблема WinRate</div>
                    <p class="f-14">Игрок с <strong>3 победами из 3</strong> и игрок с <strong>300 победами из 300</strong>
                    оба имеют WinRate = 100%. В таблице они стоят рядом.</p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="f-13 b-600 mb-2 cs">✅ Решение OpenSkill</div>
                    <p class="f-14">3/3 → Рейтинг ≈ 6.0 (σ ещё высокая, система не уверена)<br>
                    300/300 → Рейтинг ≈ 22+ (σ низкая, система уверена в уровне)</p>
                </div>
            </div>
        </div>
        <div class="row row2 mt-2">
            <div class="col-md-6">
                <div class="card">
                    <div class="f-13 b-600 mb-1">🎯 Учёт силы соперника</div>
                    <p class="f-14" style="opacity:.8">Победа над сильным соперником (высокий μ) даёт больший прирост рейтинга,
                    чем победа над слабым.</p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="f-13 b-600 mb-1">🏐 Командные игры</div>
                    <p class="f-14" style="opacity:.8">В пляжной паре (2x2) каждый игрок получает одинаковый прирост.
                    В классике (6 игроков) вклад пропорционален σ каждого.</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Форматы --}}
    <div class="ramka">
        <h2 class="-mt-05">{{ __('players.rating_info_formats') }}</h2>
        <p class="f-15" style="opacity:.8">{{ __('players.rating_info_formats_desc') }}</p>
        <div class="row row2">
            <div class="col-md-6">
                <div class="card">
                    <div class="f-14 b-600 mb-2">🏖 Пляжный волейбол</div>
                    <div class="d-flex gap-1 flex-wrap">
                        @foreach(['2x2 — пары','3x3 — тройки','4x4 — четвёрки'] as $s)
                        <span class="f-13 b-600 px-2 py-1" style="background:rgba(59,130,246,.1);border-radius:4px">{{ $s }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="f-14 b-600 mb-2">🏐 Классический волейбол</div>
                    <div class="d-flex gap-1 flex-wrap">
                        @foreach(['4x4 — мини','4x2 — 6 игр.','5x1 — 6 игр.','5x1+либеро — 7 игр.'] as $s)
                        <span class="f-13 b-600 px-2 py-1" style="background:rgba(16,185,129,.1);border-radius:4px">{{ $s }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="text-center mt-3 mb-3">
        <a href="{{ route('players.rating') }}" class="btn">{{ __('players.rating_title') }} →</a>
    </div>

</div>
</x-voll-layout>
