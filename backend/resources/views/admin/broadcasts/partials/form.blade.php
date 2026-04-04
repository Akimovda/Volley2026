<div class="ramka">
	<h2 class="-mt-05">Содержимое уведомления</h2>
	<div class="row">
		<div class="col-md-6">
			<div class="card">
				<label>Название</label>
				<input type="text" name="name" value="{{ old('name', $broadcast->name ?? '') }}">
				<div class="mb-05"></div>
			</div>
		</div>
		
		<div class="col-md-6">
			<div class="card">
				<label>Заголовок</label>
				<input type="text" name="title" value="{{ old('title', $broadcast->title ?? '') }}">
				<div class="mb-05"></div>
			</div>
		</div>
		<div class="col-md-12">
			<div class="card">
				<label>Текст</label>
				<textarea name="body" rows="10">{{ old('body', $broadcast->body ?? '') }}</textarea>
			</div>	
		</div>		
		<div class="col-md-12">
			<div class="card">				
				<label>Картинка (URL)</label>
				<input type="text" name="image_url" value="{{ old('image_url', $broadcast->image_url ?? '') }}">
				<div class="mb-05"></div>
			</div>
		</div>
		<div class="col-md-6">
			<div class="card">
				<label>Текст кнопки</label>
				<input type="text" name="button_text" value="{{ old('button_text', $broadcast->button_text ?? '') }}">
				<div class="mb-05"></div>
			</div>
		</div>
		<div class="col-md-6">
			<div class="card">
				<label>Ссылка кнопки</label>
				<input type="text" name="button_url" value="{{ old('button_url', $broadcast->button_url ?? '') }}">
				<div class="mb-05"></div>
			</div>
		</div>
	</div>	
</div>	
<div class="ramka" style="z-index: 10">	
	<h2 class="-mt-05">Параметры уведомления</h2>
	<div class="row">
		<div class="col-md-4">
			<div class="card">
				<label>Каналы</label>
				<div class="row row2">
					@foreach($channelOptions as $value => $label)
					<div class="col-6">
					<label class="checkbox-item">
						<input type="checkbox" name="channels[]" value="{{ $value }}" {{ in_array($value, old('channels', $channels ?? ['in_app']), true) ? 'checked' : '' }}>
						<div class="custom-checkbox"></div>
						<span>{{ $label }}</span>
					</label>
					</div>
					@endforeach
				</div>
			</div>
		</div>		
		<div class="col-md-4">
			<div class="card">
				<label>Статус</label>
				<select name="status" class="form-select">
					@foreach($statusOptions as $value => $label)
					<option value="{{ $value }}" {{ old('status', $broadcast->status ?? 'draft') === $value ? 'selected' : '' }}>
						{{ $label }}
					</option>
					@endforeach
				</select>
				<div class="mb-05"></div>
			</div>	
		</div>	
		<div class="col-md-4">
			<div class="card">
				<label>Запланировать на</label>
				<input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at', !empty($broadcast?->scheduled_at) ? \Illuminate\Support\Carbon::parse($broadcast->scheduled_at)->format('Y-m-d\TH:i') : '') }}">
				<div class="mb-05"></div>
			</div>	
		</div>	
	</div>
</div>	


<div class="ramka">
	<div class="card-body">
		<h2 class="-mt-05">Фильтры аудитории</h2>
		
		<div class="mb-3">
			<label>Город</label>
			<input type="text" name="filter_city" value="{{ old('filter_city', $filters['city'] ?? '') }}">
		</div>
		
		<div class="d-flex flex-wrap gap-3">
			<label class="checkbox-item">
				<input type="checkbox" name="filter_has_telegram" value="1" {{ old('filter_has_telegram', $filters['has_telegram'] ?? false) ? 'checked' : '' }}>
				<div class="custom-checkbox"></div>
				<span>Только с Telegram</span>
			</label>
			
			<label class="checkbox-item">
				<input type="checkbox" name="filter_has_vk" value="1" {{ old('filter_has_vk', $filters['has_vk'] ?? false) ? 'checked' : '' }}>
				<div class="custom-checkbox"></div>
				<span>Только с VK</span>
			</label>
			
			<label class="checkbox-item">
				<input type="checkbox" name="filter_has_max" value="1" {{ old('filter_has_max', $filters['has_max'] ?? false) ? 'checked' : '' }}>
				<div class="custom-checkbox"></div>
				<span>Только с MAX</span>
			</label>
		</div>
	</div>
</div>

<div class="ramka">
	<div class="card-body">
		<h2 class="-mt-05">Предпросмотр rich-уведомления</h2>
		
		<div class="border rounded bg-light">
			<div id="preview-image-wrap" class="d-none">
				<img id="preview-image" src="" alt="Preview" class="w-100" style="max-height: 320px; object-fit: cover;">
			</div>
			
			<div class="p-4">
				<div id="preview-title" class="h5 fw-bold text-dark">
					Заголовок уведомления
				</div>
				
				<div id="preview-body" class="mt-2 text-secondary" style="white-space: pre-line;">
					Здесь будет текст уведомления
				</div>
				
				<div id="preview-button-wrap" class="mt-3 d-none">
					<a id="preview-button" href="#" target="_blank" rel="noopener noreferrer" class="btn btn-primary">
						Открыть
					</a>
				</div>
			</div>
		</div>
		
		<div class="text-muted small mt-2">
			Это визуальный предпросмотр. Реальный вид может немного отличаться в Telegram / VK / MAX.
		</div>
	</div>
</div>

<div class="ramka">
	<div class="card-body">
		<h2 class="-mt-05">Быстрые действия</h2>
		
		<div class="d-flex flex-wrap gap-2 mb-3">
			<button type="button" id="btn-audience" class="btn btn-secondary">
				Посчитать аудиторию
			</button>
			
			<button type="button" id="btn-test" class="btn btn-warning">
				Тест мне
			</button>
			
			<button type="button" id="btn-dry-run" class="btn btn-purple" style="background-color: #7c3aed; border-color: #7c3aed;">
				Dry run
			</button>
		</div>
		
		<div id="action-result" class="text-muted">
			Готово к проверке
		</div>
	</div>
</div>

<div class="ramka">
	<div class="card-body">
		<div class="d-flex justify-content-between align-items-center mb-3">
			<h3 class="mb-0">Dry run</h2>
			<div class="text-muted small">Первые 10 пользователей</div>
		</div>
		
		<div id="dry-run-summary" class="text-muted mb-3">
			Ещё не запускали
		</div>
		
		<div id="dry-run-stats" class="d-flex flex-wrap gap-2 mb-3"></div>
		
		<div id="dry-run-list" class="space-y-3"></div>
	</div>
</div>

<script>
	document.addEventListener('DOMContentLoaded', function () {
		const form = document.getElementById('broadcast-form');
		const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
		
		if (!form) {
			return;
		}
		
		function fd() {
			return new FormData(form);
		}
		
		function setActionResult(text, isError = false) {
			const node = document.getElementById('action-result');
			if (!node) {
				return;
			}
			
			node.textContent = text;
			node.className = isError ? 'text-danger' : 'text-muted';
		}
		
		function escapeHtml(value) {
			return String(value ?? '')
			.replaceAll('&', '&amp;')
			.replaceAll('<', '&lt;')
			.replaceAll('>', '&gt;')
			.replaceAll('"', '&quot;')
			.replaceAll("'", '&#039;');
		}
		
		async function postForm(url) {
			const resp = await fetch(url, {
				method: 'POST',
				headers: {
					'X-CSRF-TOKEN': csrf || '',
					'Accept': 'application/json',
				},
				body: fd(),
			});
			
			let json = {};
			try {
				json = await resp.json();
				} catch (e) {
				json = {};
			}
			
			return { resp, json };
		}
		
		function updatePreview() {
			const data = fd();
			
			const title = (data.get('title') || '').trim();
			const body = (data.get('body') || '').trim();
			const image = (data.get('image_url') || '').trim();
			const btnText = (data.get('button_text') || '').trim();
			const btnUrl = (data.get('button_url') || '').trim();
			
			const previewTitle = document.getElementById('preview-title');
			const previewBody = document.getElementById('preview-body');
			const imgWrap = document.getElementById('preview-image-wrap');
			const img = document.getElementById('preview-image');
			const btnWrap = document.getElementById('preview-button-wrap');
			const btn = document.getElementById('preview-button');
			
			if (previewTitle) {
				previewTitle.textContent = title || 'Заголовок уведомления';
			}
			
			if (previewBody) {
				previewBody.textContent = body || 'Здесь будет текст уведомления';
			}
			
			if (imgWrap && img) {
				if (image) {
					img.src = image;
					imgWrap.classList.remove('d-none');
					} else {
					img.src = '';
					imgWrap.classList.add('d-none');
				}
			}
			
			if (btnWrap && btn) {
				if (btnUrl) {
					btn.textContent = btnText || 'Открыть';
					btn.href = btnUrl;
					btnWrap.classList.remove('d-none');
					} else {
					btn.textContent = 'Открыть';
					btn.href = '#';
					btnWrap.classList.add('d-none');
				}
			}
		}
		
		form.addEventListener('input', updatePreview);
		form.addEventListener('change', updatePreview);
		updatePreview();
		
		const audienceBtn = document.getElementById('btn-audience');
		if (audienceBtn) {
			audienceBtn.addEventListener('click', async function () {
				setActionResult('Считаем аудиторию...');
				
				try {
					const { resp, json } = await postForm('{{ route('admin.broadcasts.preview_audience') }}');
					
					if (!resp.ok || !json.ok) {
						setActionResult('Ошибка при расчёте аудитории', true);
						return;
					}
					
					setActionResult('Под фильтр попадёт пользователей: ' + json.count);
					} catch (e) {
					setActionResult('Ошибка запроса', true);
				}
			});
		}
		
		const testBtn = document.getElementById('btn-test');
		if (testBtn) {
			testBtn.addEventListener('click', async function () {
				setActionResult('Отправляем тест...');
				
				try {
					const { resp, json } = await postForm('{{ route('admin.broadcasts.test_send') }}');
					
					if (!resp.ok || !json.ok) {
						setActionResult('Ошибка тестовой отправки', true);
						return;
					}
					
					setActionResult('Тест отправлен ✅ notification_id=' + json.notification_id);
					} catch (e) {
					setActionResult('Ошибка запроса', true);
				}
			});
		}
		
		const dryRunBtn = document.getElementById('btn-dry-run');
		if (dryRunBtn) {
			dryRunBtn.addEventListener('click', async function () {
				const summary = document.getElementById('dry-run-summary');
				const statsBox = document.getElementById('dry-run-stats');
				const list = document.getElementById('dry-run-list');
				
				if (summary) {
					summary.textContent = 'Считаем dry run...';
					summary.className = 'text-muted';
				}
				
				if (statsBox) {
					statsBox.innerHTML = '';
				}
				
				if (list) {
					list.innerHTML = '';
				}
				
				try {
					const { resp, json } = await postForm('{{ route('admin.broadcasts.dry_run') }}');
					
					if (!resp.ok || !json.ok) {
						if (summary) {
							summary.textContent = 'Ошибка dry run';
							summary.className = 'text-danger';
						}
						if (statsBox) {
							statsBox.innerHTML = '';
						}
						return;
					}
					
					if (summary) {
						summary.textContent = `Всего попадёт: ${json.total}. Показано первых: ${json.preview_count} из ${json.limit}.`;
						summary.className = 'text-muted';
					}
					
					if (statsBox && json.stats) {
						const statBadge = (label, value, cls) =>
						`<span class="badge bg-${cls}">${label}: ${value}</span>`;
						
						statsBox.innerHTML = [
						statBadge('In-App', json.stats.in_app ?? 0, 'secondary'),
						statBadge('Telegram', json.stats.telegram ?? 0, 'info'),
						statBadge('VK', json.stats.vk ?? 0, 'primary'),
						statBadge('MAX', json.stats.max ?? 0, 'success'),
						statBadge('Без внешних каналов', json.stats.no_external_channels ?? 0, 'danger'),
						].join('');
					}
					
					if (!list) {
						return;
					}
					
					if (!json.items || !json.items.length) {
						list.innerHTML = '<div class="text-muted">Никого не найдено</div>';
						return;
					}
					
					function channelBadge(channel) {
						const map = {
							in_app: 'bg-secondary',
							telegram: 'bg-info',
							vk: 'bg-primary',
							max: 'bg-success',
						};
						
						const cls = map[channel] || 'bg-secondary';
						
						return `<span class="badge ${cls}">${escapeHtml(channel)}</span>`;
					}
					
					function skippedBadge(text) {
						return `<span class="badge bg-danger">${escapeHtml(text)}</span>`;
					}
					
					list.innerHTML = json.items.map((item) => {
						const name = escapeHtml(item.name || 'Без имени');
						const userId = escapeHtml(item.user_id || '');
						const email = escapeHtml(item.email || '');
						
						const channelsHtml = Array.isArray(item.channels) && item.channels.length
						? item.channels.map(channelBadge).join(' ')
						: '<span class="text-muted">Нет доступных каналов</span>';
						
						const skippedHtml = Array.isArray(item.skipped) && item.skipped.length
						? item.skipped.map(skippedBadge).join(' ')
						: '<span class="text-muted small">Без пропусков</span>';
						
						return `
						<div class="card bg-light mb-3">
						<div class="card-body">
						<div class="d-flex justify-content-between align-items-start">
						<div>
						<div class="fw-bold">${name} <span class="text-muted">#${userId}</span></div>
						<div class="text-secondary">${email}</div>
						</div>
						</div>
						
						<div class="mt-2">
						<div class="small text-muted mb-1">Доступные каналы</div>
						<div class="d-flex flex-wrap gap-1">${channelsHtml}</div>
						</div>
						
						<div class="mt-2">
						<div class="small text-muted mb-1">Причины пропуска</div>
						<div class="d-flex flex-wrap gap-1">${skippedHtml}</div>
						</div>
						</div>
						</div>
						`;
					}).join('');
					} catch (e) {
					if (summary) {
						summary.textContent = 'Ошибка запроса';
						summary.className = 'text-danger';
					}
					if (statsBox) {
						statsBox.innerHTML = '';
					}
				}
			});
		}
	});
</script>							