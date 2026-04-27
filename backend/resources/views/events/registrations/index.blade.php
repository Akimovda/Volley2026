@php
$posLabels = $availablePositions ?? [
'setter'   => 'Связующий',
'outside'  => 'Доигровщик',
'opposite' => 'Диагональный',
'middle'   => 'Центральный',
'libero'   => 'Либеро',
];

$isBeach   = ($direction ?? 'classic') === 'beach';
$isClassic = !$isBeach;
$hasPositions = $isClassic && count($posLabels) > 0;

$eventTitle = (string)($event->title ?? 'Мероприятие');

$addrParts = array_filter([
$event->location?->city?->name,
$event->location?->address,
$event->location?->name,
]);
$address = $addrParts ? implode(', ', $addrParts) : '—';

$dateLine = '—';
if ($startsLocal) {
$dateLine = $startsLocal->translatedFormat('l, j F') . ' @ ' . $startsLocal->format('H:i');
if ($endsLocal) $dateLine .= ' - ' . $endsLocal->format('H:i');
}

$capacityLine = ($maxPlayers > 0) ? "{$freeCount}/{$maxPlayers}" : "—/—";

$statusText = function ($r) {
if (property_exists($r, 'cancelled_at') && $r->cancelled_at) return 'отменено';
if (property_exists($r, 'is_cancelled') && !is_null($r->is_cancelled) && (bool)$r->is_cancelled) return 'отменено';
if (property_exists($r, 'status') && (string)$r->status === 'cancelled') return 'отменено';
return 'подтверждено';
};

$isCancelled = function ($r) use ($statusText) {
return $statusText($r) === 'отменено';
};

$activeRegistrations = $registrations->filter(fn($r) => !$isCancelled($r))->values();
$searchUrl = route('api.users.search');
@endphp

<x-voll-layout body_class="registrations-page">
	
    <x-slot name="title">Управление записью — {{ $eventTitle }}</x-slot>
    <x-slot name="h1">Управление записью</x-slot>
    <x-slot name="t_description">{{ $eventTitle }}</x-slot>
	
    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('events.create.event_management') }}" itemprop="item"><span itemprop="name">Мои мероприятия</span></a>
            <meta itemprop="position" content="2">
		</li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('events.show', $event->id) }}" itemprop="item"><span itemprop="name">{{ $eventTitle }}</span></a>
            <meta itemprop="position" content="3">
		</li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">Запись</span>
            <meta itemprop="position" content="4">
		</li>
	</x-slot>
	
    <x-slot name="d_description">
        <div class="d-flex between fvc mt-1" style="flex-wrap:wrap;gap:1rem;">
            <div class="f-16">
                @if($isBeach)
				🏖 Пляжный волейбол
                @else
				🏐 Классика @if(!empty($gameSubtype)) · {{ $gameSubtype }} @endif
                @endif
			</div>
            <div class="d-flex gap-1">
                <a href="{{ route('events.create.event_management') }}" class="btn btn-secondary">← К управлению</a>
                <a href="{{ route('events.show', $event->id) }}" class="btn btn-secondary">← К мероприятию</a>
			</div>
		</div>
	</x-slot>
	
    <x-slot name="style">
		<style>


			.badge { display: inline-block; padding: .3rem 1rem; border-radius: 2rem; font-size: 1.5rem; font-weight: 600; }
			.badge-ok  { background: rgba(76,175,80,.12); color: #2e7d32; }
			.badge-err { background: rgba(244,67,54,.12);  color: #c62828; }
			.summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 2rem; }
			@media (max-width: 768px) { .summary-grid { grid-template-columns: 1fr; } }
			@media (min-width: 769px) {  
			.table-scrollable {
			overflow: unset;
			}
			}			
			
			
		</style>
	</x-slot>
	
    <div class="container form">
		
        {{-- Flash --}}
        @if(session('status'))
        <div class="ramka">
			<div class="alert alert-success">
				{{ session('status') }}
			</div>
		</div>
        @endif
        @if(session('error'))
        <div class="ramka">
			<div class="alert alert-error">
				{{ session('error') }}
			</div>
		</div>
        @endif
		
        {{-- Сводка --}}
        <div class="ramka">
            <div class="summary-grid">
                <div class="card text-center">
                    <div>Свободных мест</div>
                    <div class="f-22 b-600 cd">{{ $capacityLine }}</div>
                    <div>Активных: {{ $activeCount }}</div>
				</div>
                <div class="card text-center">
                    <div>Дата</div>
                    <div class="b-600">{{ $dateLine }}</div>
                    <div>{{ $tz }}</div>
				</div>
                <div class="card text-center">
                    <div>Место</div>
                    <div class="b-600">{{ $address }}</div>
				</div>
			</div>
		</div>
		
        {{-- Добавить игрока --}}
        <div class="ramka" style="z-index:6">
            <h2 class="-mt-05">Добавить игрока</h2>
            <p>
                Начните вводить имя или email. Введите <strong>bot</strong> для поиска ботов-помощников.
			</p>
            <form method="POST"
			action="{{ route('events.registrations.add', ['event' => $event->id]) }}"
			id="add-player-form"
			class="form">
                @csrf
                <div class="row">
				
                    <div class="col-md-6">
					<div class="card">
                        <label>Игрок</label>
                        <div style="position:relative;" id="ac-wrap">
                            <input type="text" id="ac-input" autocomplete="off"
							placeholder="Имя, email или «bot»…">
                            <input type="hidden" name="user_id" id="ac-userid">
                            <div id="ac-dd" style="display:none;position:absolute;left:0;right:0;top:100%;margin-top:.4rem;z-index:50;background:var(--bg-card,#fff);border:0.1rem solid var(--border-color,#eee);border-radius:1.2rem;box-shadow:0 1rem 3rem rgba(0,0,0,.1);max-height:24rem;overflow-y:auto;"></div>
						</div>
                        <div id="ac-selected" style="display:none;margin-top:.5rem;font-size:1.4rem;color:#4caf50;font-weight:600;"></div>
					</div>
					</div>
                    @if($hasPositions)
                    <div class="col-md-6">
				<div class="card">
                        <label>Позиция</label>
                        <select name="position">
                            <option value="">— без позиции —</option>
                            @foreach($posLabels as $k => $lbl)
                            <option value="{{ $k }}">{{ $lbl }}</option>
                            @endforeach
						</select>
					</div>
					</div>
                    @endif
				</div>
				<div class="text-center mt-2">
				        <button type="submit" id="add-player-btn" disabled
						class="btn" style="opacity:.4;">
                            Добавить
						</button>
				</div>		
				
				
			</form>
		</div>
		
        {{-- Таблица игроков --}}
        <div class="ramka" style="z-index:5">
            <h2 class="-mt-05">Зарегистрированные игроки</h2>
			
            @if($registrations->isEmpty())
            <div class="alert alert-info">
                Пока никто не записан
			</div>
            @else
			<div class="table-scrollable mb-0">
				<div class="table-drag-indicator"></div>
				<table class="table">
                    <thead>
                        <tr>
                            <th>Игрок</th>
                            <th>Телефон</th>
                            @if($hasPositions)<th>Позиция</th>@endif
                            @if($isBeach)<th>Группа</th>@endif
                            <th>Статус</th>
                            @if($hasOrgNote ?? false)<th style="min-width:160px">Комментарий</th>@endif
                            <th class="text-center">Действия</th>
						</tr>
					</thead>
                    <tbody>
                        @foreach($registrations as $r)
                        @php
						$name     = $r->name ?: ($r->email ?: ('User_' . $r->user_id));
						$phone    = $r->phone ?: '—';
						$posKey   = $r->position ?: '';
						$posLabel = $posKey ? ($posLabels[$posKey] ?? $posKey) : '—';
						$st       = $statusText($r);
						$groupKey = $r->group_key ?: '';
						$isBot    = !empty($r->is_bot);
                        @endphp
                        <tr class="{{ $st === 'отменено' ? 'cancelled' : '' }}">
                            <td>
                                <div class="d-flex fvc gap-1">
                                    <a href="{{ route('users.show', $r->user_id) }}" class="b-600 blink">
                                        {{ $name }}
									</a>
                                    @if($isBot)<span title="Бот">🤖</span>@endif
								</div>
                                <p class="pt-1">#{{ $r->user_id }} · reg #{{ $r->id }}</p>
							</td>
                            <td>{{ $phone }}</td>
                            @if($hasPositions)
                            <td>
						{{--
                                <div class="f-16 mb-05">{{ $posLabel }}</div>
						--}}		
                                <form class="d-flex gap-1 fvc" method="POST"
								action="{{ route('events.registrations.position', ['event' => $event->id, 'registration' => $r->id]) }}">
                                    @csrf @method('PATCH')
                                    <select name="position">
                                        <option value="">— без —</option>
                                        @foreach($posLabels as $k => $lbl)
                                        <option value="{{ $k }}" @selected($posKey === $k)>{{ $lbl }}</option>
                                        @endforeach
									</select>
                                    <button class="btn btn-secondary" type="submit">✓</button>
								</form>
							</td>
                            @endif
                            @if($isBeach)
                            <td>
                                @if($groupKey)
                                <div class="b-600">{{ $groupKey }}</div>
                                @if($st !== 'отменено')
                                <form method="POST" action="{{ route('events.registrations.group.leave', ['event' => $event->id, 'registration' => $r->id]) }}">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-small btn-secondary mt-05">Убрать</button>
								</form>
                                @endif
                                @else
                                @if($st !== 'отменено')
                                <form method="POST" action="{{ route('events.registrations.group.create', ['event' => $event->id, 'registration' => $r->id]) }}">
                                    @csrf
                                    <button class="btn btn-small btn-secondary">+ Группа</button>
								</form>
                                @endif
                                @endif
							</td>
                            @endif
                            <td class="text-center">
                                <span class="f-15 p-1 pt-05 pb-05 {{ $st === 'отменено' ? 'alert-error' : 'alert-success' }}">
                                    {{ $st }}
								</span>
							</td>
                            @if($hasOrgNote ?? false)
                            <td class="align-top">
                                <textarea class="org-note-input" rows="1"
                                    style="width:100%;min-width:140px;font-size:1.3rem;resize:none;overflow:hidden;border:1px solid #e2e8f0;border-radius:.6rem;padding:.4rem .6rem;height:2.4rem;min-height:2.4rem;box-sizing:border-box;"
                                    data-url="{{ route('events.registrations.note', ['event' => $event->id, 'registration' => $r->id]) }}"
                                    placeholder="Заметка…">{{ $r->organizer_note ?? '' }}</textarea>
                            </td>
                            @endif
                            <td>
                                <div class="d-flex gap-1 text-center">
<form method="POST"
    action="{{ route('events.registrations.cancel', ['event' => $event->id, 'registration' => $r->id]) }}">
    @csrf @method('PATCH')
    
    <button class="btn {{ $st === 'отменено' ? 'btn-svg icon-copy' : 'btn-danger btn-svg icon-stop' }}" 
            title="{{ $st === 'отменено' ? 'Восстановить' : 'Отклонить' }}">
    </button>
</form>
                                    <form method="POST"
									action="{{ route('events.registrations.destroy', ['event' => $event->id, 'registration' => $r->id]) }}"
									onsubmit="return confirm('Удалить регистрацию полностью?');">
                                        @csrf @method('DELETE')
										<button type="submit" 
										class="icon-delete btn-alert btn btn-danger btn-svg"
										data-title="Удалить регистрацию?"
										data-text="Регистрация будет удалена полностью"
										data-icon="warning"
										data-confirm-text="Да, удалить"
										data-cancel-text="Отмена">
										</button>	
									</form>
								</div>
							</td>
						</tr>
                        @endforeach
					</tbody>
				</table>
			</div>
            @endif
		</div>
		
        {{-- Группы (только пляжка) --}}
        @if($isBeach)
        <div class="ramka">
            <h2 class="-mt-05">Объединить в пару</h2>
            <form method="POST"
			action="{{ route('events.registrations.group.invite', ['event' => $event->id]) }}"
			class="form">
                @csrf
                <div class="row row2">
                    <div class="col-md-4">
                        <label>Первый игрок</label>
                        <select name="from_user_id" required>
                            <option value="">— выбрать —</option>
                            @foreach($activeRegistrations as $r)
                            <option value="{{ $r->user_id }}">#{{ $r->user_id }} — {{ $r->name ?? '' }}</option>
                            @endforeach
						</select>
					</div>
                    <div class="col-md-4">
                        <label>Второй игрок</label>
                        <select name="to_user_id" required>
                            <option value="">— выбрать —</option>
                            @foreach($activeRegistrations as $r)
                            <option value="{{ $r->user_id }}">#{{ $r->user_id }} — {{ $r->name ?? '' }}</option>
                            @endforeach
						</select>
					</div>
                    <div class="col-md-4 d-flex" style="align-items:flex-end;">
                        <button class="btn btn-secondary w-100">Отправить приглашение</button>
					</div>
				</div>
			</form>
		</div>
        @endif

        {{-- Экспорт --}}
        <div class="ramka d-flex gap-2 justify-content-center flex-wrap">
            <a href="{{ route('events.registrations.pdf', ['event' => $event->id]) }}"
               class="btn btn-secondary">
                ⬇ Скачать PDF
            </a>
            <a href="{{ route('events.registrations.txt', ['event' => $event->id]) }}"
               class="btn btn-secondary">
                ⬇ Скачать TXT
            </a>
        </div>

	</div>
	
    <x-slot name="script">
		<script>
			(function () {
				var input   = document.getElementById('ac-input');
				var dd      = document.getElementById('ac-dd');
				var hidden  = document.getElementById('ac-userid');
				var sel     = document.getElementById('ac-selected');
				var addBtn  = document.getElementById('add-player-btn');
				var timer   = null;
				var searchUrl = '{{ $searchUrl }}';
				
				if (!input) return;
				
				function clearSel() {
					hidden.value = '';
					addBtn.disabled = true;
					addBtn.style.opacity = '.4';
					sel.style.display = 'none';
				}
				
				function setSel(id, label) {
					hidden.value = id;
					addBtn.disabled = false;
					addBtn.style.opacity = '1';
					sel.style.display = 'block';
					sel.textContent = '✅ ' + label;
					dd.innerHTML = '';
					dd.style.display = 'none';
					input.value = label.replace(/^🤖\s*/, '');
				}
				
				function esc(s) {
					return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
				}
				
				function render(items) {
					dd.innerHTML = '';
					if (!items.length) {
						dd.innerHTML = '<div style="padding:1.2rem 1.6rem;font-size:1.5rem;opacity:.5;">Ничего не найдено</div>';
						dd.style.display = 'block';
						return;
					}
					items.forEach(function(item) {
						var div = document.createElement('div');
						div.style.cssText = 'padding:1rem 1.6rem;cursor:pointer;font-size:1.5rem;border-bottom:0.1rem solid var(--border-color,#eee);display:flex;justify-content:space-between;align-items:center;';
						div.innerHTML =
						'<span class="b-500">' + (item.is_bot ? '🤖 ' : '') + esc(item.label || item.name) + '</span>' +
						(item.meta ? '<span style="font-size:1.3rem;opacity:.5;">' + esc(item.meta) + '</span>' : '');
						div.addEventListener('mouseover', function() { this.style.background = 'var(--bg-hover,#f5f5f5)'; });
						div.addEventListener('mouseout',  function() { this.style.background = ''; });
						div.addEventListener('click', function() {
							setSel(item.id, (item.is_bot ? '🤖 ' : '') + (item.label || item.name));
						});
						dd.appendChild(div);
					});
					dd.style.display = 'block';
				}
				
				function search(q) {
					fetch(searchUrl + '?q=' + encodeURIComponent(q), {
						headers: { 'Accept': 'application/json' },
						credentials: 'same-origin'
					})
					.then(function(r) { return r.json(); })
					.then(function(data) { render(data.items || []); })
					.catch(function() {
						dd.innerHTML = '<div style="padding:1.2rem 1.6rem;color:#e53935;">Ошибка поиска</div>';
						dd.style.display = 'block';
					});
				}
				
				input.addEventListener('input', function() {
					clearSel();
					clearTimeout(timer);
					var q = input.value.trim();
					if (q.length < 2) { dd.style.display = 'none'; return; }
					dd.innerHTML = '<div style="padding:1.2rem 1.6rem;font-size:1.5rem;opacity:.5;">Поиск…</div>';
					dd.style.display = 'block';
					timer = setTimeout(function() { search(q); }, 250);
				});
				
				document.addEventListener('click', function(e) {
					if (!document.getElementById('ac-wrap').contains(e.target)) {
						dd.style.display = 'none';
					}
				});
				
				document.getElementById('add-player-form').addEventListener('submit', function(e) {
					if (!hidden.value) {
						e.preventDefault();
						input.focus();
					}
				});
			})();

			// --- Авторасширение textarea ---
			function autoResize(el) { el.style.height = '2.4rem'; if (el.scrollHeight > el.offsetHeight) el.style.height = el.scrollHeight + 'px'; }
			$(document).on('input', '.org-note-input', function() { autoResize(this); });
			$('.org-note-input').each(function() { autoResize(this); });

			// --- Автосохранение комментария организатора (blur) ---
			$(document).on('blur', '.org-note-input', function() {
				var ta  = $(this);
				var url = ta.data('url');
				var val = ta.val();
				$.ajax({
					url: url,
					method: 'POST',
					headers: {
						'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
						'X-HTTP-Method-Override': 'PATCH',
						'Accept': 'application/json',
					},
					data: { organizer_note: val, _method: 'PATCH' },
					success: function() {
						ta.css('border-color', '#10b981');
						setTimeout(function() { ta.css('border-color', ''); }, 1500);
					},
					error: function() {
						ta.css('border-color', '#ef4444');
					}
				});
			});
		</script>
	</x-slot>
	
</x-voll-layout>
