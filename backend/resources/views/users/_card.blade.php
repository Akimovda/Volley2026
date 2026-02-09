{{-- resources/views/users/_card.blade.php --}}
@php
    /** @var \App\Models\User $u */

    $age = $u->ageYears();
    $gender = (string)($u->gender ?? '');
    $genderLabel = $gender === 'm' ? 'Мужчина' : ($gender === 'f' ? 'Женщина' : null);

    $cityLabel = null;
    if ($u->city) {
        $cityLabel = $u->city->name . ($u->city->region ? ' (' . $u->city->region . ')' : '');
    }

    $metaParts = array_values(array_filter([
        $cityLabel,
        !is_null($age) ? ($age . ' лет') : null,
        $genderLabel,
        !empty($u->height_cm) ? ($u->height_cm . ' см') : null,
    ]));

    $classic = $u->classic_level ?? null;
    $beach   = $u->beach_level ?? null;

    $profileUrl = route('users.show', ['user' => $u->id]);
@endphp

<div class="v-card h-full">
    <div class="v-card__body">
        <div class="flex items-start gap-3">
            <a href="{{ $profileUrl }}" class="shrink-0">
                <img
                    src="{{ $u->profile_photo_url }}"
                    alt="avatar"
                    class="rounded-full border border-gray-200 bg-gray-100"
                    style="width:52px;height:52px;object-fit:cover;"
                    loading="lazy"
                />
            </a>

            <div class="min-w-0 flex-1">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <div class="font-semibold text-gray-900 leading-tight truncate">
                            <a class="hover:underline" href="{{ $profileUrl }}">
                                {{ $u->displayName() }}
                            </a>
                        </div>

                        @if(!empty($metaParts))
                            <div class="text-xs text-gray-500 mt-1 leading-snug">
                                {{ implode(' · ', $metaParts) }}
                            </div>
                        @endif
                    </div>

                    @if($gender === 'm' || $gender === 'f')
                        <span class="shrink-0 inline-flex items-center px-2 py-1 rounded-full text-[11px] font-semibold
                            {{ $gender === 'm' ? 'bg-blue-50 text-blue-700 border border-blue-100' : 'bg-pink-50 text-pink-700 border border-pink-100' }}">
                            {{ $gender === 'm' ? 'M' : 'F' }}
                        </span>
                    @endif
                </div>

                <div class="mt-3 grid grid-cols-2 gap-2">
                    <div class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2">
                        <div class="text-[11px] text-gray-500">Классика</div>
                        <div class="font-semibold text-gray-900 leading-tight">
                            {{ !is_null($classic) && $classic !== '' ? $classic : '—' }}
                        </div>
                    </div>
                    <div class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2">
                        <div class="text-[11px] text-gray-500">Пляж</div>
                        <div class="font-semibold text-gray-900 leading-tight">
                            {{ !is_null($beach) && $beach !== '' ? $beach : '—' }}
                        </div>
                    </div>
                </div>

                {{-- Sensitive fields --}}
                @can('view-sensitive-profile', $u)
                    @php
                        $sensParts = [];
                        if (!empty($u->patronymic)) $sensParts[] = 'Отчество: ' . $u->patronymic;
                        if (!empty($u->phone)) $sensParts[] = 'Телефон: ' . $u->phone;
                    @endphp

                    @if(!empty($sensParts))
                        <div class="mt-3 text-xs text-gray-600 border-t border-gray-100 pt-3">
                            @foreach($sensParts as $line)
                                <div class="truncate">{{ $line }}</div>
                            @endforeach
                        </div>
                    @endif
                @endcan

                <div class="mt-3">
                    <a href="{{ $profileUrl }}"
                       class="inline-flex items-center justify-center w-full px-3 py-2 rounded-lg text-sm font-semibold
                              border border-gray-200 bg-white hover:bg-gray-50">
                        Открыть профиль →
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
