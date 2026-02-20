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
			<a href="{{ route('admin.dashboard') }}" itemprop="item"><span itemprop="name">Админка</span></a>
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
		
	</x-slot>	
	
    <div class="container">
		<div class="users-filter">
			<div class="ramka">
				<div class="form">
					<form method="GET" action="{{ route('admin.users.index') }}">
						{{-- Search --}}
						
						<div class="row">
							<div class="col-12 col-md-6">
								
								<input 
								name="q"
								value="{{ $q ?? '' }}"
								placeholder="Поиск: имя/фамилия/email/tg/vk/yandex" />
								
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
							<a href="{{ route('admin.users.show', $u) }}">
								{{ $u->name }}
							</a>
							<div class="f-15">{{ $u->email }}</div>
							<div class="f-15">
								{{ $u->last_name }} {{ $u->first_name }}
							</div>
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
		{{ $users->links() }}
		</div>	
	</div>		
</x-voll-layout>



