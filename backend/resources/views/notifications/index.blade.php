<x-voll-layout>
	
	@php
	$user = auth()->user();
	@endphp	
	
    <x-slot name="title">Уведомления</x-slot>
    <x-slot name="h1">Уведомления</x-slot>
	
	<x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem">
            <a href="{{ route('profile.show') }}" itemprop="item">
                <span itemprop="name">Ваши профиль</span>
			</a>
            <meta itemprop="position" content="2">
		</li>	
		<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
			<a href="{{ route('notifications.index') }}" itemprop="item">
				<span itemprop="name">Уведомления</span>
			</a>
			<meta itemprop="position" content="3">
		</li>
	</x-slot>
	<x-slot name="t_description">Непрочитанных: <strong class="cd">{{ $unreadCount }}</strong></x-slot>
	
	
	
	<x-slot name="d_description">
		
		@if($unreadCount > 0)
		<div data-aos-delay="250" data-aos="fade-up">
			<form method="POST" action="{{ route('notifications.read_all') }}">
				@csrf
				<button type="submit" class="mt-2 btn btn-outline-secondary">
					Прочитать все
				</button>
			</form>
		</div>		
		@endif
		
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
		
		
        <div class="row">
<div class="col-lg-4 col-xl-3 order-2 d-none d-lg-block">
<div class="sticky">
<div class="card-ramka">
@include('profile._menu', [
    'menuUser'   => auth()->user(),
    'activeMenu' => 'notifications',
])
</div>
</div>
</div>
			<div class="col-lg-8 col-xl-9 order-1">		
				
				
				<div class="ramka">
					<h2 class="-mt-05">Входящие уведомления</h2>
					@forelse($notifications as $notification)
					@php
					$payload = is_array($notification->payload) ? $notification->payload : [];
					$isUnread = empty($notification->read_at);
					$type = (string) ($notification->type ?? '');
					$eventId = (int) ($payload['event_id'] ?? 0);
					$inviteId = (int) ($payload['invite_id'] ?? 0);
					$autoJoin = (bool) ($payload['auto_join_after_registration'] ?? false);
					@endphp
					
					
					<div id="notification-{{ $notification->id }}" class="card {{ !$loop->last ? 'mb-2' : '' }}">
						<div class="d-flex between fvc mb-1">
							<div>
								<div class="b-600">							
									@if($isUnread)
									<span class="emo">🔴</span>
									@endif
									{{ $notification->title }}
								</div>
							</div>
							<div>
								<div class="text-right f-16">
									{{ $notification->created_at?->format('d.m.Y H:i') }}
								</div>					
								
							</div>						
						</div>
						<div class="row">
							<div class="col-sm-10">		
								@if(!empty($notification->body))
								{{ $notification->body }}
								@endif	
								
								
								@if($type === 'group_invite' && $eventId > 0 && $inviteId > 0)
								<div class="mt-2 d-flex gap-2 flex-wrap">
									<a href="{{ route('events.show', ['event' => $eventId]) }}" class="btn btn-outline-primary">
										Открыть мероприятие
									</a>
									
									@if(!$autoJoin)
									<form method="POST" action="{{ route('events.group.accept', ['event' => $eventId, 'invite' => $inviteId]) }}">
										@csrf
										<button type="submit" class="btn">
											Принять
										</button>
									</form>
									@endif
									
									<form method="POST" action="{{ route('events.group.decline', ['event' => $eventId, 'invite' => $inviteId]) }}">
										@csrf
										<button type="submit" class="btn btn-outline-secondary">
											Отклонить
										</button>
									</form>
								</div>
								
								@if($autoJoin)
								<div class="alert alert-info mt-2 mb-0">
									Сначала зарегистрируйтесь в системе и запишитесь на мероприятие, затем вернитесь и примите приглашение.
								</div>
								@endif
								@endif						
								
								
							</div>	
							
							<div class="col-sm-2 text-right f-0">
								
								
								@if($isUnread)
								<form class="d-inline-block" method="POST" action="{{ route('notifications.read', ['notification' => $notification->id]) }}">
									@csrf
									<button type="submit" class="mr-1 icon-eye btn btn-svg"></button>
								</form>
								@endif	
								
								<form class="d-inline-block" method="POST"
								action="{{ route('notifications.destroy', ['notification' => $notification->id]) }}">
									@csrf
									@method('DELETE')
									<button type="button" 
									class="icon-delete btn-alert btn btn-danger btn-svg"
									data-title="Удалить сообщение?"
									data-icon="warning"
									data-confirm-text="Да, удалить"
									data-cancel-text="Отмена">
									</button>                                        
								</form>
							</div>						
						</div>		
						
						
					</div>
					
					@empty
					
					<div class="alert alert-secondary">
						Уведомлений пока нет.
					</div>
					
					@endforelse
					
				</div>
			</div> 	
		</div> 		
		{{ $notifications->links() }}
	</div>
</x-voll-layout>
