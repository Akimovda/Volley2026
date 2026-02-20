@php
    $age = $u->ageYears();
    $gender = (string)($u->gender ?? '');
    $genderClass = $gender === 'm' ? 'male' : ($gender === 'f' ? 'female' : '');
    
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
    
    $classicLevel = !is_null($classic) && $classic !== '' ? (int)$classic : null;
    $beachLevel = !is_null($beach) && $beach !== '' ? (int)$beach : null;

    $profileUrl = route('users.show', ['user' => $u->id]);
@endphp

<div data-aos="fade" class="card-ramka user-card {{ $genderClass }}">
    <div class="user-avatar-wrapper">
        <a href="{{ $profileUrl }}" class="user-avatar-link">
            <div class="user-avatar-img-wrapper">
                <img
                    src="{{ $u->profile_photo_url }}"
                    alt=""
                    class="user-card-avatar-img"
                    loading="lazy"
                />
            </div>
            
            <span class="user-card-name">
                @if(!empty($u->first_name) && !empty($u->last_name))
                    {{ $u->first_name }}<br>{{ $u->last_name }}
                @else
                    Пользователь<br>#{{ $u->id }}
                @endif
            </span>
        </a>
    </div>
    
@if(!empty($metaParts))
    <div class="user-meta-list">
        @php
            $genderText = '';
            $ageText = '';
            $cityText = '';
            
            foreach($metaParts as $part) {
                if ($part === 'Мужчина' || $part === 'Женщина') {
                    $genderText = $part;
                } elseif (str_ends_with($part, ' лет')) {
                    $ageText = $part;
                } elseif (str_contains($part, '(') && str_contains($part, ')')) {
                    $cityText = $part;
                }
            }
        @endphp
        
        @if($cityText)
            <div class="user-meta-item {{ $genderClass }}">
                <span class="user-meta-icon user-icon-city"></span>
                <span class="user-meta-text">{{ $cityText }}</span>
            </div>
        @endif
        
        @if($genderText || $ageText)
            <div class="user-meta-item {{ $genderClass }}">
                <span class="user-meta-icon user-icon-gender"></span>
                <span class="user-meta-text">
                    @if($genderText && $ageText)
                        {{ $genderText }}, {{ $ageText }}
                    @elseif($genderText)
                        {{ $genderText }}
                    @elseif($ageText)
                        {{ $ageText }}
                    @endif
                </span>
            </div>
        @endif
    </div>
@endif

{{-- Чувствительные поля закомментированы
@can('view-sensitive-profile', $u)
    @php
        $sensParts = [];
        if (!empty($u->patronymic)) $sensParts['patronymic'] = 'Отчество: ' . $u->patronymic;
        if (!empty($u->phone)) $sensParts['phone'] = 'Телефон: ' . $u->phone;
    @endphp
    
    @if(!empty($sensParts))
        <div class="user-sensitive-list">
            @foreach($sensParts as $type => $line)
                @php
                    $iconClass = 'icon-' . $type;
                @endphp
                
                <div class="user-sensitive-item {{ $genderClass }}">
                    <span class="user-sensitive-icon {{ $iconClass }}"></span>
                    <span class="user-sensitive-text">{{ $line }}</span>
                </div>
            @endforeach
        </div>
    @endif
@endcan
--}}
    

    
    <div class="user-levels">
        <div class="user-level">
            <div class="user-level-label">Классика</div>
            <div class="user-level-value">
                @if($classicLevel)
                    <span class="levelmark level-{{ $classicLevel }}">{{ $classic }}</span>
                @else
                    <span class="levelmark level-na">!?</span>
                @endif
            </div>
        </div>
        <div class="user-level">
            <div class="user-level-label">Пляж</div>
            <div class="user-level-value">
                @if($beachLevel)
                    <span class="levelmark level-{{ $beachLevel }}">{{ $beach }}</span>
                @else
                    <span class="levelmark level-na">!?</span>
                @endif
            </div>
        </div>
    </div>
</div>