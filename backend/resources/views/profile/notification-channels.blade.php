{{-- resources/views/profile/notification-channels.blade.php --}}
<x-voll-layout body_class="profile-page">

    <x-slot name="title">{{ __('profile.nch_title') }}</x-slot>
    <x-slot name="h1">{{ __('profile.nch_title') }}</x-slot>
    <x-slot name="h2">{{ __('profile.nch_h2') }}</x-slot>
    <x-slot name="t_description">{{ __('profile.nch_t_description') }}</x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('profile.show') }}" itemprop="item"><span itemprop="name">{{ __('profile.nch_breadcrumb') }}</span></a>
            <meta itemprop="position" content="2">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">{{ __('profile.nch_breadcrumb_self') }}</span>
            <meta itemprop="position" content="3">
        </li>
    </x-slot>

    <div class="container">

        {{-- ======= ИНСТРУКЦИЯ ======= --}}
        <div class="ramka">
            <div class="alert alert-info mb-2">
                <div class="alert-title b-700 f-17">{{ __('profile.nch_help_title') }}</div>
                <p class="mt-1 mb-0">
                    Это ваш чат или канал в <strong>Telegram</strong> / <strong>MAX</strong>,
                    куда наш бот автоматически отправляет анонсы ваших мероприятий.
                    Для <strong>ВКонтакте</strong> анонсы публикуются прямо на стене вашего сообщества —
                    через ключ доступа (см. блок ниже).
                </p>
            </div>

            <h3 class="mt-0">{{ __('profile.nch_howto_h3') }}</h3>

            <div class="row">
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="f-30 text-center mb-05">1️⃣</div>
                        <div class="b-700 f-16 text-center mb-05">{{ __('profile.nch_step1_title') }}</div>
                        <p class="f-14 mb-0">
                            В блоке <strong>«Подключить канал»</strong> выберите платформу
                            (Telegram или MAX), придумайте название — и нажмите кнопку.
                            Сервис сгенерирует уникальную ссылку-приглашение.
                        </p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="f-30 text-center mb-05">2️⃣</div>
                        <div class="b-700 f-16 text-center mb-05">{{ __('profile.nch_step2_title') }}</div>
                        <p class="f-14 mb-0">
                            Нажмите кнопку «Открыть» — откроется мессенджер.
                            Выберите свою группу или канал и добавьте бота
                            <strong>с правами администратора</strong>.
                        </p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="f-30 text-center mb-05">3️⃣</div>
                        <div class="b-700 f-16 text-center mb-05">{{ __('profile.nch_step3_title') }}</div>
                        <p class="f-14 mb-0">
                            Бот автоматически отметит канал как подтверждённый.
                            Обновите страницу — увидите его в списке с бейджем
                            <span class="badge badge-green">подтверждён</span>.
                        </p>
                    </div>
                </div>
            </div>

            {{-- Пошаговки для Telegram и MAX --}}
            <h3>{{ __('profile.nch_platforms_h3') }}</h3>

            <div class="tabs-content">
                <div class="tabs">
                    <div class="tab active" data-tab="howto-tg">✈️ Telegram</div>
                    <div class="tab" data-tab="howto-max">💬 MAX</div>
                    <div class="tab-highlight"></div>
                </div>
                <div class="tab-panes">

                    <div class="tab-pane active" id="howto-tg">
                        @php $tgBot = '@' . config('services.telegram.bot_username', 'VolleyEvents_bot'); @endphp
                        <p class="f-14 mb-1">
                            <strong>Наш бот:</strong>
                            <a href="https://t.me/{{ ltrim($tgBot, '@') }}" target="_blank" rel="noopener" class="link b-700">{{ $tgBot }}</a>
                        </p>

                        <div class="b-700 f-15 mt-1 mb-05">Способ 1 — для группы / супергруппы (быстрый)</div>
                        <ol class="list">
                            <li>Создайте или откройте свою <strong>группу / супергруппу</strong> в Telegram.</li>
                            <li>В блоке ниже выберите <strong>Telegram</strong>, укажите название и нажмите <strong>«Создать ссылку привязки»</strong>.</li>
                            <li>Нажмите кнопку <strong>«Подключить Telegram»</strong> — откроется список ваших чатов.</li>
                            <li>Выберите нужную группу — бот добавится автоматически.</li>
                            <li>⚠️ Дайте боту права <strong>администратора</strong> — иначе он не сможет публиковать сообщения.</li>
                            <li>Бот пришлёт подтверждение в чат. Обновите эту страницу.</li>
                        </ol>

                        <div class="b-700 f-15 mt-2 mb-05">Способ 2 — для канала (ручное добавление)</div>
                        <p class="f-13 mb-1" style="opacity:.8">
                            Telegram часто <strong>не показывает каналы</strong> в списке выбора при нажатии на ссылку привязки —
                            это особенность Telegram. Используйте этот способ:
                        </p>
                        <ol class="list">
                            <li>Откройте свой <strong>канал</strong> → ⚙️ Управление каналом → <strong>Администраторы</strong> → «Добавить администратора».</li>
                            <li>Найдите бота по имени <strong>{{ $tgBot }}</strong> и добавьте его. Обязательно включите право <strong>«Публикация сообщений»</strong>.</li>
                            <li>В блоке ниже выберите <strong>Telegram</strong>, укажите название и нажмите <strong>«Создать ссылку привязки»</strong>.</li>
                            <li>Скопируйте из созданной ссылки часть <code>bind_…</code> (после <code>=</code>) — это ваш токен привязки.</li>
                            <li>В <strong>самом канале</strong> опубликуйте сообщение: <code>/start bind_ВАШ_ТОКЕН</code>.</li>
                            <li>Бот ответит «✅ Telegram-чат подключён». Обновите эту страницу.</li>
                        </ol>
                    </div>

                    <div class="tab-pane" id="howto-max">
                        <ol class="list">
                            <li>Создайте или откройте свой <strong>чат в MAX</strong>.</li>
                            <li>Добавьте в чат нашего MAX-бота <strong>с правами администратора</strong>.</li>
                            <li>В блоке ниже выберите <strong>MAX</strong> и нажмите <strong>«Создать ссылку привязки»</strong>.</li>
                            <li>Нажмите <strong>«Открыть MAX»</strong> — откроется бот с уже переданным токеном.</li>
                            <li>Бот покажет список чатов — выберите нужный.</li>
                            <li>Готово! Обновите страницу.</li>
                        </ol>
                    </div>

                </div>
            </div>

            <div class="alert alert-warning mt-2 mb-0">
                <strong>💡 Совет:</strong> можно подключить <strong>несколько каналов</strong> на разных платформах —
                анонсы дублируются во все подтверждённые каналы одновременно.
                Ссылка привязки действительна <strong>30 минут</strong>.
            </div>
        </div>

        {{-- Flash --}}
        @if(session('status'))
            <div class="ramka"><div class="alert alert-success">{{ session('status') }}</div></div>
        @endif
        @if(session('error'))
            <div class="ramka"><div class="alert alert-error">{{ session('error') }}</div></div>
        @endif

        @php $bindInstruction = session('bind_instruction'); @endphp

        @if(!empty($bindInstruction))
        <div class="ramka" id="bind-result">
            <div class="alert alert-success">
                <div class="alert-title b-700 f-17">
                    ✅ Ссылка для привязки {{ strtoupper($bindInstruction['platform'] ?? '') }} создана
                </div>
                <p class="mt-1">{{ $bindInstruction['message'] ?? '' }}</p>

                <div class="b-700 f-16 mt-2 mb-05">📋 Что делать дальше:</div>
                @if(!empty($bindInstruction['instruction']))
                    <div class="f-15 pre-line">{{ $bindInstruction['instruction'] }}</div>
                @endif

                @if(!empty($bindInstruction['link']))
                    <div class="mt-2">
                        <a href="{{ $bindInstruction['link'] }}" target="_blank" rel="noopener"
                           class="btn btn-primary">
                            👉 {{ $bindInstruction['button_text'] ?? 'Открыть' }}
                        </a>
                    </div>
                @endif

                @if(($bindInstruction['platform'] ?? '') !== 'max' && !empty($bindInstruction['command']))
                    <div class="mt-2">
                        <div class="f-14 b-600 mb-05">Команда для отправки боту:</div>
                        <code class="code-block">{{ $bindInstruction['command'] }}</code>
                    </div>
                @endif

                <div class="f-13 text-muted mt-2">
                    ⏱ Ссылка действительна 30 минут. После добавления бота в чат — обновите страницу.
                </div>
            </div>
        </div>
        @endif

        <div class="row row2">

            {{-- Левая колонка: подключить системный канал (Telegram / MAX) --}}
            <div class="col-lg-6">
                <div class="ramka">
                    <h3 class="mt-0">📡 Подключить канал</h3>
                    <p class="f-15 text-muted">Системный бот отправляет анонсы в ваш чат или канал.</p>

                    <form method="POST" action="{{ route('profile.notification_channels.bind') }}" class="form">
                        @csrf
                        <div class="card">
                            <label class="f-15 b-600 mb-05">Платформа</label>
                            <select name="platform" class="w-100">
                                <option value="telegram" {{ old('platform','telegram')==='telegram'?'selected':'' }}>✈️ Telegram</option>
                                <option value="max"      {{ old('platform')==='max'?'selected':'' }}>💬 MAX</option>
                            </select>
                            @error('platform')<div class="red f-14 mt-05">{{ $message }}</div>@enderror
                        </div>

                        <div class="card">
                            <label class="f-15 b-600 mb-05">Название канала</label>
                            <input type="text" name="title" value="{{ old('title') }}"
                                   placeholder="Например: Основной Telegram канал" class="w-100">
                            @error('title')<div class="red f-14 mt-05">{{ $message }}</div>@enderror
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            Создать ссылку привязки
                        </button>
                    </form>
                </div>
            </div>

            {{-- Правая колонка: подключённые каналы --}}
            <div class="col-lg-6">
                <div class="ramka">
                    <h3 class="mt-0">✅ Подключённые каналы</h3>
                    <p class="f-15 text-muted">Только подтверждённые каналы доступны для анонсов.</p>

                    @if(($channels ?? collect())->isEmpty())
                        <div class="card text-muted f-15">Пока нет подключённых каналов.</div>
                    @else
                        @foreach($channels as $channel)
                        @php
                            $kind = $channel->meta['kind'] ?? '';
                            $isWall = $kind === 'vk_wall';
                            $isCommunity = $kind === 'vk_community';
                            $isVkOwned = $isWall || $isCommunity;
                        @endphp
                        <div class="card mb-1">
                            <div class="d-flex between">
                                <div>
                                    @if($isCommunity)
                                        <span class="b-700 f-16">🔵 VK сообщество</span>
                                    @elseif($isWall)
                                        <span class="b-700 f-16">🔵 VK стена</span>
                                    @else
                                        <span class="b-700 f-16">{{ strtoupper($channel->platform) }}</span>
                                    @endif
                                    @if($channel->is_verified)
                                        <span class="badge badge-green ml-05">подтверждён</span>
                                    @else
                                        <span class="badge badge-yellow ml-05">ожидает</span>
                                    @endif
                                    @if(($channel->bot_type ?? 'system') === 'user')
                                        <span class="badge badge-orange ml-05">🤖 свой бот{{ $channel->user_bot_username ? ' @'.$channel->user_bot_username : '' }}</span>
                                    @elseif(!$isVkOwned)
                                        <span class="badge ml-05">системный</span>
                                    @endif
                                </div>
                                <div class="d-flex gap-05">
                                    @if(($channel->bot_type ?? 'system') === 'user')
                                        <button onclick="document.getElementById('upd-{{ $channel->id }}').classList.toggle('d-none')"
                                                class="btn btn-small btn-secondary">🔑</button>
                                    @endif
                                    <form method="POST"
                                          action="{{ route('profile.notification_channels.destroy', $channel) }}"
                                          onsubmit="return confirm('Удалить канал?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-small btn-danger">✕</button>
                                    </form>
                                </div>
                            </div>

                            <div class="f-15 b-600 mt-05">{{ $channel->title ?: 'Без названия' }}</div>
                            @if($isCommunity)
                                @if(!empty($channel->meta['group_screen_name']))
                                    <div class="f-13 text-muted">
                                        Сообщество:
                                        <a href="https://vk.com/{{ $channel->meta['group_screen_name'] }}" target="_blank" rel="noopener">
                                            {{ $channel->meta['group_name'] ?? $channel->meta['group_screen_name'] }}
                                        </a>
                                    </div>
                                @elseif(!empty($channel->meta['group_name']))
                                    <div class="f-13 text-muted">Сообщество: {{ $channel->meta['group_name'] }}</div>
                                @endif
                                @if(!$channel->is_verified)
                                    <div class="f-13 red mt-05">⚠️ Ключ недействителен — привяжите сообщество заново</div>
                                @endif
                            @elseif($isWall && !empty($channel->meta['group_name']))
                                <div class="f-13 text-muted">Сообщество: {{ $channel->meta['group_name'] }}</div>
                            @else
                                <div class="f-13 text-muted">chat_id: {{ $channel->chat_id }}</div>
                            @endif
                            @if(!empty($channel->verified_at))
                                <div class="f-13 text-muted">Подтверждён: {{ $channel->verified_at->format('d.m.Y H:i') }}</div>
                            @endif

                            @if(($channel->bot_type ?? 'system') === 'user')
                            <div id="upd-{{ $channel->id }}" class="d-none mt-1">
                                <form method="POST"
                                      action="{{ route('profile.personal_bot.update_token', $channel) }}"
                                      class="d-flex gap-05">
                                    @csrf @method('PATCH')
                                    <input type="text" name="bot_token" placeholder="Новый токен"
                                           class="flex-1 f-14" style="font-family:monospace">
                                    <button type="submit" class="btn btn-small btn-primary">Обновить</button>
                                </form>
                            </div>
                            @endif
                        </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>

        {{-- VK Community (прямой токен) --}}
        <div class="ramka">
            <h3 class="mt-0">🔵 Привязать VK-сообщество</h3>
            <p class="f-15 mb-2">
                Анонсы будут публиковаться на стене вашего VK-сообщества.
                Бот не нужен — используется ключ доступа, который вы создаёте в настройках своего сообщества.
            </p>

            <div class="alert alert-info mb-2">
                <div class="b-700 f-15 mb-1">Как создать ключ доступа — 5 шагов</div>
                <ol class="list mb-0">
                    <li>Откройте ваше сообщество ВКонтакте → <strong>Управление</strong> → <strong>Работа с API</strong></li>
                    <li>Нажмите <strong>«Создать ключ»</strong></li>
                    <li>Отметьте права доступа:
                        <ul>
                            <li>Разрешить приложению доступ к управлению сообществом</li>
                            <li>Разрешить приложению доступ к стене сообщества</li>
                        </ul>
                    </li>
                    <li>Нажмите <strong>«Создать»</strong> и скопируйте полученный ключ</li>
                    <li>Вставьте адрес сообщества и ключ в поля ниже, нажмите <strong>«Привязать»</strong></li>
                </ol>
                <div class="f-13 text-muted mt-1">
                    ⚠️ Ключ хранится в зашифрованном виде и используется только для публикации анонсов.
                </div>
            </div>

            <form method="POST" action="{{ route('integrations.vk_community.bind') }}" class="form">
                @csrf
                <div class="card">
                    <label class="f-15 b-600 mb-05">Адрес сообщества VK <span class="red">*</span></label>
                    <input type="text" name="group_slug" value="{{ old('group_slug') }}"
                           placeholder="club12345678 или msk_volley или vk.com/club12345678"
                           class="w-100" required>
                    <div class="f-13 text-muted mt-05">Адрес из браузера или короткое имя сообщества</div>
                    @error('group_slug')<div class="red f-14 mt-05">{{ $message }}</div>@enderror
                </div>
                <div class="card">
                    <label class="f-15 b-600 mb-05">Ключ доступа сообщества VK <span class="red">*</span></label>
                    <input type="password" name="token" value=""
                           placeholder="Вставьте ключ доступа из «Управление → Работа с API»"
                           class="w-100" required style="font-family:monospace">
                    @error('token')<div class="red f-14 mt-05">{{ $message }}</div>@enderror
                </div>
                <button type="submit" class="btn btn-primary">
                    🔵 Привязать VK-сообщество
                </button>
            </form>
        </div>

        {{-- Персональный бот --}}
        <div class="ramka">
            <h3 class="mt-0">🤖 Организатор Pro — Свой бот</h3>
            <p class="f-15 text-muted">Анонсы от вашего персонального бота — брендинг и независимость от системного бота.</p>

            @if(!($isPro ?? false))
            <div class="card text-center" style="padding:2.5rem 2rem">
                <div style="font-size:4rem;margin-bottom:1rem">🔒</div>
                <div class="b-600 f-16 mb-1">Доступно в Организатор Pro</div>
                <div class="f-15 mb-2" style="opacity:.7">Подключите подписку чтобы использовать персонального бота для анонсов.</div>
                <a href="{{ route('organizer_pro.index') }}" class="btn">⭐ Подключить Организатор Pro</a>
            </div>
            @else
            <div class="row row2" id="personal-bot-tabs-wrap">
                {{-- Вкладки --}}
                <div class="col-12 mb-1">
                    <div class="d-flex gap-05">
                        <button onclick="personalBotTab('telegram')" id="tab-btn-telegram"
                                class="btn btn-primary btn-small">✈️ Telegram</button>
                        <button onclick="personalBotTab('max')" id="tab-btn-max"
                                class="btn btn-secondary btn-small">💬 MAX</button>
                    </div>
                </div>

                {{-- Telegram --}}
                <div id="personal-bot-tab-telegram" class="col-lg-8">
                    <div class="alert alert-info mb-1">
                        <strong>Инструкция:</strong> создайте бота через
                        <a href="https://t.me/BotFather" target="_blank">@BotFather</a>,
                        добавьте в канал/группу как администратора, вставьте токен и ID чата.
                    </div>
                    <form method="POST" action="{{ route('profile.personal_bot.telegram') }}" class="form">
                        @csrf
                        <div class="card">
                            <label class="f-15 b-600 mb-05">Токен бота <span class="red">*</span></label>
                            <input type="text" name="bot_token" value="{{ old('bot_token') }}"
                                   placeholder="123456789:AAF..." class="w-100"
                                   style="font-family:monospace">
                            @error('bot_token')<div class="red f-14 mt-05">{{ $message }}</div>@enderror
                        </div>
                        <div class="card">
                            <label class="f-15 b-600 mb-05">ID чата / канала <span class="red">*</span></label>
                            <input type="text" name="chat_id" value="{{ old('chat_id') }}"
                                   placeholder="-1001234567890" class="w-100"
                                   style="font-family:monospace">
                            <div class="f-13 text-muted mt-05">Для канала/группы ID начинается с -100. Узнать через @userinfobot.</div>
                            @error('chat_id')<div class="red f-14 mt-05">{{ $message }}</div>@enderror
                        </div>
                        <div class="card">
                            <label class="f-15 b-600 mb-05">Название канала</label>
                            <input type="text" name="title" value="{{ old('title') }}"
                                   placeholder="Мой волейбольный канал" class="w-100">
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            Подключить Telegram-бота
                        </button>
                    </form>
                </div>

                {{-- MAX --}}
                <div id="personal-bot-tab-max" class="col-lg-8" style="display:none">
                    <div class="alert alert-info mb-1">
                        <strong>Инструкция:</strong> создайте бота в MAX, добавьте в чат как администратора,
                        скопируйте токен и ID чата.
                    </div>
                    <form method="POST" action="{{ route('profile.personal_bot.max') }}" class="form">
                        @csrf
                        <div class="card">
                            <label class="f-15 b-600 mb-05">Токен бота MAX <span class="red">*</span></label>
                            <input type="text" name="bot_token" value="{{ old('bot_token') }}"
                                   placeholder="Токен от платформы MAX" class="w-100"
                                   style="font-family:monospace">
                            @error('bot_token')<div class="red f-14 mt-05">{{ $message }}</div>@enderror
                        </div>
                        <div class="card">
                            <label class="f-15 b-600 mb-05">ID чата <span class="red">*</span></label>
                            <input type="text" name="chat_id" value="{{ old('chat_id') }}"
                                   placeholder="ID чата в MAX" class="w-100"
                                   style="font-family:monospace">
                            @error('chat_id')<div class="red f-14 mt-05">{{ $message }}</div>@enderror
                        </div>
                        <div class="card">
                            <label class="f-15 b-600 mb-05">Название</label>
                            <input type="text" name="title" value="{{ old('title') }}"
                                   placeholder="Мой MAX чат" class="w-100">
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            Подключить MAX-бота
                        </button>
                    </form>
                </div>
            </div>
            @endif
        </div>

        {{-- Последние запросы привязки --}}
        <div class="ramka">
            <h3 class="mt-0">🔗 Последние запросы привязки</h3>
            <div class="table-scrollable">
                <div class="table-drag-indicator"></div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Платформа</th>
                            <th>Статус</th>
                            <th>Истекает</th>
                            <th>Токен</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse(($bindRequests ?? collect()) as $bind)
                        <tr>
                            <td class="b-700">#{{ $bind->id }}</td>
                            <td>{{ strtoupper($bind->platform) }}</td>
                            <td><span class="badge">{{ $bind->status }}</span></td>
                            <td class="f-14">{{ $bind->expires_at?->format('d.m.Y H:i') ?? '—' }}</td>
                            <td><code class="f-13">{{ $bind->token }}</code></td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-muted f-14">Запросов нет.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <x-slot name="script">
    <script>
    function personalBotTab(tab) {
        ['telegram','max'].forEach(function(t) {
            var panel = document.getElementById('personal-bot-tab-' + t);
            var btn   = document.getElementById('tab-btn-' + t);
            if (panel) panel.style.display = (t === tab) ? '' : 'none';
            if (btn) {
                btn.className = (t === tab)
                    ? 'btn btn-primary btn-small'
                    : 'btn btn-secondary btn-small';
            }
        });
    }

    (function() {
        var target = document.getElementById('bind-result');
        if (!target) return;
        // Плавный скролл к блоку с инструкцией + кратковременная подсветка
        setTimeout(function() {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            target.style.transition = 'box-shadow .4s ease';
            target.style.boxShadow = '0 0 0 3px rgba(40,167,69,.45)';
            setTimeout(function() { target.style.boxShadow = ''; }, 2200);
        }, 100);
    })();
    </script>
    </x-slot>

</x-voll-layout>
