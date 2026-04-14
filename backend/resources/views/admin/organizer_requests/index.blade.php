<x-voll-layout body_class="admin-organizer-requests-page">
	
    <x-slot name="title">Заявки на организатора</x-slot>
    <x-slot name="description">Управление заявками пользователей на роль организатора</x-slot>
    <x-slot name="canonical">{{ route('admin.organizer_requests.index') }}</x-slot>
    <x-slot name="h1">Заявки на организатора</x-slot>
    <x-slot name="t_description">Управление заявками пользователей на роль организатора</x-slot>
	
    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('admin.dashboard') }}" itemprop="item"><span itemprop="name">Админ-панель</span></a>
            <meta itemprop="position" content="2">
		</li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('admin.organizer_requests.index') }}" itemprop="item"><span itemprop="name">Заявки на организатора</span></a>
            <meta itemprop="position" content="3">
		</li>
	</x-slot>
	<div class="container">
		@if (session('status'))
		<div class="ramka">
			<div class="alert alert-info">{{ session('status') }}</div>
		</div>
		@endif
		
		@if ($requests->isEmpty())
		<div class="ramka">
			<div class="alert alert-info">📭 Заявок пока нет.</div>
		</div>
		@else
		<div class="ramka">
			
			<div class="table-scrollable">
				<div class="table-drag-indicator"></div>
				<table class="table">
					<thead>
						<tr>
							<th>#</th>
							<th>Пользователь</th>
							<th>Роль</th>
							<th>Статус</th>
							<th>Комментарий</th>
							<th>Дата</th>
							<th>Рассмотрел</th>
							<th>Действия</th>
						</tr>
					</thead>
					<tbody>
						@foreach ($requests as $r)
						@php
						$fio = trim(($r->last_name ?? '') . ' ' . ($r->first_name ?? ''));
						$label = $fio !== '' ? $fio : (($r->telegram_username ?? '') !== '' ? ('@'.$r->telegram_username) : $r->email);
						@endphp
						<tr>
							<td class="f-15">{{ $r->id }}</td>
							<td>
								<div class="b-600">{{ $label }}</div>
							</td>
							<td>
								<span class="f-15">{{ $r->role }}</span>
							</td>
							<td>
								@if ($r->status === 'pending')
								<span class="cs nowrap" style="color:#f5a623">⏳ Ожидает</span>
								@elseif ($r->status === 'approved')
								<span class="cs nowrap">✅ Одобрена</span>
								@elseif ($r->status === 'rejected')
								<span class="cd nowrap">❌ Отклонена</span>
								@else
								<span>{{ $r->status }}</span>
								@endif
							</td>
							<td class="f-15">
								@if (!empty($r->message))
								<div style="max-width:250px;white-space:pre-wrap">{{ $r->message }}</div>
								@else
								<span>—</span>
								@endif
							</td>
							<td class="f-15 nowrap">
								{{ \Carbon\Carbon::parse($r->created_at)->setTimezone('Europe/Moscow')->format('d.m.Y H:i') }}
							</td>
							<td class="f-15">
								@if (!empty($r->reviewed_at))
								<div>{{ \Carbon\Carbon::parse($r->reviewed_at)->setTimezone('Europe/Moscow')->format('d.m.Y H:i') }}</div>
								<div>{{ $r->reviewer_email ?? ('ID '.$r->reviewed_by) }}</div>
								@else
								<span>—</span>
								@endif
							</td>
							<td class="nowrap">
								@if ($r->status === 'pending')
								<form class="w-100" method="POST" action="{{ route('admin.organizer_requests.approve', $r->id) }}" class="d-inline">
									@csrf
									<button class="btn btn-small w-100 mb-1" type="submit">Одобрить</button>
								</form>
								<form class="w-100" method="POST" action="{{ route('admin.organizer_requests.reject', $r->id) }}" class="d-inline ml-1">
									@csrf
									<button class="btn-alert btn btn-small btn-danger" 
										data-title="Отменить заявку?"
										data-icon="warning"
										data-confirm-text="Да, отменить"
										data-cancel-text="Отмена"									
									type="submit">Отклонить</button>
								</form>
								@else
								<span>—</span>
								@endif
							</td>
						</tr>
						@endforeach
					</tbody>
				</table>
			</div>
			<p>
				Одобрение переводит пользователя в роль <b>organizer</b>. Отклонение оставляет роль без изменений.
			</p>	
		</div>
	</div>
    @endif
	
</x-voll-layout>
