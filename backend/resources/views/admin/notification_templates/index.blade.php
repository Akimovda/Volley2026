{{--  body_class - класс для body --}}
<x-voll-layout body_class="note-page"> 
	
    <x-slot name="title">
		Шаблоны уведомлений
	</x-slot>
	
    <x-slot name="description">
		Шаблоны уведомлений
	</x-slot>
	
	
    <x-slot name="style">
        <style>
			
		</style>	
	</x-slot>
	
    <x-slot name="h1">
        Шаблоны уведомлений
	</x-slot>
	
	
    <x-slot name="t_description">
       Список шаблонов
	</x-slot>
	
    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('admin.dashboard') }}" itemprop="item"><span itemprop="name">Админ-панель</span></a>
            <meta itemprop="position" content="2">
		</li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('admin.notification_templates.index') }}" itemprop="item"><span itemprop="name">Шаблоны уведомлений</span></a>
            <meta itemprop="position" content="3">
		</li>
	</x-slot>	
	
	
	
	
    <div class="container">
		
        @if(session('status'))
		<div class="ramka">
			{{ session('status') }}
		</div>
        @endif	
		
		
        <div class="ramka">
			<div class="table-scrollable mb-0">
				<div class="table-drag-indicator"></div>
				<table class="table">
					<thead>
						<tr>
							<th>ID</th>
							<th>Код</th>
							<th>Канал</th>
							<th style="min-width: 30rem">Название</th>
							<th>Активен</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						@forelse($templates as $row)
                        <tr>
                            <td class="text-center">{{ $row->id }}</td>
                            <td>{{ $row->code }}</td>
                            <td>{{ $row->channel ?: 'общий' }}</td>
                            <td>{{ $row->name }}</td>
                            <td class="text-center">
                                @if($row->is_active)
								<span class="green">Да</span>
                                @else
								<span class="red">Нет</span>
                                @endif
							</td>
                            <td class="text-center">
                                <a href="{{ route('admin.notification_templates.edit', $row->id) }}"
								class="icon-edit btn btn-svg"></a>
							</td>
						</tr>
						@empty
                        <tr>
                            <td colspan="6" class="text-center">
                                Шаблоны пока не найдены.
							</td>
						</tr>
						@endforelse
					</tbody>
				</table>
			</div>

		</div>
	</div>	
</x-voll-layout>



