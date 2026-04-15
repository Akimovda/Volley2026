{{-- resources/views/admin/users/index.blade.php --}}


<x-voll-layout body_class="admin-users-page"> 
	
    <x-slot name="title">
		Пользователи сайта
	</x-slot>
	
    <x-slot name="description">
		Администрирование пользователей сайта
	</x-slot>
	
    <x-slot name="canonical">
		{{ route('admin.users.index') }}
	</x-slot>
	
    <x-slot name="style">
        <style>
.table td {
    vertical-align: middle;
}			
		</style>		
	</x-slot>
	
    <x-slot name="h1">
        Пользователи сайта
	</x-slot>
	
	
    <x-slot name="t_description">
		Администрирование пользователей сайта
	</x-slot>
	
    <x-slot name="breadcrumbs">
		{{-- Крошки в шапку, например  --}} 
		<li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem">
			<a href="{{ route('admin.dashboard') }}" itemprop="item"><span itemprop="name">Админ-панель</span></a>
			<meta itemprop="position" content="2">
		</li>		
		<li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem">
			<a href="{{ route('admin.users.index') }}" itemprop="item"><span itemprop="name">Администрирование пользователей</span></a>
			<meta itemprop="position" content="3">
		</li>
	</x-slot>	
	
	<x-slot name="d_description">
		<div data-aos-delay="250" data-aos="fade-up">
			<button class="btn ufilter-btn mt-2">Фильтр</button>
		</div>
	</x-slot>
	
	
    <x-slot name="script">
<script>
(function(){
    var inp = document.getElementById('admin-users-search-q');
    var dd = document.getElementById('admin-users-search-dd');
    var timer = null;
    if (!inp || !dd) return;
    function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
    function showDd() { dd.classList.add('form-select-dropdown--active'); }
    function hideDd() { dd.classList.remove('form-select-dropdown--active'); }
    function render(items) {
        dd.innerHTML = '';
        if (!items.length) { dd.innerHTML = '<div class="city-message">Ничего не найдено</div>'; showDd(); return; }
        items.slice(0,8).forEach(function(u) {
            var div = document.createElement('div');
            div.className = 'trainer-item form-select-option';
            div.setAttribute('data-name', u.label || '');
            div.innerHTML = '<div class="text-sm text-gray-900">' + esc(u.label || '') + '</div>';
            div.addEventListener('click', function() { inp.value = div.getAttribute('data-name'); hideDd(); inp.closest('form').submit(); });
            dd.appendChild(div);
        });
        showDd();
    }
    inp.addEventListener('input', function() {
        clearTimeout(timer);
        var q = inp.value.trim();
        if (q.length < 2) { hideDd(); return; }
        dd.innerHTML = '<div class="city-message">Поиск…</div>'; showDd();
        timer = setTimeout(function() {
            fetch('/api/users/search?q=' + encodeURIComponent(q))
                .then(function(r){ return r.json(); })
                .then(function(data){ render(Array.isArray(data) ? data : (data.items||[])); });
        }, 250);
    });
    document.addEventListener('click', function(e) { if (!inp.contains(e.target) && !dd.contains(e.target)) hideDd(); });
})();
</script>
</x-slot>	
	
    <div class="container">
		<div class="users-filter">
			<div class="ramka">
				<div class="form">
					<form method="GET" action="{{ route('admin.users.index') }}">
						{{-- Search --}}
						
						<div class="row">
							<div class="col-12 col-md-6">
								
								<div style="position:relative;">
								<input
									name="q"
									id="admin-users-search-q"
									value="{{ $q ?? '' }}"
									placeholder="Акимов Дмитрий / email / telegram"
									autocomplete="off"/>
								<div id="admin-users-search-dd" class="form-select-dropdown trainer_dd"></div>
							</div>
								
							</div>
							<div class="col-12 col-md-2">
								{{-- Role --}}
								<select name="role">
									<option value="">Все роли</option>
									@foreach (($roles ?? []) as $r)
									<option value="{{ $r }}" @selected(($role ?? null) === $r)>{{ $r }}</option>
									@endforeach
								</select>
							</div>
							<div class="col-12 col-md-4">
								{{-- Restrictions (events) --}}
								@php
								// Safety fallback (чтобы никогда не падало, даже если контроллер забыли обновить)
								$restrictedOptions = $restrictedOptions ?? [
								'all' => 'Все',
								'restricted' => 'Только с блокировками (events)',
								'not_restricted' => 'Только без блокировок',
								];
								$restricted = $restricted ?? 'all';
								@endphp
								
								<select name="restricted">
									@foreach ($restrictedOptions as $key => $label)
									<option value="{{ $key }}" @selected($restricted === $key)>{{ $label }}</option>
									@endforeach
								</select>
							</div>
							
							<div class="col-12 text-right m-center">
								<button class="btn" type="submit">Найти</button>
								<a href="{{ route('admin.users.index') }}" class="btn btn-secondary">Сброс</a>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>	
		
		<div class="ramka">	
			<div class="table-scrollable mb-0">
			<div class="table-drag-indicator"></div>		
			<table class="table">
				<thead class="text-gray-600">
					<tr>
						<th>ID</th>
						<th>Пользователь</th>
						<th>Роль</th>
						<th>TG</th>
						<th>VK</th>
						<th>Yandex</th>
						<th>Регистрация</th>
					</tr>
				</thead>
				
				<tbody>
					@foreach ($users as $u)
					<tr>
						<td>{{ $u->id }}</td>
						
						<td>
<a class="blink b-600" href="{{ route('admin.users.show', $u) }}">
@if($u->last_name || $u->first_name)
    {{ trim(($u->last_name ?? '') . ' ' . ($u->first_name ?? '')) }}
@else
    {{ $u->name }}
@endif
</a>
@if($u->name && ($u->last_name || $u->first_name))
<div class="f-13" style="opacity:.4">{{ $u->name }}</div>
@endif
						</td>
						
						<td>{{ $u->role ?? 'user' }}</td>
						<td class="text-center">{{ $u->telegram_id ? '✅' : '—' }}</td>
						<td class="text-center">{{ $u->vk_id ? '✅' : '—' }}</td>
						<td class="text-center">{{ $u->yandex_id ? '✅' : '—' }}</td>
						<td class="text-center">{{ $u->created_at?->format('Y-m-d') }}</td>
					</tr>
					@endforeach
				</tbody>
			</table>
		</div>

		</div>	
				{{ $users->links() }}
	</div>		
</x-voll-layout>



