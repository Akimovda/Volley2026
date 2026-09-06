<x-voll-layout body_class="admin-audits">
	<x-slot name="title">
		Админ аудит
	</x-slot>
	<x-slot name="h1">Админ аудит</x-slot>
    <x-slot name="breadcrumbs">
		<li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem">
			<a href="{{ route('admin.dashboard') }}" itemprop="item"><span itemprop="name">{{ __('admin.breadcrumb_dashboard') }}</span></a>
			<meta itemprop="position" content="2">
		</li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">Админ аудит</span>
            <meta itemprop="position" content="3">
		</li>		
	</x-slot>
	
    <x-slot name="d_description">
        <div data-aos="fade-up" data-aos-delay="250">
            <button class="btn mt-2 ufilter-btn">Фильтр</button>
		</div>
	</x-slot>
	
	
    <div class="container">
		<div class="users-filter">
            <div class="ramka form">
				
				<form method="GET" action="{{ route('admin.audits.index') }}">

					<div class="row">
						<div class="col-sm-6 col-lg-4">
							<label>Действие</label>
							<select name="action">
								<option value="">Все действия</option>
								@foreach ($actionOptions as $value => $label)
									<option value="{{ $value }}" @selected($filters['action'] === $value)>{{ $label }}</option>
								@endforeach
							</select>
						</div>
						<div class="col-sm-6 col-lg-4">
							<label>ID администратора</label>
							<input name="admin_user_id" value="{{ $filters['admin_user_id'] ?? '' }}" placeholder="Например, 5" />
						</div>
						<div class="col-sm-6 col-lg-4">
							<label>Тип цели</label>
							<select name="target_type">
								<option value="">Все</option>
								@foreach ($targetTypeOptions as $value => $label)
									<option value="{{ $value }}" @selected($filters['target_type'] === $value)>{{ $label }}</option>
								@endforeach
							</select>
						</div>
						<div class="col-sm-6 col-lg-4">
							<label>ID цели</label>
							<input name="target_id" value="{{ $filters['target_id'] ?? '' }}" placeholder="Например, 123" />
						</div>
						<div class="col-sm-6 col-lg-4">
							<label>Дата с</label>
							<input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" />
						</div>
						<div class="col-sm-6 col-lg-4">
							<label>Дата по</label>
							<input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" />
						</div>
					</div>
					<div class="d-flex gap-1 mt-2">
						<button class="btn" type="submit">Фильтровать</button>
						<a class="btn btn-secondary" href="{{ route('admin.audits.index') }}">Сбросить</a>
					</div>
				</form>			
			</div>
		</div>
		
		<div class="ramka">
			<div class="table-scrollable mb-0">
				<div class="table-drag-indicator"></div>
				<table class="table">
						<thead>
							<tr>
								<th>ID</th>
								<th style="min-width: 10rem">Время</th>
								<th>Администратор</th>
								<th style="min-width: 14rem">Действие</th>
								<th>Цель</th>
								<th>IP</th>
							</tr>
						</thead>

						<tbody>
							@forelse ($audits as $a)
							<tr class="align-top">
								<td>{{ $a->id }}</td>

								<td>
									{{ \Illuminate\Support\Carbon::parse($a->created_at)->format('d.m.Y H:i') }}
								</td>

								<td>
									<div>{{ $a->admin_name ?? ('#'.$a->admin_user_id) }}</div>
								</td>

								<td>
									<div>{{ $a->action_label }}</div>
									@if ($a->action_detail !== '')
										<div class="text-sm">{{ $a->action_detail }}</div>
									@endif
									@php
									$meta = $a->meta;
									$metaArr = null;

									if (is_string($meta) && $meta !== '') {
									$decoded = json_decode($meta, true);
									$metaArr = is_array($decoded) ? $decoded : null;
									} elseif (is_array($meta)) {
									$metaArr = $meta;
									}
									@endphp
									@if ($metaArr || $a->action !== $a->action_label)
									<details class="text-xs mt-1">
										<summary class="text-muted" style="cursor:pointer">тех. детали</summary>
										<div class="text-muted">{{ $a->action }}</div>
										@if ($metaArr)
										<pre class="text-xs whitespace-pre-wrap">{{ json_encode($metaArr, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
										@endif
									</details>
									@endif
								</td>

								<td>
									@if ($a->target_type === 'user' && $a->target_id)
										<a href="{{ route('admin.users.show', $a->target_id) }}">{{ $a->target_label }} #{{ $a->target_id }}</a>
									@else
										{{ $a->target_label }}{{ $a->target_id ? ' #'.$a->target_id : '' }}
									@endif
								</td>

								<td>{{ $a->ip ?? '—' }}</td>
							</tr>
							@empty
							<tr class="border-t">
								<td class="py-4 text-gray-500" colspan="6">Записи не найдены.</td>
							</tr>
							@endforelse
						</tbody>
					</table>						
				</div>
			</div>
			
			
			{{ $audits->links() }}
			
			
		</div>
	</x-voll-layout>
