{{-- Кнопка проверки Telegram-канала/темы. @include('partials.channel-check', ['channel' => $channel, 'threadInput' => '#селектор поля номера темы' | null]) --}}
@once
	<script src="@asset_v('js/channel-check.js')" defer></script>
@endonce
@if($channel->platform === 'telegram')
<div class="channel-check" style="margin-top:.4rem">
	<button type="button" class="btn btn-secondary btn-small channel-check-btn"
		data-check-url="{{ route('profile.notification_channels.check', $channel) }}"
		data-checking="{{ __('channel_check.checking') }}"
		data-failed="{{ __('channel_check.unavailable') }}"
		@if(!empty($threadInput)) data-thread-input="{{ $threadInput }}" @endif>{{ __('channel_check.btn') }}</button>
	<div class="channel-check-result f-13" style="margin-top:.4rem;max-width:520px"></div>
</div>
@endif
