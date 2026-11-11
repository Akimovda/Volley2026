{{-- Цветная иконка провайдера входа. @include('admin.dashboard._provider_icon', ['p' => 'apple|google|max|tg|vk|ya', 's' => 22]) --}}
@php $s = (int) ($s ?? 22); @endphp
@switch($p)
	@case('apple')
		<svg width="{{ $s }}" height="{{ $s }}" viewBox="0 0 24 24" aria-hidden="true" style="vertical-align:middle;flex:none"><path fill="#111" d="M16.365 1.43c0 1.14-.493 2.27-1.177 3.08-.744.9-1.99 1.57-2.987 1.57-.12 0-.23-.02-.3-.03-.01-.06-.04-.22-.04-.39 0-1.15.572-2.27 1.206-2.98.804-.94 2.142-1.64 3.248-1.68.03.13.05.28.05.43zm4.565 15.71c-.03.07-.463 1.58-1.518 3.12-.945 1.34-1.94 2.71-3.43 2.71-1.517 0-1.9-.88-3.63-.88-1.698 0-2.302.91-3.67.91-1.377 0-2.332-1.26-3.428-2.8-1.287-1.82-2.323-4.63-2.323-7.28 0-4.28 2.797-6.55 5.552-6.55 1.448 0 2.675.95 3.6.95.865 0 2.222-1.01 3.902-1.01.613 0 2.886.06 4.374 2.19-.13.09-2.383 1.37-2.383 4.19 0 3.26 2.854 4.42 2.955 4.45z"/></svg>
		@break
	@case('google')
		<svg width="{{ $s }}" height="{{ $s }}" viewBox="0 0 24 24" aria-hidden="true" style="vertical-align:middle;flex:none"><path fill="#4285F4" d="M23.49 12.27c0-.79-.07-1.54-.19-2.27H12v4.51h6.47c-.29 1.48-1.14 2.73-2.4 3.58v3h3.86c2.26-2.09 3.56-5.17 3.56-8.82z"/><path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.86-3c-1.08.72-2.45 1.16-4.07 1.16-3.13 0-5.78-2.11-6.73-4.96h-3.98v3.09C3.24 21.3 7.31 24 12 24z"/><path fill="#FBBC05" d="M5.27 14.29c-.25-.72-.38-1.49-.38-2.29s.14-1.57.38-2.29V6.62h-3.98a11.86 11.86 0 0 0 0 10.76l3.98-3.09z"/><path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.31 0 3.24 2.7 1.29 6.62l3.98 3.09C6.22 6.86 8.87 4.75 12 4.75z"/></svg>
		@break
	@case('max')
		<img src="/assets/img/max-icon.svg" width="{{ $s }}" height="{{ $s }}" alt="" style="vertical-align:middle;flex:none;border-radius:50%">
		@break
	@case('tg')
		<svg width="{{ $s }}" height="{{ $s }}" viewBox="0 0 24 24" aria-hidden="true" style="vertical-align:middle;flex:none"><circle cx="12" cy="12" r="12" fill="#2AABEE"/><path fill="#fff" d="M5.43 11.87c3.5-1.52 5.83-2.53 7-3.01 3.33-1.39 4.02-1.63 4.47-1.64.1 0 .32.02.46.14.12.1.16.23.17.33.02.1.04.31.02.48-.18 1.9-.96 6.5-1.36 8.63-.17.9-.5 1.2-.82 1.23-.7.06-1.23-.46-1.9-.9-1.06-.69-1.65-1.12-2.68-1.8-1.19-.78-.42-1.21.26-1.91.18-.18 3.25-2.98 3.31-3.23.01-.03.01-.15-.06-.21-.07-.06-.17-.04-.25-.02-.11.02-1.8 1.14-5.09 3.36-.48.33-.92.49-1.31.48-.43-.01-1.26-.24-1.88-.44-.76-.25-1.36-.38-1.31-.8.03-.22.33-.45.9-.69z"/></svg>
		@break
	@case('vk')
		<svg width="{{ $s }}" height="{{ $s }}" viewBox="0 0 24 24" aria-hidden="true" style="vertical-align:middle;flex:none"><circle cx="12" cy="12" r="12" fill="#0077FF"/><text x="12" y="15.6" text-anchor="middle" font-family="Arial,Helvetica,sans-serif" font-size="9.5" font-weight="800" fill="#fff">VK</text></svg>
		@break
	@case('ya')
		<svg width="{{ $s }}" height="{{ $s }}" viewBox="0 0 24 24" aria-hidden="true" style="vertical-align:middle;flex:none"><circle cx="12" cy="12" r="12" fill="#FC3F1D"/><text x="12" y="16.6" text-anchor="middle" font-family="Arial,Helvetica,sans-serif" font-size="13" font-weight="800" fill="#fff">Я</text></svg>
		@break
@endswitch
