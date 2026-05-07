{{-- resources/views/admin/users/show.blade.php --}}
<x-voll-layout body_class="admin-users-show">
    
    {{-- =========================
	ЗАГОЛОВКИ И МЕТАДАННЫЕ
    ========================== --}}
    <x-slot name="title">
        Редактирование пользователя #{{ $user->id }} — {{ $user->name }}
	</x-slot>
    
    <x-slot name="h1">
		{{ $user->name }}
	</x-slot>  
    <x-slot name="h2">
		#{{ $user->id }}
	</x-slot>
	<x-slot name="t_description">
        Редактирование пользователя
	</x-slot>   
    {{-- Крошки --}}
    <x-slot name="breadcrumbs">
		<li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem">
			<a href="{{ route('admin.dashboard') }}" itemprop="item"><span itemprop="name">Админ-панель</span></a>
			<meta itemprop="position" content="2">
		</li>	
        <li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem">
            <a href="{{ route('admin.users.index') }}" itemprop="item">
                <span itemprop="name">Пользователи</span>
			</a>
            <meta itemprop="position" content="3">
		</li>
        <li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem">
            <a href="#" itemprop="item">
                <span itemprop="name">{{ $user->name }}</span>
			</a>
            <meta itemprop="position" content="4">
		</li>		
	</x-slot>
    
    {{-- =========================
	ОСНОВНОЙ КОНТЕНТ
    ========================== --}}
    <div class="container">
        
        {{-- =========================
		FLASH / ERRORS
        ========================== --}}
        @if (session('status'))
		<div class="ramka">	
            <div class="alert alert-success">
                {{ session('status') }}
			</div>
		</div>	
        @endif
		
        @if (session('error'))
		<div class="ramka">	
            <div class="alert alert-error">
                <strong>Ошибка:</strong> {{ session('error') }}
			</div>
		</div>	
        @endif
		
        @if ($errors->any())
		<div class="ramka">	
            <div class="alert alert-error">
                <strong>Ошибки:</strong>
                <ul class="mt-2 mb-0">
                    @foreach ($errors->all() as $e)
					<li>{{ $e }}</li>
                    @endforeach
				</ul>
			</div>
		</div>	
        @endif
        
        {{-- Основная сетка --}}
        <div class="row">
            {{-- =========================
			ЛЕВАЯ КОЛОНКА (основная информация)
            ========================== --}}
            <div class="col-lg-8">
                
                {{-- Карточка профиля --}}
                <div class="ramka">
                    <h2 class="-mt-05">Профиль</h2>
                    <div class="card-body">
                        <div class="d-flex gap-1 fvc">
							<div class="avatar-small">
								<img src="{{ $user->profile_photo_url }}" 
								alt="avatar">
							</div>
                            <div>
                                <div class="fw-bold fs-5">{{ $user->name }}</div>
                                <div class="text-muted">
                                    {{ $user->email }}
                                    @if($user->email_verified_at)
                                    <span class="text-success ms-1" title="Email подтверждён {{ $user->email_verified_at?->format('Y-m-d H:i') }}">✓</span>
                                    @endif
								</div>
                                <div class="small text-muted mt-1">
                                    Регистрация: {{ $user->created_at?->format('Y-m-d H:i') ?? '—' }}
								</div>
                                @php
								$isAnonymizedEmail = is_string($user->email) && preg_match('/^deleted_\d+@deleted\.volleyplay\.club$/', $user->email);
								$isDeleted = $user->deleted_at || $isAnonymizedEmail;
                                @endphp
                                @if($isDeleted)
                                <div class="small text-danger fw-bold mt-1">
                                    🗑 DELETED:
                                    @if($user->deleted_at)
									{{ \Carbon\Carbon::parse($user->deleted_at)->format('Y-m-d H:i') }}
                                    @else
									анонимизирован (дата не сохранена), последнее обновление {{ $user->updated_at?->format('Y-m-d H:i') ?? '—' }}
                                    @endif
								</div>
                                @endif
                                @if($user->merged_into_user_id)
                                <div class="small text-warning mt-1">
                                    🔀 Объединён с пользователем
                                    <a class="blink" href="{{ route('admin.users.show', $user->merged_into_user_id) }}">#{{ $user->merged_into_user_id }}</a>
								</div>
                                @endif
							</div>
						</div>
						
                        <div class="mt-1">
                            <div><strong>Имя/Фамилия:</strong> {{ $user->last_name }} {{ $user->first_name }}</div>
                            <div>
                                <strong>Телефон:</strong> {{ $user->phone ?? '—' }}
                                @if($user->phone && $user->phone_verified_at)
                                <span class="text-success ms-1" title="Телефон подтверждён {{ $user->phone_verified_at?->format('Y-m-d H:i') }}">✓</span>
                                @endif
							</div>
                            <div><strong>Пароль:</strong> {{ !empty($user->password) ? 'установлен' : '—' }}</div>
						</div>
                        <ul class="list mt-1">
							<li><a class="blink" href="/user/{{ $user->id }}">Публичный профиль пользователя</a></li>						
							<li><a class="blink" href="/profile/complete?user_id={{ $user->id }}">Редактировать данные пользователя</a></li>
							<li><a class="blink" href="/user/photos?user_id={{ $user->id }}">Редактировать фото пользователя</a></li>                           
						</ul>						
					</div>
				</div>
				
				{{-- Карточка провайдеров OAuth --}}
				<div class="ramka">
					<h2 class="-mt-05">Провайдеры OAuth</h2>
					<div class="card-body">
						<div class="small">
							{{-- Telegram --}}
							<div class="mb-2">
								<span class="text-muted" style="min-width:80px; display:inline-block;">Telegram:</span>
								<strong>{{ $user->telegram_id ? '✅' : '—' }}</strong>
								@if($user->telegram_username)
								<span class="text-muted">(@{{ $user->telegram_username }})</span>
								@endif
								@if($user->telegram_id)
								<span class="text-muted ms-2">id: {{ $user->telegram_id }}</span>
								@endif
							</div>
							
							{{-- VK --}}
							<div class="mb-2">
								<span class="text-muted" style="min-width:80px; display:inline-block;">VK:</span>
								<strong>{{ $user->vk_id ? '✅' : '—' }}</strong>
								@if($user->vk_id)
								<span class="text-muted ms-2">id: {{ $user->vk_id }}</span>
								@endif
								@if($user->vk_email)
								<span class="text-muted ms-2">email: {{ $user->vk_email }}</span>
								@endif
							</div>
							
							{{-- Yandex --}}
							<div class="mb-2">
								<span class="text-muted" style="min-width:80px; display:inline-block;">Yandex:</span>
								<strong>{{ $user->yandex_id ? '✅' : '—' }}</strong>
								@if($user->yandex_id)
								<span class="text-muted ms-2">id: {{ $user->yandex_id }}</span>
								@endif
								@if($user->yandex_email)
								<span class="text-muted ms-2">email: {{ $user->yandex_email }}</span>
								@endif
							</div>
							
							{{-- Google --}}
							<div class="mb-2">
								<span class="text-muted" style="min-width:80px; display:inline-block;">Google:</span>
								<strong>{{ !empty($user->google_id) ? '✅' : '—' }}</strong>
								@if(!empty($user->google_id))
								<span class="text-muted ms-2">id: {{ $user->google_id }}</span>
								@endif
							</div>
							
							{{-- Apple --}}
							<div>
								<span class="text-muted" style="min-width:80px; display:inline-block;">Apple:</span>
								<strong>{{ !empty($user->apple_id) ? '✅' : '—' }}</strong>
								@if(!empty($user->apple_id))
								<span class="text-muted ms-2">id: {{ $user->apple_id }}</span>
								@endif
							</div>
						</div>
					</div>
				</div>
				
				{{-- Каналы уведомлений (привязки для рассылок ботов) --}}
				<div class="ramka">
					<h2 class="-mt-05">Каналы уведомлений</h2>
					<div class="small">
						{{-- Telegram bot --}}
						<div class="mb-2">
							<span class="text-muted" style="min-width:120px; display:inline-block;">Telegram бот:</span>
							<strong>{{ !empty($user->telegram_notify_chat_id) ? '✅' : '—' }}</strong>
							@if(!empty($user->telegram_notify_chat_id))
							<span class="text-muted ms-2">chat_id: {{ $user->telegram_notify_chat_id }}</span>
							@endif
						</div>
						
						{{-- VK bot --}}
						<div class="mb-2">
							<span class="text-muted" style="min-width:120px; display:inline-block;">VK бот:</span>
							<strong>{{ !empty($user->vk_notify_user_id) ? '✅' : '—' }}</strong>
							@if(!empty($user->vk_notify_user_id))
							<span class="text-muted ms-2">user_id: {{ $user->vk_notify_user_id }}</span>
							@endif
						</div>
						
						{{-- MAX bot --}}
						<div>
							<span class="text-muted" style="min-width:120px; display:inline-block;">MAX:</span>
							<strong>{{ !empty($user->max_chat_id) ? '✅' : '—' }}</strong>
							@if(!empty($user->max_chat_id))
							<span class="text-muted ms-2">chat_id: {{ $user->max_chat_id }}</span>
							@endif
						</div>
					</div>
				</div>
				
				
				{{-- Admin audits --}}
				<div class="ramka">
					<h2 class="-mt-05">Admin audits (последние 50)</h2>
					
					<div class="card-body p-0">
						@if(empty($adminAudits) || count($adminAudits) === 0)
						<div class="p-3 text-muted">Пока нет.</div>
						@else
						<div class="table-scrollable mb-0">
							<div class="table-drag-indicator"></div>		
							<table class="table">
								<thead class="table-light">
									<tr>
										<th>At</th>
										<th>Action</th>
										<th>Admin</th>
										<th>Meta</th>
									</tr>
								</thead>
								<tbody>
									@foreach($adminAudits as $a)
									<tr>
										<td class="text-nowrap">{{ \Carbon\Carbon::parse($a->created_at)->format('Y-m-d H:i') }}</td>
										<td class="text-nowrap">{{ $a->action }}</td>
										<td>{{ $a->admin_id ?? '—' }}</td>
										<td>
											<div class="small text-muted" style="max-width:250px; word-break:break-all;">
												{{ is_string($a->meta ?? null) ? $a->meta : json_encode($a->meta, JSON_UNESCAPED_UNICODE) }}
											</div>
											@if(!empty($a->note))
											<div class="small text-muted mt-1">note: {{ $a->note }}</div>
											@endif
										</td>
									</tr>
									@endforeach
								</tbody>
							</table>
						</div>
						@endif
					</div>
				</div>
				
				{{-- Account link audits --}}
				<div class="ramka">
					<h2 class="-mt-05">Account link audits (последние 50)</h2>
					
					<div class="card-body p-0">
						@if(empty($linkAudits) || count($linkAudits) === 0)
						<div class="p-3 text-muted">Пока нет.</div>
						@else
						<div class="table-scrollable mb-0">
							<div class="table-drag-indicator"></div>		
							<table class="table">
								<thead class="table-light">
									<tr>
										<th>At</th>
										<th>Action</th>
										<th>Provider</th>
										<th>Meta</th>
									</tr>
								</thead>
								<tbody>
									@foreach($linkAudits as $a)
									<tr>
										<td class="text-nowrap">{{ \Carbon\Carbon::parse($a->created_at)->format('Y-m-d H:i') }}</td>
										<td class="text-nowrap">{{ $a->action ?? '—' }}</td>
										<td class="text-nowrap">{{ $a->provider ?? '—' }}</td>
										<td>
											<div class="small text-muted" style="max-width:250px; word-break:break-all;">
												{{ is_string($a->meta ?? null) ? $a->meta : json_encode($a->meta, JSON_UNESCAPED_UNICODE) }}
											</div>
										</td>
									</tr>
									@endforeach
								</tbody>
							</table>
						</div>
						@endif
					</div>
				</div>
			</div>
			
			{{-- =========================
			ПРАВАЯ КОЛОНКА (действия)
			========================== --}}
			<div class="col-lg-4">
				
				{{-- Карточка роли --}}
				<div class="ramka">
					<h2 class="-mt-05">Роль пользователя</h2>
					<div class="card-body">
						<form method="POST" action="{{ route('admin.users.role.update', $user) }}" class="form">
							@csrf
							<select name="role" class="form-control mb-2">
								@foreach($roles as $r)
								<option value="{{ $r }}" @selected(($user->role ?? 'user') === $r)>{{ $r }}</option>
								@endforeach
							</select>
							<button class="btn btn-primary w-100 mt-2" type="submit">
								Сохранить роль
							</button>
							<div class="small text-muted mt-2">
								Все изменения логируются в admin_audits
							</div>
						</form>
					</div>
				</div>
				
				{{-- Привязка Staff к организатору --}}
				@if(true) {{-- всегда показываем для Админа --}}
				<div class="ramka">
					<h2 class="-mt-05">🧑‍💻 Привязка Staff</h2>
					<div class="card-body">
						@php
						$staffAssignment = \App\Models\StaffAssignment::where('staff_user_id', $user->id)->with('organizer')->first();
						@endphp
						@if($staffAssignment)
						<div class="alert alert-info mb-2">
							Привязан к: <strong>{{ trim($staffAssignment->organizer->first_name . ' ' . $staffAssignment->organizer->last_name) }}</strong>
							(#{{ $staffAssignment->organizer_id }})
						</div>
						<form method="POST" action="{{ route('staff.destroy', $staffAssignment->id) }}">
							@csrf @method('DELETE')
							<button type="submit" class="btn btn-danger btn-small w-100"
							onclick="return confirm('Снять с должности?')">Снять Staff</button>
						</form>
						@else
						<div class="alert alert-info mb-2">Не привязан ни к одному организатору.</div>
						<form class="form" method="POST" action="{{ route('staff.store') }}" id="adminStaffForm">
							@csrf
							<input type="hidden" name="staff_user_id" value="{{ $user->id }}">
							<input type="hidden" name="organizer_id_override" id="admin_org_id_input">
							<label class="f-14 b-600">Выбрать организатора</label>
							<input type="text" id="admin_org_search"
							placeholder="Введите имя или email организатора..."
							autocomplete="off" class="w-100 mt-1">
							<div id="admin_org_results" style="display:none;border:0.1rem solid var(--border-color,#eee);border-radius:0.8rem;overflow:hidden;max-height:20rem;overflow-y:auto;"></div>
							<div id="admin_org_selected" class="f-14 mt-1" style="display:none;"></div>
							<button type="submit" class="btn w-100 mt-1" id="admin_org_submit" disabled>Привязать</button>
						</form>
						<script>
							(function() {
								const search  = document.getElementById('admin_org_search');
								const results = document.getElementById('admin_org_results');
								const hidden  = document.getElementById('admin_org_id_input');
								const selected= document.getElementById('admin_org_selected');
								const submit  = document.getElementById('admin_org_submit');
								let timer = null;
								
								function pick(id, name) {
									hidden.value = id;
									search.value = name;
									results.style.display = 'none';
									selected.style.display = '';
									selected.innerHTML = '<span style="color:var(--cd)">✅</span> <strong>' + name + '</strong> <span style="opacity:.5;">#' + id + '</span>';
									submit.disabled = false;
								}
								
								search.addEventListener('input', function() {
									hidden.value = ''; submit.disabled = true; selected.style.display = 'none';
									clearTimeout(timer);
									const q = this.value.trim();
									if (q.length < 2) { results.style.display = 'none'; return; }
									timer = setTimeout(async function() {
										const res  = await fetch('/api/users/search?q=' + encodeURIComponent(q) + '&roles=organizer,admin');
										const data = await res.json();
										if (!data.ok || !data.items.length) {
											results.innerHTML = '<div class="p-2 f-14" style="opacity:.6;">Ничего не найдено</div>';
											results.style.display = ''; return;
										}
										results.innerHTML = data.items.slice(0, 8).map(u => {
											const name = u.full_name || u.label || u.name || ('#' + u.id);
											return '<div class="org-pick-item" data-id="' + u.id + '" data-name="' + name + '"'
											+ ' style="padding:.8rem 1.2rem;cursor:pointer;border-bottom:.1rem solid var(--border-color,#eee);">'
											+ '<div class="b-600">' + name + '</div>'
											+ '<div class="f-13" style="opacity:.6;">#' + u.id + ' · ' + u.role + '</div>'
											+ '</div>';
										}).join('');
										results.style.display = '';
										results.querySelectorAll('.org-pick-item').forEach(el => {
											el.addEventListener('mouseenter', () => el.style.background = 'var(--bg2,#f5f5f5)');
											el.addEventListener('mouseleave', () => el.style.background = '');
											el.addEventListener('click', () => pick(el.dataset.id, el.dataset.name));
										});
									}, 300);
								});
								
								document.addEventListener('click', e => {
									if (!results.contains(e.target) && e.target !== search) results.style.display = 'none';
								});
							})();
						</script>
						@endif
					</div>
				</div>
				@endif
				
				{{-- Ограничения на мероприятия --}}
				<div class="ramka">
					<h2 class="-mt-05">Ограничения (events)</h2>
					<div class="card-body">
						@php
						$restrictions = $restrictions ?? [];
						$hasRestrictions = is_countable($restrictions) && count($restrictions) > 0;
						@endphp
						
						@if(!$hasRestrictions)
						<div class="alert alert-success">
							<strong>Активных ограничений нет</strong><br>
							<small>Пользователь может записываться на все мероприятия.</small>
						</div>
						@else
						<div class="alert alert-warning">
							<strong>Есть активные ограничения</strong>
						</div>
						
						@foreach($restrictions as $r)
						@php
						$until = $r->ends_at
						? \Carbon\Carbon::parse($r->ends_at)->format('d.m.Y H:i')
						: 'пожизненно';
						$ids = [];
						if (!empty($r->event_ids)) {
						$decoded = is_string($r->event_ids) ? json_decode($r->event_ids, true) : $r->event_ids;
						$ids = is_array($decoded) ? $decoded : [];
						}
						@endphp
						
						<div class="mt-2">
							<div class="small mb-2">
								<strong>Действует до:</strong> 
								<span>{{ $until }}</span>
							</div>
							<div class="small mb-2">
								<strong>Events:</strong>
								@if(count($ids))
								@foreach($ids as $eid)
								<span>#{{ (int)$eid }}</span>
								@endforeach
								@else
								<span class="text-muted">—</span>
								@endif
							</div>
							<div class="small mb-2">
								<strong>Причина:</strong> {{ $r->reason ?: '—' }}
							</div>
							<div class="small text-muted">
								Создано: {{ \Carbon\Carbon::parse($r->created_at)->format('Y-m-d H:i') }}
							</div>
						</div>
						@endforeach
						@endif
						
						
						<h4 class="h6 mb-2">Установить блокировку</h4>
						<form method="POST" action="{{ route('admin.users.restrictions.events', $user) }}" class="form">
							@csrf
							<div class="mb-2">
								<label class="small text-muted">Event IDs (числа через запятую)</label>
								<input class="form-control" type="text" name="event_ids" placeholder="12, 18, 25" required />
							</div>
							
							<div class="mb-2">
								
								<label class="radio-item mb-2">
									<input type="radio" name="mode" value="forever" checked>
									<div class="custom-radio"></div>
									<span>Пожизненно</span>
								</label>
								
								<label class="radio-item">
									<input type="radio" name="mode" value="until">
									<div class="custom-radio"></div>
									<span>До даты</span>
								</label>
							</div>
							
							<div class="mb-2">
								<label class="small text-muted">Дата окончания (если выбрано "До даты")</label>
								<input class="form-control" type="datetime-local" name="until" />
							</div>
							
							<div class="mb-2">
								<label class="small text-muted">Причина (опционально)</label>
								<input class="form-control" type="text" name="reason" placeholder="Напр.: запрет на конкретные турниры" />
							</div>
							
							<button class="btn btn-primary w-100" type="submit">
								Установить ограничение
							</button>
						</form>
						
						{{-- Кнопка снятия ограничений --}}
						
						<form method="POST" action="{{ route('admin.users.restrictions.clear', $user) }}" 
						onsubmit="if (!confirm('Вы точно хотите снять ВСЕ активные ограничения?')) return false; const v = prompt('Для подтверждения введите: yes'); return (v || '').trim().toLowerCase() === 'yes';">
							@csrf
							<input type="hidden" name="confirm" value="yes">
							<button class="btn btn-danger w-100 mt-2" type="submit">
								Снять все ограничения
							</button>
						</form>
					</div>
				</div>
				
				{{-- Danger zone --}}
				<div class="ramka">
					<h2 class="-mt-05">Danger zone</h2>
					<div class="card-body form">
						<form method="POST" action="{{ route('admin.users.purge', $user) }}" id="purge-form">
							@csrf
							@method('DELETE')
							<input type="hidden" name="confirm" value="yes">
							
							<label class="small text-muted">Комментарий (почему удаляем)</label>
							<textarea name="note" class="form-control" rows="3" placeholder="Причина удаления"></textarea>
							
							<button type="button" class="btn btn-danger w-100 mt-2" onclick="sweetPurge()">
								Полное удаление пользователя!
							</button>
							<div class="small text-muted mt-2">
								Действие необратимо.
							</div>
						</form>
					</div>
				</div>
				
			</div>
		</div>
	</div>
	
	{{-- =========================
	СКРИПТЫ
	========================== --}}
	<x-slot name="script">
		<script>
			function sweetPurge() {
				swal({
					title: 'Точно удалить?',
					text: 'Это действие нельзя отменить! Все данные будут потеряны.',
					icon: 'warning',
					buttons: {
						cancel: 'Отмена',
						confirm: {
							text: 'Удалить',
							value: 'delete',
							className: 'btn-danger'
						}
					},
					dangerMode: true,
					}).then((value) => {
					if (value === 'delete') {
						// Второй шаг - подтверждение
						swal({
							title: 'Последний шаг',
							content: 'input',
							text: 'Введите "yes" для подтверждения',
							buttons: {
								cancel: 'Отмена',
								confirm: 'Подтвердить'
							},
							}).then((inputValue) => {
							if (inputValue && inputValue.trim().toLowerCase() === 'yes') {
								document.getElementById('purge-form').submit();
								} else {
								swal('Отмена', 'Удаление отменено', 'info');
							}
						});
					}
				});
			}
		</script>
	</x-slot>
	
</x-voll-layout>	