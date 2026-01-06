<div class="v-card">
    <div class="v-card__body">
        <div class="flex items-start gap-3">
            <img
                src="{{ $u->profile_photo_url }}"
                alt="avatar"
                class="rounded-full"
                style="width:48px;height:48px;object-fit:cover;"
            />

            <div class="min-w-0">
                <div class="font-semibold">
                    <a class="underline" href="{{ route('users.show', ['user' => $u->id]) }}">
                        {{ $u->displayName() }}
                    </a>
                </div>

                <div class="text-sm text-gray-600">
                    @if($u->city)
                        {{ $u->city->name }}@if($u->city->region) ({{ $u->city->region }})@endif
                        ·
                    @endif

                    @php $age = $u->ageYears(); @endphp
                    @if(!is_null($age))
                        {{ $age }} лет ·
                    @endif

                    @if($u->gender === 'm')
                        Мужчина
                    @elseif($u->gender === 'f')
                        Женщина
                    @endif

                    @if(!empty($u->height_cm))
                        · {{ $u->height_cm }} см
                    @endif
                </div>

                <div class="text-sm mt-2">
                    <div>Классика: <span class="font-semibold">{{ $u->classic_level ?? '—' }}</span></div>
                    <div>Пляж: <span class="font-semibold">{{ $u->beach_level ?? '—' }}</span></div>
                </div>

                {{-- Скрытые поля: только owner/admin/organizer/staff --}}
                @can('view-sensitive-profile', $u)
                    <div class="text-sm mt-2">
                        @if(!empty($u->patronymic))
                            <div>Отчество: <span class="font-semibold">{{ $u->patronymic }}</span></div>
                        @endif
                        @if(!empty($u->phone))
                            <div>Телефон: <span class="font-semibold">{{ $u->phone }}</span></div>
                        @endif
                    </div>
                @endcan
            </div>
        </div>
    </div>
</div>
