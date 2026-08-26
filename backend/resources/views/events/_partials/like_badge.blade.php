{{-- resources/views/events/_partials/like_badge.blade.php --}}
{{-- Плашка "лайк" мероприятия. Ожидает переменные из events/_card.blade.php:
     $event, $eventLikeCount, $eventLiked, $eventPageUrl. Гость — открывает общий
     поп-ап логина (см. auth/_login_popup.blade.php), авторизованный — toggle
     через делегированный клик-хендлер в public/assets/script.js (.js-like-toggle). --}}
@if(auth()->check())
<button
    type="button"
    class="event-like-badge js-like-toggle{{ $eventLiked ? ' is-liked' : '' }}"
    data-event-id="{{ (int) $event->id }}"
    title="{{ __('events.card_like_title') }}"
>
    <x-menu-icon name="flame" />
    <span data-like-count>{{ $eventLikeCount }}</span>
</button>
@else
<button
    type="button"
    class="event-like-badge js-open-login-popup"
    data-return-url="{{ $eventPageUrl }}"
    title="{{ __('events.card_like_title') }}"
>
    <x-menu-icon name="flame" />
    <span data-like-count>{{ $eventLikeCount }}</span>
</button>
@endif
