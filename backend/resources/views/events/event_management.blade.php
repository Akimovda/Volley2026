{{-- resources/views/events/event_management.blade.php --}}
@php
/**
* ВАЖНО:
* - В tab=mine актуальность идёт по next_occurrence_starts_at (если есть),
*   поэтому в колонке "Дата и время" показываем NEXT как основную дату.
* - events.starts_at (root) может быть в прошлом у recurring — показываем вторичной строкой.
*/

$tabs = [
'archive' => 'Архив',
'mine'    => 'Мои',
];

// ✅ ROOT дата (events.starts_at/ends_at) — может быть в прошлом у recurring
$fmtRootDt = function ($event) {
$tz = $event->timezone ?: 'UTC';
$s = $event->starts_at ? $event->starts_at->copy()->setTimezone($tz) : null;
$e = $event->ends_at ? $event->ends_at->copy()->setTimezone($tz) : null;
if (!$s) return '—';
$date = $s->format('d.m.Y');
$time = $s->format('H:i') . ($e ? '–' . $e->format('H:i') : '');
return $date . ' · ' . $time . ' (' . $tz . ')';
};

// ✅ Форматтер для серии (начало и конец серии)
$fmtSeriesDt = function ($event) {
$tz = $event->timezone ?: 'UTC';

$first = $event->starts_at ? $event->starts_at->copy()->setTimezone($tz) : null;
if (!$first) return '—';

$lastUtc = $event->last_occurrence_starts_at ?? null;
$last = $lastUtc ? \Carbon\Carbon::parse($lastUtc, 'UTC')->setTimezone($tz) : null;

if ($last) {
return $first->format('d.m.Y') . ' — ' . $last->format('d.m.Y') . ' (' . $tz . ')';
}

return 'с ' . $first->format('d.m.Y') . ' (' . $tz . ')';
};

$fmtLocation = function ($event) {
$parts = array_filter([
$event->location?->name,
$event->location?->city?->name,
$event->location?->address,
]);
return $parts ? implode(', ', $parts) : '—';
};

$seatMeta = function ($event) {
$max = (int)($event->max_players ?? 0);
$registered = (int)($event->active_regs ?? 0);

if (!(bool)$event->allow_registration) {
return ['label' => 'Регистрация выключена', 'free' => null, 'max' => null, 'registered' => $registered];
}
if ($max <= 0) {
return ['label' => 'Мест: —', 'free' => null, 'max' => null, 'registered' => $registered];
    }
    
    $free = max(0, $max - $registered);
    return ['label' => "Мест: {$free}/{$max}", 'free' => $free, 'max' => $max, 'registered' => $registered];
	};
	
	// ✅ Исправленный детектор повторяющихся мероприятий
	$isRecurringEvent = function ($event) {
    return (bool)($event->is_recurring ?? false)
	|| trim((string)($event->recurrence_rule ?? '')) !== '';
	};
	
	$isAdmin = (auth()->user()?->role ?? null) === 'admin';
	$organizerFilter = (int)($organizerFilter ?? request()->query('organizer_id', 0));
	@endphp
	
	<x-voll-layout>
		<x-slot name="title">Управление мероприятиями</x-slot>
		<x-slot name="h1">Управление мероприятиями</x-slot>
		
		<x-slot name="breadcrumbs">
			<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
				<a href="{{ route('events.create.event_management') }}" itemprop="item">
					<span itemprop="name">Управление мероприятиями</span>
				</a>
				<meta itemprop="position" content="2">
			</li>
		</x-slot>    
		
		<x-slot name="t_description">Быстрое создание копии ("Создать копию"), а также доступ к регистрации.</x-slot>    
		
		<x-slot name="d_description">
			<div data-aos-delay="250" data-aos="fade-up">
				<a href="{{ route('events.create') }}" class="mt-2 btn btn-outline-secondary">
					Создать новое
				</a>
			</div>        
		</x-slot>    
		
		<div class="container">
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
					{{ session('error') }}
				</div>
			</div>
			@endif        
			
			<div class="ramka">
				<div class="row row2">
					<div class="col-md-6">
						<div class="tabs-content">
							<div class="tabs">
								@foreach($tabs as $key => $label)
								<a href="{{ route('events.create.event_management', array_merge(['tab' => $key], request()->only('organizer_id'))) }}" class="tab {{ $tab === $key ? 'active' : '' }}">
									{{ $label }}
								</a>
								@endforeach                    
								<div class="tab-highlight"></div>
							</div>                
						</div>
					</div>    
					
					{{-- Admin: filter by organizer --}}
					@if($isAdmin)
					<div class="col-md-6">    
						<div class="form">
							<div class="card mb-2">
								<form method="GET" action="{{ route('events.create.event_management') }}">
									<input type="hidden" name="tab" value="{{ $tab }}">
									<label>Организатор:</label>
									<select name="organizer_id" onchange="this.form.submit()">
										<option value="0">Все</option>
										@foreach(($organizers ?? []) as $o)
										<option value="{{ (int)$o->id }}" {{ $organizerFilter === (int)$o->id ? 'selected' : '' }}>
											#{{ (int)$o->id }} — {{ $o->name ?: $o->email }}
										</option>
										@endforeach
									</select>
									
									@if($organizerFilter > 0)
									<a href="{{ route('events.create.event_management', ['tab' => $tab]) }}" class="mt-1 blink">
										Сбросить
									</a>
									@endif
								</form>
							</div>    
						</div>
					</div>
					@endif
				</div>
				
				@if($events->isEmpty())
				<div class="alert alert-info">
					@if($tab === 'mine')
					Актуальных мероприятий нет — проверь вкладку «Архив».
					@else
					Здесь пока пусто.
					@endif
				</div>
				@else
				{{-- BULK ACTIONS UI --}}
				<div class="row form row2 mb-1">
					<div class="col-md-6 mb-1">
						<label class="checkbox-item">
							<input type="checkbox" id="bulkSelectAll">
							<div class="custom-checkbox"></div>
							<span>Выбрать всё на странице (выбрано: <span id="bulkSelectedCount" class="cd b-600">0</span>)</span>
						</label>
					</div>
					<div class="col-md-6 text-right mb-1">
						<button type="button" id="bulkCancelBtn" class="btn btn-small" disabled>Отменить выбранные</button>
						
						@if($isAdmin)
						<button type="button" id="bulkForceDeleteBtn" class="btn btn-small" disabled>Удалить навсегда</button>
						@endif
					</div>
				</div>
				
				<div class="form table-scrollable mb-0">
					<div class="table-drag-indicator"></div>
					<table class="table">
						<colgroup>
							<col style="width:4rem" />
							<col style="width:7rem" />
							<col style="width:34%" />
							<col />
							<col style="width:18%" />
							@if($isAdmin)
							<col style="width:18%" />
							@endif
							<col style="width:18rem" />
						</colgroup>
						
						<thead class="bg-gray-50 text-gray-600">
							<tr>
								<th></th>
								<th>ID</th>
								<th>Название</th>
								<th>Местоположение</th>
								<th>Дата и время</th>
								@if($isAdmin)
								<th>Организатор</th>
								@endif
								<th>Действия</th>
							</tr>
						</thead>
						
						<tbody>
							@foreach($events as $event)
							@php
							$org = $event->organizer;
							$seat = $seatMeta($event);
							
							$isRecurring = $isRecurringEvent($event);
							$hasNext = !empty($event->next_occurrence_starts_at ?? null);
							
							$tz = $event->timezone ?: 'UTC';
							$nextLocal = $hasNext
                            ? \Carbon\Carbon::parse($event->next_occurrence_starts_at, 'UTC')->setTimezone($tz)
                            : null;
							@endphp
							
							<tr>
								<td class="align-top">
									<label class="checkbox-item">
										<input type="checkbox" class="bulkItem" value="{{ (int)$event->id }}">
										<div class="custom-checkbox mr-0"></div>
									</label>        
								</td>
								
								<td class="nowrap align-top">
									#{{ (int)$event->id }}
								</td>
								
								<td class="align-top">
									<div class="d-flex">
										@if($isRecurring)
										<span class="emo" title="Повторяющееся мероприятие">🔁</span>
										@endif    
										<div>
											<a class="blink" href="{{ url('/events/' . (int)$event->id) }}">{{ $event->title }}</a>
											<div class="f-16 pt-1">
												{{ strtoupper((string)$event->direction) }} · {{ (string)$event->format }}
												@if(\Illuminate\Support\Facades\Schema::hasColumn('events','is_template') && (bool)$event->is_template)
												· <span class="font-semibold">TEMPLATE</span>
												@endif
											</div>
										</div>
									</div>                                    
								</td>
								
								<td class="align-top f-16">
									{{ $fmtLocation($event) }}
								</td>
								
								{{-- ✅ Дата и время: разделено для single и recurring --}}
								<td class="align-top f-16">
									@if($isRecurring)
                                    <div>{{ $fmtSeriesDt($event) }}</div>
                                    @if($nextLocal)
                                    <div class="mt-1">
                                        Следующее: {{ $nextLocal->format('d.m.Y · H:i') }} ({{ $tz }})
									</div>
                                    @endif
									@else
                                    <div class="break-words">{{ $fmtRootDt($event) }}</div>
                                    @if($tab === 'mine' && $nextLocal)
                                    <div class="mt-1">
                                        Следующее: {{ $nextLocal->format('d.m.Y · H:i') }} ({{ $tz }})
									</div>
                                    @endif
									@endif
								</td>
								@if($isAdmin)
								<td class="align-top f-16">
									@if($org)
									<div>
										#{{ (int)$org->id }} — {{ $org->name ?? $org->email }}
									</div>
									<div>
										{{ ucfirst((string)($org->role ?? 'user')) }}
									</div>
									@else
									<span>—</span>
									@endif
								</td>
								@endif
								{{-- Действия --}}
								<td class="nowrap align-top f-0">
									
									@php
									$showBotBtn = ($event->format ?? 'game') !== 'tournament'
										&& ($event->registration_type ?? 'individual') !== 'team'
										&& (bool)($event->allow_registration ?? false);
									$botOn = (bool)($event->bot_assistant_enabled ?? false);
								@endphp
								@if($isRecurring)
									<div class="d-flex gap-1">
                                        @if($showBotBtn)
                                        <button type="button"
                                            class="btn btn-svg event-bot-toggle"
                                            data-url="{{ route('events.event_management.toggle-bot', ['event' => (int)$event->id]) }}"
                                            data-enabled="{{ $botOn ? '1' : '0' }}"
                                            title="{{ $botOn ? 'Бот включён для всех дат (нажми чтобы выключить)' : 'Бот выключен (нажми чтобы включить)' }}"
                                            @if($botOn) style="border-color:#10b981;color:#10b981" @endif>🤖</button>
                                        @endif
	                                        {{-- Для recurring: изменить серию, открыть даты, отменить всю серию --}}
                                        <a href="{{ route('events.event_management.edit', ['event' => (int)$event->id]) }}"
										class="icon-edit btn btn-svg"
										title="Изменить серию"></a>
                                        
										{{-- Для recurring: Создать копию тоже нужно --}}
                                        <a href="{{ url('/events/create?from_event_id=' . (int)$event->id) }}"
										class="icon-copy btn btn-svg"
										title="Создать копию"></a>										
										
										
                                        <form method="POST"
										action="{{ route('events.event_management.destroy', ['event' => (int)$event->id]) }}"
										class="d-inline-block">
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" name="delete_mode" value="series">
                                            <button type="submit"
											class="btn-alert btn btn-danger btn-svg icon-stop"
											data-title="Отменить всю серию?"
											data-text="Все даты серии будут отменены. История сохранится."
											data-confirm-text="Да, отменить"
											data-cancel-text="Отмена">
											</button>
										</form>
                                        
                                        {{-- Force delete для recurring (только для админа) --}}
                                        @if($isAdmin)
                                        <form method="POST"
										action="{{ route('events.event_management.destroy', ['event' => (int)$event->id]) }}"
										class="d-inline-block">
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" name="delete_mode" value="force">
                                            <button type="submit"
											class="btn-alert btn btn-danger btn-svg icon-delete"
											data-title="Удалить всю серию навсегда?"
											data-text="Будут удалены все даты серии без возможности восстановления."
											data-confirm-text="Да, удалить"
											data-cancel-text="Отмена">
											</button>
										</form>
                                        @endif
									</div>
									<a href="{{ route('events.event_management.occurrences', ['event' => (int)$event->id]) }}"
									class="mt-1 w-100 btn btn-secondary btn-small"
									>Открыть даты</a>										
									@else
									<div class="d-flex gap-1">
                                        @if($showBotBtn)
                                        <button type="button"
                                            class="btn btn-svg event-bot-toggle"
                                            data-url="{{ route('events.event_management.toggle-bot', ['event' => (int)$event->id]) }}"
                                            data-enabled="{{ $botOn ? '1' : '0' }}"
                                            title="{{ $botOn ? 'Бот включён (нажми чтобы выключить)' : 'Бот выключен (нажми чтобы включить)' }}"
                                            @if($botOn) style="border-color:#10b981;color:#10b981" @endif>🤖</button>
                                        @endif
	                                        {{-- Для single: изменить, копировать, отменить --}}
                                        <a href="{{ route('events.event_management.edit', ['event' => (int)$event->id]) }}"
										class="icon-edit btn btn-svg"
										title="Изменить"></a>
                                        
                                        <a href="{{ url('/events/create?from_event_id=' . (int)$event->id) }}"
										class="icon-copy btn btn-svg"
										title="Создать копию"></a>
										
                                        
                                        <form method="POST"
										action="{{ route('events.event_management.destroy', ['event' => (int)$event->id]) }}"
										class="d-inline-block">
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" name="delete_mode" value="single">
                                            <button type="submit"
											class="btn-alert btn btn-danger btn-svg icon-stop"
											data-title="Отменить мероприятие?"
											data-text="История сохранится, событие исчезнет из списка."
											data-confirm-text="Да, отменить"
											data-cancel-text="Отмена">
											</button>
										</form>
                                        
                                        @if($isAdmin)
                                        <form method="POST"
										action="{{ route('events.event_management.destroy', ['event' => (int)$event->id]) }}"
										class="d-inline-block">
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" name="delete_mode" value="force">
                                            <button type="submit"
											class="btn-alert btn btn-danger btn-svg icon-delete"
											data-title="Удалить навсегда?"
											data-text="Данные будут удалены без возможности восстановления. Только для тестовых данных."
											data-confirm-text="Да, удалить"
											data-cancel-text="Отмена">
											</button>
										</form>
                                        @endif
									</div>
									@if((bool)$event->allow_registration)
									<a href="{{ route('events.registrations.index', ['event' => (int)$event->id]) }}"
									class="mt-1 w-100 btn btn-secondary btn-small"
									title="Регистрации">Регистрации</a>
									@endif
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
		
		{{ $events->links() }} 
		
		{{-- bulk form --}}
		<form id="bulkForm"
		method="POST"
		action="{{ route('events.create.event_management.bulk_delete', array_merge(['tab' => $tab], request()->only('organizer_id'))) }}"
		class="hidden">
			@csrf
			<input type="hidden" name="delete_mode" id="bulkDeleteMode" value="">
			<div id="bulkIds"></div>
		</form>
		
		<script>
			document.addEventListener('DOMContentLoaded', () => {
				// ===== Bulk функционал через btn-alert (без нативного confirm)
				const bulkForm = document.getElementById('bulkForm');
				if (!bulkForm) return;
				
				const selectAll    = document.getElementById('bulkSelectAll');
				const countEl      = document.getElementById('bulkSelectedCount');
				const cancelBtn    = document.getElementById('bulkCancelBtn');
				const forceDeleteBtn = document.getElementById('bulkForceDeleteBtn');
				const deleteModeEl = document.getElementById('bulkDeleteMode');
				const idsWrap      = document.getElementById('bulkIds');
				
				const items = () => Array.from(document.querySelectorAll('.bulkItem'));
				
				function getSelectedIds() {
					return items().filter(i => i.checked).map(i => i.value);
				}
				
				function refreshBulk() {
					const selected = getSelectedIds().length;
					
					if (countEl) countEl.textContent = String(selected);
					if (cancelBtn) cancelBtn.disabled = selected === 0;
					if (forceDeleteBtn) forceDeleteBtn.disabled = selected === 0;
					
					if (selectAll) {
						const all = items();
						selectAll.checked = all.length > 0 && all.every(i => i.checked);
					}
				}
				
				function submitBulk(deleteMode) {
					if (!idsWrap) return;
					
					idsWrap.innerHTML = '';
					
					const ids = getSelectedIds();
					ids.forEach(id => {
						const inp = document.createElement('input');
						inp.type = 'hidden';
						inp.name = 'ids[]';
						inp.value = id;
						idsWrap.appendChild(inp);
					});
					
					if (deleteModeEl) deleteModeEl.value = deleteMode;
					bulkForm.submit();
				}
				
				// Обработка bulk-кнопок
				if (cancelBtn) {
					cancelBtn.addEventListener('click', () => {
						if (confirm('Отменить выбранные мероприятия? История будет сохранена, события исчезнут из списка.')) {
							submitBulk('cancel');
						}
					});
				}
				
				if (forceDeleteBtn) {
					forceDeleteBtn.addEventListener('click', () => {
						if (confirm('Удалить выбранные навсегда? Данные будут удалены без возможности восстановления.')) {
							submitBulk('force');
						}
					});
				}
				
				if (selectAll) {
					selectAll.addEventListener('change', () => {
						items().forEach(i => {
							i.checked = selectAll.checked;
						});
						refreshBulk();
					});
				}
				
				document.addEventListener('change', (e) => {
					if (e.target && e.target.classList && e.target.classList.contains('bulkItem')) {
						refreshBulk();
					}
				});
				
				// Клик по кастомным чекбоксам (e.target может быть div внутри label)
				document.addEventListener("click", (e) => {
					const label = e.target.closest("label.checkbox-item");
					if (label) {
						const cb = label.querySelector(".bulkItem");
						if (cb) { setTimeout(refreshBulk, 0); return; }
						const sa = label.querySelector("#bulkSelectAll");
						if (sa) { setTimeout(() => { items().forEach(i => { i.checked = sa.checked; }); refreshBulk(); }, 0); }
					}
				});
				refreshBulk();
			});
		</script>

    <x-slot name="script">
    <script>
    $(function() {
        $(document).on('click', '.event-bot-toggle', function() {
            var btn = $(this);
            var url = btn.data('url');
            btn.prop('disabled', true);

            $.ajax({
                url: url,
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                dataType: 'json',
                success: function(data) {
                    var on = !!data.enabled;
                    btn.data('enabled', on ? '1' : '0');
                    if (on) {
                        btn.css({'border-color': '#10b981', 'color': '#10b981'});
                    } else {
                        btn.css({'border-color': '', 'color': ''});
                    }
                    btn.attr('title', on
                        ? 'Бот включён для всех дат (нажми чтобы выключить)'
                        : 'Бот выключен (нажми чтобы включить)');
                    swal({
                        title: on ? '🤖 Бот включён' : '🤖 Бот выключен',
                        text: on
                            ? 'Помощник записи активирован для всех дат мероприятия'
                            : 'Помощник записи деактивирован для всех дат мероприятия',
                        icon: on ? 'success' : 'info',
                        button: 'OK',
                    });
                },
                error: function() {
                    swal({ title: 'Ошибка', text: 'Не удалось изменить статус бота', icon: 'error', button: 'OK' });
                },
                complete: function() {
                    btn.prop('disabled', false);
                }
            });
        });
    });
    </script>
    </x-slot>

	</x-voll-layout>