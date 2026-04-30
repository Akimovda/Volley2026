<x-voll-layout>
	@php
	$user = auth()->user();
	@endphp	
	
	<x-slot name="title">Мои мероприятия</x-slot>
	<x-slot name="h1">Мои мероприятия</x-slot>
    <x-slot name="h2">
        @if(!empty($user->first_name) || !empty($user->last_name))
        {{ trim($user->first_name . ' ' . $user->last_name) }}
        @else
        Пользователь #{{ $user->id }}
        @endif
	</x-slot>
    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem">
            <a href="{{ route('profile.show') }}" itemprop="item">
                <span itemprop="name">Мой профиль</span>
			</a>
            <meta itemprop="position" content="2">
		</li>
        <li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem">
            <span itemprop="name">Мои мероприятия</span>
            <meta itemprop="position" content="3">
		</li>	
	</x-slot>
	
    <x-slot name="t_description">
        Здесь отображаются ваши текущие и архивные мероприятия
	</x-slot>
	
    <x-slot name="d_description">
		
		<div class="d-flex flex-wrap gap-1 m-center">
			<div class="mt-2" data-aos-delay="250" data-aos="fade-up">
				<a href="{{ route('player.my-events', ['filter' => 'current']) }}"
				class="btn {{ $filter === 'current' ? 'btn-primary' : 'btn-secondary' }}">
					Текущие
				</a>
			</div>
			<div class="mt-2" data-aos-delay="350" data-aos="fade-up">
				<a href="{{ route('player.my-events', ['filter' => 'archive']) }}"
				class="btn {{ $filter === 'archive' ? 'btn-primary' : 'btn-secondary' }}">
					Архивные
				</a>
			</div>						
		</div>
		
	</x-slot>
	
	
	
	<div class="container">
		
        <div class="row row2">
            <div class="col-lg-4 col-xl-3 order-2 d-none d-lg-block">
                <div class="sticky">
                    <div class="card-ramka">
						@include('profile._menu', ['activeMenu' => 'my_events'])
					</div>
				</div>
			</div>		
			<div class="col-lg-8 col-xl-9 order-1">
				<div class="ramka pb-1">
					<h2 class="-mt-05">
						{{ $filter === 'current' ? 'Текущие' : ($filter === 'archive' ? 'Архивные' : '') }} мероприятия
					</h2>			
					
					{{-- Список --}}
					@if($registrations->isEmpty())
					<div class="alert alert-info">
						{{ $filter === 'current' ? 'Нет предстоящих мероприятий.' : 'История мероприятий пуста.' }}
					</div>
					@else
					@foreach($registrations as $reg)
					@php
					$startsAt = \Carbon\Carbon::parse($reg->starts_at, 'UTC')->setTimezone($userTz);
					$cancelUntil = $reg->cancel_self_until
					? \Carbon\Carbon::parse($reg->cancel_self_until, 'UTC')
					: ($reg->event_cancel_self_until
					? \Carbon\Carbon::parse($reg->event_cancel_self_until, 'UTC')
					: null);
					$canCancel = $filter === 'current'
					&& (!$cancelUntil || now('UTC')->lt($cancelUntil))
					&& $startsAt->isFuture();
					$posLabel = $reg->position ? position_name($reg->position) : null;
					@endphp
					<div class="card mb-2">
						
						
						<div class="d-flex between gap-1 fvc">
							<div>
								<a href="{{ url('/events/' . $reg->event_id . '?occurrence=' . $reg->occurrence_id) }}"
								class="blink b-600 mb-1">
									{{ $reg->title }}
								</a>
								@if($reg->location_name)
								<div>📍 {{ $reg->location_name }}{{ $reg->city_name ? ', ' . $reg->city_name : '' }}</div>
								@endif
								@if($posLabel)
								<div>🎯 {{ $posLabel }}</div>
								@endif								
							</div>
							<div>
									<div class="text-right f-16">
										🗓 {{ $startsAt->locale('ru')->translatedFormat('d F Y, H:i') }}
									</div>
									@if($canCancel)
									<form class="mt-1 text-right" method="POST"
									action="{{ route('occurrences.leave', $reg->occurrence_id) }}"
									onsubmit="return confirm('Отменить запись на «{{ addslashes($reg->title) }}»?')"
									style="margin:0">
										@csrf
										@method('DELETE')
										<button type="button" 
										class="icon-delete btn-alert btn btn-danger btn-svg"
										data-title="Отменить запись?"
										data-icon="warning"
										data-confirm-text="Да, отменить"
										data-cancel-text="Отмена">
										</button>   
									</form>
									@endif									

							</div>
						</div>							
						
					</div>
					
					@endforeach
					
					{{-- Пагинация --}}
					@if($registrations->hasPages())
					<div class="mt-2">
						{{ $registrations->links() }}
					</div>
					@endif
					@endif
				</div>	
			</div>
		</div>
	</div>
</x-voll-layout>
