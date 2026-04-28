{{-- resources/views/profile/notification-channels.blade.php --}}
<x-voll-layout body_class="profile-page">

    <x-slot name="title">Каналы уведомлений</x-slot>
    <x-slot name="h1">Каналы уведомлений</x-slot>
    <x-slot name="h2">Подключённые каналы для анонсов</x-slot>
    <x-slot name="t_description">Telegram, VK и MAX каналы для рассылки анонсов мероприятий.</x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('profile.show') }}" itemprop="item"><span itemprop="name">Профиль</span></a>
            <meta itemprop="position" content="2">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">Каналы уведомлений</span>
            <meta itemprop="position" content="3">
        </li>
    </x-slot>

    <div class="container">

        {{-- ======= ИНСТРУКЦИЯ ======= --}}
        <div class="ramka">
            <div class="alert alert-info mb-2">
                <div class="alert-title b-700 f-17">❓ Что такое каналы уведомлений и зачем они нужны?</div>
                <p class="mt-1 mb-0">
                    <strong>Канал уведомлений</strong> — это ваш чат, группа или канал
                    в <strong>Telegram</strong>, <strong>VK</strong> или <strong>MAX</strong>,
                    куда наш бот будет автоматически отправлять анонсы ваших мероприятий:
                    когда вы создаёте игру, когда появляются свободные места, напоминания и т.&nbsp;д.
                </p>
            </div>

            <h3 class="mt-0">📋 Как это работает — 3 шага</h3>

            <div class="row">
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="f-30 text-center mb-05">1️⃣</div>
                        <div class="b-700 f-16 text-center mb-05">Создаёте ссылку привязки</div>
                        <p class="f-14 mb-0">
                            Ниже в блоке <strong>«Подключить канал»</strong> выбираете платформу
                            (Telegram / VK / MAX), придумываете название — и жмёте кнопку.
                            Сервис сгенерирует уникальную ссылку-приглашение для бота.
                        </p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="f-30 text-center mb-05">2️⃣</div>
                        <div class="b-700 f-16 text-center mb-05">Добавляете бота в чат</div>
                        <p class="f-14 mb-0">
                            Нажимаете на кнопку из инструкции — открывается мессенджер.
                            Выбираете свою группу или канал и добавляете туда бота
                            <strong>с правами администратора</strong>
                            (чтобы он мог писать сообщения).
                        </p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="f-30 text-center mb-05">3️⃣</div>
                        <div class="b-700 f-16 text-center mb-05">Канал подтверждён</div>
                        <p class="f-14 mb-0">
                            Бот автоматически отметит канал как подтверждённый.
                            Обновите страницу — увидите его в списке справа с зелёным бейджем
                            <span class="badge badge-green">подтверждён</span>.
                            Теперь можно использовать его для анонсов.
                        </p>
                    </div>
                </div>
            </div>

            {{-- Пошаговки для каждой платформы --}}
            <h3>🧭 Подробные инструкции по платформам</h3>

            <div class="tabs-content">
                <div class="tabs">
                    <div class="tab active" data-tab="howto-tg">✈️ Telegram</div>
                    <div class="tab" data-tab="howto-vk">🔵 VK</div>
                    <div class="tab" data-tab="howto-max">💬 MAX</div>
                    <div class="tab-highlight"></div>
                </div>
                <div class="tab-panes">

                    <div class="tab-pane active" id="howto-tg">
                        <ol class="list">
                            <li>Создайте или откройте свою <strong>группу / супергруппу / канал</strong> в Telegram, куда будут приходить анонсы.</li>
                            <li>В блоке ниже выберите <strong>Telegram</strong>, укажите название (для себя) и нажмите <strong>«Создать ссылку привязки»</strong>.</li>
                            <li>Появится кнопка <strong>«Подключить Telegram»</strong> — нажмите её.</li>
                            <li>Telegram предложит выбрать чат — выберите нужный. Бот автоматически добавится в чат.</li>
                            <li>⚠️ <strong>Важно:</strong> дайте боту права <strong>администратора</strong>, иначе он не сможет публиковать сообщения.</li>
                            <li>Бот пришлёт подтверждение в чат — канал готов. Обновите эту страницу.</li>
                        </ol>
                    </div>

                    <div class="tab-pane" id="howto-vk">
                        <ol class="list">
                            <li>Создайте или откройте свою <strong>группу ВКонтакте</strong> (именно сообщество, не личную страницу).</li>
                            <li>Включите в сообществе <strong>сообщения сообщества</strong>: <em>Управление → Сообщения → Включено</em>.</li>
                            <li>Здесь выберите <strong>VK</strong> и нажмите <strong>«Создать ссылку привязки»</strong> — скопируйте появившийся код вида <code>bind_xxxxx</code>.</li>
                            <li>Нажмите <strong>«Открыть VK»</strong> — откроется диалог с нашим ботом.</li>
                            <li>Добавьте бота в свою группу как <strong>администратора</strong> или напишите ему в личку из своей группы.</li>
                            <li>Отправьте боту сообщение с кодом <code>bind_xxxxx</code> — привязка завершится автоматически.</li>
                            <li>Обновите эту страницу — канал появится в списке подтверждённых.</li>
                        </ol>
                    </div>

                    <div class="tab-pane" id="howto-max">
                        <ol class="list">
                            <li>Создайте или откройте свой <strong>чат в MAX</strong>, куда будут приходить анонсы.</li>
                            <li>Добавьте в чат нашего MAX-бота <strong>с правами администратора</strong>.</li>
                            <li>Здесь выберите <strong>MAX</strong> и нажмите <strong>«Создать ссылку привязки»</strong>.</li>
                            <li>Нажмите кнопку <strong>«Открыть MAX»</strong> — откроется бот с уже переданным токеном.</li>
                            <li>Бот покажет список чатов, где он состоит — выберите нужный.</li>
                            <li>Готово! Обновите страницу — канал отметится как подтверждённый.</li>
                        </ol>
                    </div>

                </div>
            </div>

            <div class="alert alert-warning mt-2 mb-0">
                <strong>💡 Совет:</strong> можно подключить <strong>несколько каналов</strong> на разных платформах —
                анонсы будут дублироваться во все подтверждённые каналы одновременно.
                Если ссылка привязки не сработала — она действительна <strong>30 минут</strong>,
                после чего нужно создать новую.
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
        <div class="ramka">
            <div class="alert alert-info">
                <div class="alert-title">
                    Инструкция по привязке {{ strtoupper($bindInstruction['platform'] ?? '') }}
                </div>
                <p>{{ $bindInstruction['message'] ?? '' }}</p>

                @if(!empty($bindInstruction['link']))
                    <a href="{{ $bindInstruction['link'] }}" target="_blank" rel="noopener"
                       class="btn btn-small btn-primary mt-1">
                        {{ $bindInstruction['button_text'] ?? 'Открыть' }}
                    </a>
                @endif

                @if(($bindInstruction['platform'] ?? '') !== 'max' && !empty($bindInstruction['command']))
                    <div class="mt-1">
                        <div class="f-14 b-600 mb-05">Команда / токен:</div>
                        <code class="code-block">{{ $bindInstruction['command'] }}</code>
                    </div>
                @endif

                @if(!empty($bindInstruction['instruction']))
                    <div class="mt-1 f-15 pre-line">{{ $bindInstruction['instruction'] }}</div>
                @endif
            </div>
        </div>
        @endif

        {{-- ======= УВЕДОМЛЕНИЯ О РЕГИСТРАЦИЯХ ======= --}}
        <div class="ramka">
            <h3 class="mt-0">🔔 Уведомления о записях игроков</h3>
            <p class="f-15">
                Когда включено — при каждой записи или отмене записи на ваши мероприятия
                вы будете получать сообщение во <strong>все подключённые каналы</strong>.
            </p>

            <div class="alert alert-warning mb-2">
                <strong>⚠️ Внимание!</strong> Включая данную функцию, вы можете получать
                <strong>очень много сообщений</strong>, особенно если у вас много мероприятий
                с активной записью. Убедитесь, что это вам нужно.
            </div>

            <form method="POST" action="{{ route('profile.notification_channels.settings') }}" class="d-flex align-items-center gap-1">
                @csrf
                <label class="d-flex align-items-center gap-05" style="cursor:pointer;font-size:1rem">
                    <input type="hidden" name="notify_player_registrations" value="0">
                    <input type="checkbox" name="notify_player_registrations" value="1"
                           onchange="this.form.submit()"
                           {{ ($notifyPlayerRegistrations ?? false) ? 'checked' : '' }}>
                    <span>{{ ($notifyPlayerRegistrations ?? false) ? 'Уведомления включены' : 'Уведомления выключены' }}</span>
                </label>
            </form>
        </div>

        <div class="row row2">

            {{-- Левая колонка: подключить системный канал --}}
            <div class="col-lg-6">
                <div class="ramka">
                    <h3 class="mt-0">📡 Подключить канал</h3>
                    <p class="f-15 text-muted">Системный бот сервиса отправляет анонсы в вашу группу или канал.</p>

                    <form method="POST" action="{{ route('profile.notification_channels.bind') }}" class="form">
                        @csrf
                        <div class="card">
                            <label class="f-15 b-600 mb-05">Платформа</label>
                            <select name="platform" class="w-100">
                                <option value="telegram" {{ old('platform','telegram')==='telegram'?'selected':'' }}>Telegram</option>
                                <option value="vk"       {{ old('platform')==='vk'?'selected':'' }}>VK</option>
                                <option value="max"      {{ old('platform')==='max'?'selected':'' }}>MAX</option>
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
                        @php $isWall = ($channel->meta['kind'] ?? '') === 'vk_wall'; @endphp
                        <div class="card mb-1">
                            <div class="d-flex between">
                                <div>
                                    @if($isWall)
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
                                    @elseif(!$isWall)
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
                            @if($isWall && !empty($channel->meta['group_name']))
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

        {{-- VK Wall --}}
        <div class="ramka">
            <h3 class="mt-0">🔵 Привязать VK-сообщество (публикация на стене)</h3>
            <p class="f-15 text-muted mb-2">
                Анонсы будут публиковаться <strong>на стене</strong> вашего VK-сообщества — без бота и беседы.
                Потребуется авторизация через VK с доступом к управлению стеной.
            </p>
            <form method="POST" action="{{ route('integrations.vk_community.redirect') }}" class="d-flex gap-1 flex-wrap align-items-end">
                @csrf
                <div style="flex:1;min-width:220px;">
                    <label class="f-14 b-600 mb-05 d-block">Название канала</label>
                    <input type="text" name="title" value="{{ old('vk_wall_title') }}"
                           placeholder="Например: VK Волейбол Новосибирск"
                           class="w-100" required maxlength="128">
                </div>
                <button type="submit" class="btn btn-primary" style="white-space:nowrap;">
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
    </script>
    </x-slot>

</x-voll-layout>
