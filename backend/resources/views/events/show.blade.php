{{-- resources/views/events/show.blade.php --}}
@php
    /** @var \App\Models\Event $event */
    /** @var \App\Models\EventOccurrence|null $occurrence */
    /** @var array $availability */
    $user = auth()->user();

    $userTz = $user?->timezone ?: ($occurrence?->timezone ?: ($event->timezone ?: 'UTC'));
    $tz = $userTz;

    $starts = $occurrence?->starts_at
        ? \Illuminate\Support\Carbon::parse($occurrence->starts_at)->setTimezone($tz)
        : ($event->starts_at ? \Illuminate\Support\Carbon::parse($event->starts_at)->setTimezone($tz) : null);

    $ends = $occurrence?->ends_at
        ? \Illuminate\Support\Carbon::parse($occurrence->ends_at)->setTimezone($tz)
        : ($event->ends_at ? \Illuminate\Support\Carbon::parse($event->ends_at)->setTimezone($tz) : null);

    $dateLabel = $starts ? $starts->format('d.m.Y') : '—';
    $timeLabel = $starts
        ? $starts->format('H:i') . ($ends ? '–' . $ends->format('H:i') : '')
        : '—';

    $addressParts = array_filter([
        $event->location?->name,
        $event->location?->city,
        $event->location?->address,
    ]);
    $address = $addressParts ? implode(', ', $addressParts) : null;

    $mapQuery = $address ? urlencode($address) : null;

    $meta = $availability['meta'] ?? [];
    $maxPlayers = (int)($meta['max_players'] ?? ($availability['max_players'] ?? 0));
    $registeredTotal = (int)($meta['registered_total'] ?? ($availability['registered_total'] ?? 0));

    // =========================
    // ✅ UI-фильтр по Gender Policy
    // =========================
    $normalizeGender = function ($g): ?string {
        $g = is_string($g) ? trim($g) : null;
        if ($g === null || $g === '') return null;
        $g = mb_strtolower($g);
        if (in_array($g, ['m','male','man'], true)) return 'male';
        if (in_array($g, ['f','female','woman'], true)) return 'female';
        if (in_array($g, ['male','female'], true)) return $g;
        return null;
    };

    $userGender = $user ? $normalizeGender($user->gender ?? ($user->sex ?? ($user->profile_gender ?? null))) : null;

    $gs = \App\Models\EventGameSetting::query()->where('event_id', $event->id)->first();

    $effectivePolicy = null;
    $effectiveLimitedSide = null;
    $effectiveLimitedMax = null;
    $effectiveLimitedPositions = null;

    if ($gs) {
        $effectivePolicy = $gs->gender_policy ? (string)$gs->gender_policy : null;

        // legacy fallback
        if (!$effectivePolicy) {
            if (\Illuminate\Support\Facades\Schema::hasColumn('event_game_settings', 'allow_girls')) {
                $allowGirls = (bool)($gs->allow_girls ?? true);
                if (!$allowGirls) $effectivePolicy = 'only_male';
                else {
                    $girlsMax = $gs->girls_max ?? null;
                    $effectivePolicy = is_null($girlsMax) ? 'mixed_open' : 'mixed_limited';
                }
            }
        }

        $effectiveLimitedSide = $normalizeGender($gs->gender_limited_side ?? null);
        $effectiveLimitedMax = is_null($gs->gender_limited_max) ? null : (int)$gs->gender_limited_max;

        $lp = $gs->gender_limited_positions ?? null;
        if (is_string($lp)) $lp = [$lp];
        if (is_array($lp)) {
            $lp = array_values(array_unique(array_map('strval', $lp)));
            $effectiveLimitedPositions = count($lp) ? $lp : null;
        } else {
            $effectiveLimitedPositions = null;
        }

        // legacy: mixed_limited без side/max -> girls_max
        if ($effectivePolicy === 'mixed_limited' && (!$effectiveLimitedSide || is_null($effectiveLimitedMax))) {
            if (\Illuminate\Support\Facades\Schema::hasColumn('event_game_settings', 'girls_max')) {
                $effectiveLimitedSide = 'female';
                $effectiveLimitedMax = is_null($gs->girls_max) ? null : (int)$gs->girls_max;
                $effectiveLimitedPositions = $effectiveLimitedPositions ?: null;
            }
        }
    }

    $policyRequiresGender = in_array($effectivePolicy, ['only_male','only_female','mixed_open','mixed_limited','mixed_5050'], true);

    $freePositions = $availability['free_positions'] ?? [];
    $filteredFreePositions = $freePositions;

    $genderBlockedMessage = null;
    if ($user && $effectivePolicy === 'only_male' && $userGender !== 'male') {
        $filteredFreePositions = [];
        $genderBlockedMessage = 'Это мероприятие доступно только мужчинам.';
    }
    if ($user && $effectivePolicy === 'only_female' && $userGender !== 'female') {
        $filteredFreePositions = [];
        $genderBlockedMessage = 'Это мероприятие доступно только девушкам.';
    }
    if ($user && $effectivePolicy === 'mixed_limited' && $effectiveLimitedSide && $userGender === $effectiveLimitedSide) {
        if (is_array($effectiveLimitedPositions) && count($effectiveLimitedPositions) > 0) {
            $filteredFreePositions = array_values(array_filter($filteredFreePositions, function ($pos) use ($effectiveLimitedPositions) {
                $k = is_array($pos) ? ($pos['key'] ?? null) : null;
                return is_string($k) && in_array($k, $effectiveLimitedPositions, true);
            }));
        }
    }

    // ✅ Trainer label (legacy single)
    $trainer = $event->trainer_user ?? null;
    $trainerLabel = null;
    if ($trainer) {
        $trainerLabel = trim((string)($trainer->name ?? ''));
        if ($trainerLabel === '') $trainerLabel = (string)($trainer->nickname ?? '');
        if ($trainerLabel === '') $trainerLabel = (string)($trainer->username ?? '');
        if ($trainerLabel === '') $trainerLabel = (string)($trainer->phone ?? '');
    }

    $trainerIcon = asset('icons/trainer.png'); // public/icons/trainer.png

    // ✅ Description html (Trix)
    $descHtml = (string)($event->description_html ?? '');
    $hasDesc = trim(strip_tags($descHtml)) !== '';
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ $event->title }}
                </h2>
                @if(!empty($event->is_private))
                    <div class="text-sm text-gray-500 flex items-center gap-2 mt-1">
                        <span title="Приватное">🙈 invisible️</span>
                        <span>Приватное мероприятие</span>
                    </div>
                @endif
            </div>

            <a href="{{ route('events.index') }}" class="v-btn v-btn--secondary">
                ← К списку мероприятий
            </a>
        </div>
    </x-slot>

    <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 py-10 space-y-8">
        {{-- Основная информация --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <div class="text-sm text-gray-500">Дата</div>
                    <div class="font-semibold text-gray-900">{{ $dateLabel }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">Время</div>
                    <div class="font-semibold text-gray-900">
                        {{ $timeLabel }} <span class="text-xs text-gray-500">({{ $tz }})</span>
                    </div>
                </div>
                <div class="md:col-span-2">
                    <div class="text-sm text-gray-500">Адрес</div>
                    <div class="font-semibold text-gray-900">{{ $address ?? '—' }}</div>
                </div>
            </div>

            {{-- ✅ Trainer badge --}}
            @if(in_array(($event?->format ?? ''), ['training','training_game','training_pro_am','camp','coach_student'], true) && $trainer && $trainerLabel)
                <div>
                    <span class="v-badge v-badge--info" style="display:inline-flex;align-items:center;gap:.35rem;">
                        <img src="{{ $trainerIcon }}" alt="" style="width:14px;height:14px;opacity:.85;">
                        Тренер: {{ $trainerLabel }}
                    </span>
                </div>
            @endif
        </div>

        {{-- ✅ Описание --}}
        @if($hasDesc)
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <div class="font-semibold text-gray-900 mb-3">Описание мероприятия</div>

                {{-- ВАЖНО: это HTML из Trix. Если у вас нет server-side sanitize —
                     лучше ограничить теги через Purifier. Пока оставляем как есть. --}}
                <div class="prose max-w-none trix-content">
                    {!! $descHtml !!}
                </div>
            </div>
        @endif

        {{-- Карта --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-4 border-b border-gray-100 font-semibold text-gray-900">Карта</div>
            @if($mapQuery)
                <div class="w-full h-[380px]">
                    <iframe
                        width="100%"
                        height="100%"
                        frameborder="0"
                        scrolling="no"
                        marginheight="0"
                        marginwidth="0"
                        src="https://www.openstreetmap.org/export/embed.html?search={{ $mapQuery }}&zoom=16">
                    </iframe>
                </div>
            @else
                <div class="p-6 text-sm text-gray-500">
                    Адрес не указан — карта недоступна.
                </div>
            @endif
        </div>

        {{-- Регистрация / свободные места --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-4">
            <div class="flex items-center justify-between">
                <div class="font-semibold text-gray-900">Запись на мероприятие</div>
                <div class="text-sm text-gray-500">
                    Всего мест: {{ $maxPlayers }},
                    занято: {{ $registeredTotal }}
                </div>
               @if (($meta['gender_policy'] ?? null) === 'mixed_5050')
                    @php
                        // ✅ Берём из API meta (чтобы работало даже когда participants не переданы / ok=false)
                        $limit  = (int)($meta['gender_5050_limit'] ?? (($maxPlayers > 0) ? intdiv((int)$maxPlayers, 2) : 0));
                        $male   = (int)($meta['gender_5050_male'] ?? 0);
                        $female = (int)($meta['gender_5050_female'] ?? 0);
                
                        // 🔁 fallback на participants (если вдруг meta пустая)
                        if (($meta['gender_5050_male'] ?? null) === null || ($meta['gender_5050_female'] ?? null) === null) {
                            $norm = function($g){
                                $g = is_string($g) ? mb_strtolower(trim($g)) : '';
                                if (in_array($g, ['m','male','man'], true)) return 'male';
                                if (in_array($g, ['f','female','woman'], true)) return 'female';
                                return null;
                            };
                            $male = collect($participants ?? [])->filter(fn($u) => $norm(data_get($u,'gender')) === 'male')->count();
                            $female = collect($participants ?? [])->filter(fn($u) => $norm(data_get($u,'gender')) === 'female')->count();
                        }
                    @endphp
                
                    <div class="text-sm text-gray-600">
                        М: {{ $male }}/{{ $limit }}, Ж: {{ $female }}/{{ $limit }}
                    </div>
                @endif
            </div>

            @if(!$event->allow_registration)
                <div class="p-3 rounded-lg bg-gray-50 text-gray-600 text-sm">
                    Регистрация на это мероприятие отключена.
                </div>
            @elseif(!$user)
                <div class="p-3 rounded-lg bg-gray-50 text-gray-700 text-sm">
                    Чтобы записаться — нужно войти.
                    <a class="text-blue-600 font-semibold hover:text-blue-700" href="{{ route('login') }}">Войти →</a>
                </div>
            @elseif($policyRequiresGender && $userGender === null)
                <div class="p-3 rounded-lg bg-yellow-50 text-yellow-900 text-sm border border-yellow-100">
                    Для записи на это мероприятие нужно указать пол в профиле.
                    <a class="ml-2 text-blue-700 font-semibold hover:text-blue-800" href="/profile/complete">
                        Заполнить профиль →
                    </a>
                </div>
            @elseif($genderBlockedMessage)
                <div class="p-3 rounded-lg bg-red-50 text-red-700 text-sm">
                    {{ $genderBlockedMessage }}
                </div>
            @elseif(empty($filteredFreePositions))
                <div class="p-3 rounded-lg bg-red-50 text-red-700 text-sm">
                    Свободных мест больше нет (или нет доступных позиций по ограничениям).
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                    @foreach($filteredFreePositions as $pos)
                        <button
                            class="v-btn v-btn--primary w-full"
                            data-position="{{ $pos['key'] }}"
                        >
                            {{ $pos['label'] }}
                            <span class="ml-2 text-xs opacity-80">({{ $pos['free'] }})</span>
                        </button>
                    @endforeach
                </div>

                <div class="text-xs text-gray-500">
                    Нажми на позицию, чтобы записаться.
                    @if($effectivePolicy === 'mixed_limited' && $effectiveLimitedSide && $userGender === $effectiveLimitedSide && is_array($effectiveLimitedPositions) && count($effectiveLimitedPositions) > 0)
                        <span class="ml-2">Вам доступны только выбранные позиции по гендерному ограничению.</span>
                    @endif
                </div>
            @endif
            @if(($event->show_participants ?? true) && !empty($participants))
              <div class="mt-6 p-4 rounded-xl border border-gray-100 bg-white">
                <div class="font-semibold">Список записавшихся игроков:</div>
                <ol class="list-decimal ml-5 mt-2 space-y-1">
                  @foreach($participants as $u)
                    <li>
                      <a class="text-blue-700 hover:underline" href="{{ route('users.show', $u->id) }}">
                        {{ trim(($u->last_name ?? '').' '.($u->first_name ?? '')) !== '' ? trim(($u->last_name ?? '').' '.($u->first_name ?? '')) : ($u->name ?? ('User #'.$u->id)) }}
                      </a>
                    </li>
                  @endforeach
                </ol>
              </div>
            @endif
        </div>
    </div>
</x-app-layout>
