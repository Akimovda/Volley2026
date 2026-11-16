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
        <a href="{{ route('players.rating') }}" class="btn btn-secondary"><x-ico name="arrow-left" /> {{ __('players.rating_title') }}</a>
    </div>
</x-slot>

<div class="container">

    {{-- Формула рейтинга --}}
    <div class="ramka ri-hero">
        <div class="ri-formula" role="img" aria-label="Рейтинг = μ − 3 × σ">
            <span class="ri-chip ri-chip--mu"><b>Рейтинг</b><small>консервативная оценка</small></span>
            <span class="ri-op">=</span>
            <span class="ri-chip ri-chip--mu"><b>μ</b><small>уровень мастерства</small></span>
            <span class="ri-op">−&nbsp;3&nbsp;×</span>
            <span class="ri-chip ri-chip--sigma"><b>σ</b><small>точность оценки</small></span>
        </div>
    </div>

    {{-- Три компонента --}}
    <div class="ri-grid ri-grid--3 mb-3">
        <div class="ri-cell">
            <div class="ramka ri-card">
                <div class="ri-badge ri-badge--mu">μ</div>
                <h3 class="f-18 b-700">{{ __('players.rating_info_mu_title') }}</h3>
                <p class="f-15" style="opacity:.8">{{ __('players.rating_info_mu_desc') }}</p>
                <div class="ri-stats">
                    <div class="ri-stat"><b>25.0</b><span>Старт</span></div>
                    <div class="ri-stat"><b class="cs">30–35</b><span>Хороший игрок</span></div>
                    <div class="ri-stat"><b style="color:#E7612F">40+</b><span>Элита</span></div>
                </div>
            </div>
        </div>
        <div class="ri-cell">
            <div class="ramka ri-card">
                <div class="ri-badge ri-badge--sigma">σ</div>
                <h3 class="f-18 b-700">{{ __('players.rating_info_sigma_title') }}</h3>
                <p class="f-15" style="opacity:.8">{{ __('players.rating_info_sigma_desc') }}</p>
                <div class="ri-stats">
                    <div class="ri-stat"><b>8.33</b><span>0 игр</span></div>
                    <div class="ri-stat"><b>~6.0</b><span>20 игр</span></div>
                    <div class="ri-stat"><b class="cs">~4.0</b><span>50+ игр</span></div>
                </div>
            </div>
        </div>
        <div class="ri-cell">
            <div class="ramka ri-card">
                <div class="ri-badge ri-badge--cr"><x-ico name="trend" size="2.4rem" color="#ffffff" /></div>
                <h3 class="f-18 b-700">{{ __('players.rating_info_cr_title') }}</h3>
                <p class="f-15" style="opacity:.8">{{ __('players.rating_info_cr_desc') }}</p>
                <div class="ri-formula-mini">Рейтинг = μ − 3 × σ</div>
            </div>
        </div>
    </div>

    {{-- Таблица примеров --}}
    <div class="ramka">
        <h2 class="-mt-05 h2-ico"><x-ico name="chart" size="1.2em" /> Примеры расчёта</h2>
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
                    @foreach([
                        ['Новичок (0 игр)', '25.0', '8.33', 0.01, '≈ 0.01', 'Система не знает уровень'],
                        ['Начинающий (10 игр)', '26.0', '7.0', 5.0, '5.0', 'Первые данные собраны'],
                        ['Средний (20 игр)', '27.5', '6.0', 9.5, '9.5', 'Рейтинг набирает вес'],
                        ['Опытный (50+ игр)', '30.2', '4.0', 18.2, '18.2', 'Рейтинг стабилен'],
                        ['Топ-игрок (100+ игр)', '34.5', '4.0', 22.5, '22.5', 'Элитный уровень'],
                    ] as [$who, $mu, $sg, $val, $label, $note])
                    <tr>
                        <td class="b-600">{{ $who }}</td>
                        <td>{{ $mu }}</td>
                        <td>{{ $sg }}</td>
                        <td>
                            <div class="b-700" style="color:#E7612F">{{ $label }}</div>
                            <div class="ri-bar"><span style="width: {{ min(100, max(2, round($val / 25 * 100))) }}%"></span></div>
                        </td>
                        <td style="opacity:.7">{{ $note }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="ri-tip mt-2">
            <span class="ri-tip__ico"><x-ico name="bulb" size="2.2rem" color="#b45309" /></span>
            <div><strong>{{ __('players.rating_info_tip') }}</strong> —
            в первых играх σ уменьшается быстро, и рейтинг значительно меняется.
            После 30 игр система хорошо знает ваш уровень.</div>
        </div>
    </div>

    {{-- Почему лучше WinRate --}}
    <div class="ramka">
        <h2 class="-mt-05 h2-ico"><x-ico name="activity" size="1.2em" /> Почему OpenSkill лучше WinRate?</h2>
        <div class="ri-grid ri-grid--2">
            <div class="ri-cell">
                <div class="ri-compare ri-compare--bad">
                    <div class="ri-compare__head"><x-ico name="x" size="2rem" color="#dc2626" /> Проблема WinRate</div>
                    <p class="f-14">Игрок с <strong>3 победами из 3</strong> и игрок с <strong>300 победами из 300</strong>
                    оба имеют WinRate = 100%. В таблице они стоят рядом.</p>
                </div>
            </div>
            <div class="ri-cell">
                <div class="ri-compare ri-compare--good">
                    <div class="ri-compare__head"><x-ico name="check" size="2rem" color="#16a34a" /> Решение OpenSkill</div>
                    <p class="f-14">3/3 → Рейтинг ≈ 6.0 (σ ещё высокая, система не уверена)<br>
                    300/300 → Рейтинг ≈ 22+ (σ низкая, система уверена в уровне)</p>
                </div>
            </div>
        </div>
        <div class="ri-grid ri-grid--2 mt-2">
            <div class="ri-cell">
                <div class="ri-feature">
                    <span class="ri-feature__ico" style="background:rgba(37,99,235,.12)"><x-ico name="trend" size="2.4rem" /></span>
                    <div>
                        <div class="b-600 mb-05">Учёт силы соперника</div>
                        <p class="f-14" style="opacity:.8">Победа над сильным соперником (высокий μ) даёт больший прирост рейтинга,
                        чем победа над слабым.</p>
                    </div>
                </div>
            </div>
            <div class="ri-cell">
                <div class="ri-feature">
                    <span class="ri-feature__ico" style="background:rgba(124,58,237,.12)"><x-ico name="users" size="2.4rem" color="#7c3aed" /></span>
                    <div>
                        <div class="b-600 mb-05">Командные игры</div>
                        <p class="f-14" style="opacity:.8">В пляжной паре (2x2) каждый игрок получает одинаковый прирост.
                        В классике (6 игроков) вклад пропорционален σ каждого.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Форматы --}}
    <div class="ramka">
        <h2 class="-mt-05 h2-ico"><x-ico name="layers" size="1.2em" /> {{ __('players.rating_info_formats') }}</h2>
        <p class="f-15" style="opacity:.8">{{ __('players.rating_info_formats_desc') }}</p>
        <div class="ri-grid ri-grid--2">
            <div class="ri-cell">
                <div class="ri-format ri-format--beach">
                    <div class="ri-format__head"><x-ico name="beach" size="2.8rem" /> Пляжный волейбол</div>
                    <div class="scheme-pills">
                        @foreach(['2x2 — пары','3x3 — тройки','4x4 — четвёрки'] as $s)<span class="scheme-pill scheme-pill--beach">{{ $s }}</span>@endforeach
                    </div>
                </div>
            </div>
            <div class="ri-cell">
                <div class="ri-format ri-format--classic">
                    <div class="ri-format__head"><x-ico name="ball" size="2.6rem" /> Классический волейбол</div>
                    <div class="scheme-pills">
                        @foreach(['4×4 — мини','4-2 — 6 игр.','5-1 — 6 игр.','5-1 с либеро — 7 игр.'] as $s)<span class="scheme-pill scheme-pill--classic">{{ $s }}</span>@endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="text-center mt-3 mb-3">
        <a href="{{ route('players.rating') }}" class="btn">{{ __('players.rating_title') }} <x-ico name="arrow-right" color="#ffffff" /></a>
    </div>

</div>
</x-voll-layout>
