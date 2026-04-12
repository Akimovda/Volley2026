@props(['user', 'size' => 40, 'class' => ''])

<span class="{{ ($user->isPremium()) ? 'avatar-premium' : '' }} {{ $class }}"
      style="display:inline-block;position:relative;">
    <img
        src="{{ $user->profile_photo_url ?? asset('img/no-avatar.png') }}"
        alt="{{ $user->name }}"
        style="width:{{ $size }}px;height:{{ $size }}px;border-radius:50%;object-fit:cover;"
    >
</span>
