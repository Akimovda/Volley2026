{{-- resources/views/users/show.blade.php --}}
<x-voll-layout body_class="user-show-page">
<x-slot name="title">{{ ($u ?? $user)?->name ?? __('profile.pub_title_fallback') }} {{ __('profile.pub_title_suffix') }}</x-slot>
<x-slot name="description">{{ __('profile.pub_description', ['name' => ($u ?? $user)?->name ?? '']) }}</x-slot>
<x-slot name="canonical">{{ route('users.show', ($u ?? $user)?->id) }}</x-slot>

    <div class="v-container py-6">
        <div class="v-card">
            <div class="v-card__body">
                @php
                    /** @var \App\Models\User $u */
                    $u = $u ?? ($user ?? null);

                    if ($u) {
                        $u->loadMissing(['city', 'classicPositions', 'beachZones']);
                    }

                    $posMap = [
                        'setter'   => __('profile.positions.setter'),
                        'outside'  => __('profile.positions.outside'),
                        'opposite' => __('profile.positions.opposite'),
                        'middle'   => __('profile.positions.middle'),
                        'libero'   => __('profile.positions.libero'),
                    ];

                    $classicPrimary = optional($u?->classicPositions)->firstWhere('is_primary', true)?->position;
                    $classicExtras = optional($u?->classicPositions)
                        ->where('is_primary', false)
                        ->pluck('position')
                        ->values()
                        ->all() ?? [];

                    $beachPrimaryZone = optional($u?->beachZones)->firstWhere('is_primary', true)?->zone;
                    $beachExtras = optional($u?->beachZones)
                        ->where('is_primary', false)
                        ->pluck('zone')
                        ->values()
                        ->all() ?? [];

                    // контакты
                    $allowContact = (bool) ($u?->allow_user_contact ?? false);

                    $tgUsername = trim((string)($u?->telegram_username ?? ''));
                    $hasTgLink = $tgUsername !== '';
                    $tgLink = $hasTgLink ? ('https://t.me/' . ltrim($tgUsername, '@')) : null;

                    $vkIdRaw = trim((string)($u?->vk_id ?? ''));
                    $hasVkLink = $vkIdRaw !== '';
                    $vkLink = null;
                    if ($hasVkLink) {
                        $vkLink = ctype_digit($vkIdRaw)
                            ? ('https://vk.com/id' . $vkIdRaw)
                            : ('https://vk.com/' . $vkIdRaw);
                    }

                    $genderLabel = $u?->gender === 'm' ? __('profile.idx_gender_m') : ($u?->gender === 'f' ? __('profile.idx_gender_f') : '—');
                    $age = ($u && method_exists($u, 'ageYears')) ? $u->ageYears() : null;

                    $cityLabel = $u?->city
                        ? ($u->city->name . ($u->city->region ? ' (' . $u->city->region . ')' : ''))
                        : null;

                    $headerMeta = array_values(array_filter([
                        $cityLabel,
                        !is_null($age) ? (($u->gender === 'f' && $u->hide_age) ? '🤷‍♀️' : __('profile.card_age_years', ['n' => $age])) : null,
                        $genderLabel !== '—' ? $genderLabel : null,
                        !empty($u?->height_cm) ? __('profile.card_height_cm', ['n' => $u->height_cm]) : null,
                    ]));
                @endphp

                @if(!$u)
                    <div class="v-alert v-alert--warn">
                        <div class="v-alert__text">{{ __('profile.pub_not_found') }}</div>
                    </div>
                @else
                    {{-- Header --}}
                    <div class="flex flex-col md:flex-row items-start gap-5">
                        <div class="shrink-0">
                            <span class="{{ $u->isPremium() ? 'avatar-premium' : '' }}" style="display:inline-block;position:relative;">
                            <img
                                src="{{ $u->profile_photo_url }}"
                                alt="avatar"
                                class="rounded-full border border-gray-200 bg-gray-100"
                                style="width:92px;height:92px;object-fit:cover;"
                                loading="lazy"
                            />
                            </span>
                        </div>

                        <div class="min-w-0 flex-1">
                            <h1 class="text-2xl md:text-3xl font-bold text-gray-900 leading-tight break-words">
                                {{ method_exists($u, 'displayName') ? $u->displayName() : ($u->name ?? '—') }}
                            </h1>

                            @if(!empty($headerMeta))
                                <div class="text-sm text-gray-600 mt-2 break-words">
                                    {{ implode(' · ', $headerMeta) }}
                                </div>
                            @endif

                            <div class="v-actions mt-4 flex flex-wrap gap-2">
                                <a class="v-btn v-btn--secondary" href="{{ route('users.index') }}">{{ __('profile.pub_back_to_list') }}</a>
                                <a class="v-btn v-btn--secondary" href="/events">{{ __('profile.pub_to_events') }}</a>
                            </div>
                        </div>
                    </div>

                    {{-- Contacts --}}
                    <div class="mt-6 v-card">
                        <div class="v-card__body">
                            <div class="font-semibold text-lg mb-2">{{ __('profile.pub_contact_title') }}</div>

                            @auth
                                @if(auth()->id() === $u->id)
                                    <div class="v-alert v-alert--info">
                                        <div class="v-alert__text">
                                            {{ __('profile.pub_contact_self') }}
                                            <a class="underline" href="{{ route('profile.show') }}">{{ __('profile.pub_contact_self_link') }}</a>.
                                        </div>
                                    </div>
                                @else
                                    @if(!$allowContact)
                                        <div class="v-alert v-alert--info">
                                            <div class="v-alert__text">
                                                {{ __('profile.pub_contact_blocked') }}
                                            </div>
                                        </div>
                                    @else
                                        <div class="flex flex-wrap gap-2">
                                            @if($hasTgLink)
                                                <a class="v-btn v-btn--secondary"
                                                   href="{{ $tgLink }}"
                                                   target="_blank" rel="noopener">
                                                    Telegram →
                                                </a>
                                            @endif

                                            @if($hasVkLink)
                                                <a class="v-btn v-btn--secondary"
                                                   href="{{ $vkLink }}"
                                                   target="_blank" rel="noopener">
                                                    VK →
                                                </a>
                                            @endif

                                            @if(!$hasTgLink && !$hasVkLink)
                                                <div class="v-alert v-alert--info">
                                                    <div class="v-alert__text">
                                                        {{ __('profile.pub_contact_no_links') }}
                                                    </div>
                                                </div>
                                            @endif
                                        </div>

                                        @if(($u->telegram_id ?? null) && !$hasTgLink)
                                            <div class="text-xs text-gray-500 mt-2 break-words">
                                                {{ __('profile.pub_contact_tg_no_username') }}
                                            </div>
                                        @endif
                                    @endif
                                @endif
                            @else
                                <div class="v-alert v-alert--info">
                                    <div class="v-alert__text">
                                        {{ __('profile.pub_contact_login_required') }}
                                    </div>
                                </div>
                            @endauth
                        </div>
                    </div>

                    {{-- Personal data --}}
                    <div class="mt-6 v-card">
                        <div class="v-card__body">
                            <div class="font-semibold text-lg mb-3">{{ __('profile.pub_personal_title') }}</div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                                <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                                    <div class="text-xs text-gray-500">{{ __('profile.pub_field_lastname') }}</div>
                                    <div class="font-semibold text-gray-900 break-words">{{ $u->last_name ?? '—' }}</div>
                                </div>

                                <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                                    <div class="text-xs text-gray-500">{{ __('profile.pub_field_firstname') }}</div>
                                    <div class="font-semibold text-gray-900 break-words">{{ $u->first_name ?? '—' }}</div>
                                </div>

                                @can('view-sensitive-profile', $u)
                                    <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                                        <div class="text-xs text-gray-500">{{ __('profile.pub_field_patronymic') }}</div>
                                        <div class="font-semibold text-gray-900 break-words">{{ $u->patronymic ?? '—' }}</div>
                                    </div>

                                    <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                                        <div class="text-xs text-gray-500">{{ __('profile.pub_field_phone') }}</div>
                                        <div class="font-semibold text-gray-900 break-words">{{ $u->phone ?? '—' }}</div>
                                    </div>
                                @endcan

                                <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                                    <div class="text-xs text-gray-500">{{ __('profile.pub_field_gender') }}</div>
                                    <div class="font-semibold text-gray-900">{{ $genderLabel }}</div>
                                </div>

                                <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                                    <div class="text-xs text-gray-500">{{ __('profile.pub_field_city') }}</div>
                                    <div class="font-semibold text-gray-900 break-words">
                                        {{ $cityLabel ?? '—' }}
                                    </div>
                                </div>

                                <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                                    <div class="text-xs text-gray-500">{{ __('profile.pub_field_height') }}</div>
                                    <div class="font-semibold text-gray-900">
                                        {{ !empty($u->height_cm) ? __('profile.card_height_cm', ['n' => $u->height_cm]) : '—' }}
                                    </div>
                                </div>

                                <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                                    <div class="text-xs text-gray-500">{{ __('profile.pub_field_birthdate') }}</div>
                                    <div class="font-semibold text-gray-900">
                                        {{ $u->birth_date?->format('Y-m-d') ?? '—' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Skills --}}
                    <div class="mt-6">
                        <div class="font-semibold text-lg mb-3">{{ __('profile.pub_skills_title') }}</div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="v-card">
                                <div class="v-card__body space-y-2">
                                    <div class="font-semibold text-gray-900">{{ __('profile.pub_skills_classic') }}</div>

                                    <div class="text-sm">
                                        {{ __('profile.pub_skills_level') }}
                                        <span class="font-semibold">{{ $u->classic_level ?? '—' }}</span>
                                    </div>

                                    <div class="text-sm break-words">
                                        {{ __('profile.pub_skills_role') }}
                                        <span class="font-semibold">
                                            @if($classicPrimary)
                                                {{ __('profile.pub_skills_primary') }} {{ $posMap[$classicPrimary] ?? $classicPrimary }}
                                                @if(!empty($classicExtras))
                                                    ; {{ __('profile.pub_skills_extra') }} {{ collect($classicExtras)->map(fn($p)=>$posMap[$p] ?? $p)->join(', ') }}
                                                @endif
                                            @else
                                                —
                                            @endif
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="v-card">
                                <div class="v-card__body space-y-2">
                                    <div class="font-semibold text-gray-900">{{ __('profile.pub_skills_beach') }}</div>

                                    <div class="text-sm">
                                        {{ __('profile.pub_skills_level') }}
                                        <span class="font-semibold">{{ $u->beach_level ?? '—' }}</span>
                                    </div>

                                    <div class="text-sm break-words">
                                        {{ __('profile.pub_skills_zone') }}
                                        <span class="font-semibold">
                                            @if($u->beach_universal)
                                                {{ __('profile.pub_beach_universal') }}
                                            @elseif(!is_null($beachPrimaryZone))
                                                {{ __('profile.pub_skills_primary_zone') }} {{ $beachPrimaryZone }}
                                                @if(!empty($beachExtras))
                                                    ; {{ __('profile.pub_skills_extra') }} {{ collect($beachExtras)->join(', ') }}
                                                @endif
                                            @else
                                                —
                                            @endif
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-voll-layout>
