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
							<input class="v-input md:col-span-2" name="action" value="{{ $filters['action'] ?? '' }}" placeholder="action contains..." />
						</div>
						<div class="col-sm-6 col-lg-4">		
							<input name="admin_user_id" value="{{ $filters['admin_user_id'] ?? '' }}" placeholder="admin_user_id" />
						</div>
						<div class="col-sm-6 col-lg-4">		
							<input name="target_type" value="{{ $filters['target_type'] ?? '' }}" placeholder="target_type (e.g. user)" />
						</div>
						<div class="col-sm-6 col-lg-4">		
							<input name="target_id" value="{{ $filters['target_id'] ?? '' }}" placeholder="target_id" />
						</div>
						<div class="col-sm-6 col-lg-4">		
							<input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" />
						</div>
						<div class="col-sm-6 col-lg-4">		
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
								<th style="min-width: 16rem">At</th>
								<th>Admin</th>
								<th>Action</th>
								<th>Target</th>
								<th>IP</th>
								<th>Meta</th>
							</tr>
						</thead>
						
						<tbody>
							@forelse ($audits as $a)
							<tr class="align-top">
								<td>{{ $a->id }}</td>
								
								<td>
									{{ \Illuminate\Support\Carbon::parse($a->created_at)->format('Y-m-d H:i') }}
								</td>
								
								<td>
									<div>{{ $a->admin_name ?? ('#'.$a->admin_user_id) }}</div>
								</td>
								
								<td>{{ $a->action }}</td>
								
								<td>
									{{ $a->target_type }}:{{ $a->target_id }}
								</td>
								
								<td>{{ $a->ip ?? '—' }}</td>
								
								<td>
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
									
									@if ($metaArr)
									<pre class="text-xs whitespace-pre-wrap">{{ json_encode($metaArr, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
									@elseif(is_string($meta) && $meta !== '')
									<div class="text-xs">{{ $meta }}</div>
									@else
									—
									@endif
								</td>
							</tr>
							@empty
							<tr class="border-t">
								<td class="py-4 text-gray-500" colspan="7">No audits found.</td>
							</tr>
							@endforelse
						</tbody>
					</table>						
				</div>
			</div>
			
			
			{{ $audits->links() }}
			
			
		</div>
	</x-voll-layout>
