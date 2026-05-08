{{-- body_class - класс для body --}}
<x-voll-layout body_class="broadcasts-page">
    
    <x-slot name="title">
        {{ __('admin.bc_title') }}
	</x-slot>
    
    <x-slot name="description">
        {{ __('admin.bc_t_description') }}
	</x-slot>
    
    <x-slot name="canonical">
        {{ route('admin.broadcasts.index') }}
	</x-slot>
    
    <x-slot name="style">
        <style>
            /* Дополнительные стили при необходимости */
		</style>
	</x-slot>
    
    <x-slot name="h1">
        {{ __('admin.bc_title') }}
	</x-slot>
    
	
    
    <x-slot name="t_description">
        {{ __('admin.bc_idx_t_description') }}
	</x-slot>
    
    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('admin.dashboard') }}" itemprop="item"><span itemprop="name">{{ __('admin.breadcrumb_dashboard') }}</span></a>
            <meta itemprop="position" content="2">
		</li>	
        <li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem">
            <span itemprop="name">{{ __('admin.bc_breadcrumb') }}</span>
            <meta itemprop="position" content="3">
		</li>
	</x-slot>
    
    <x-slot name="d_description">
	
		<div data-aos-delay="250" data-aos="fade-up">
			<a href="{{ route('admin.broadcasts.create') }}" class="btn mt-2">
				{{ __('admin.bc_btn_create') }}
			</a>
		</div>	

	</x-slot>
    
    <x-slot name="script">
        <script>
            // Дополнительные скрипты при необходимости
		</script>
	</x-slot>
    
    <div class="container">
        <div class="row">
            <div class="col-12">
                @if(session('status'))
				<div class="ramka">
                    <div class="alert alert-success">
                        {{ session('status') }}
					</div>
				</div>
                @endif
                
                <div class="ramka">
					<div class="table-scrollable mb-0">
						<div class="table-drag-indicator"></div>
						<table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Название</th>
                                    <th>Статус</th>
                                    <th>Создал</th>
                                    <th>Создана</th>
                                    <th></th>
								</tr>
							</thead>
                            <tbody>
                                @forelse($broadcasts as $row)
								<tr>
									<td class="text-center">{{ $row->id }}</td>
									<td>{{ $row->name }}</td>
									<td>{{ $row->status }}</td>
									<td>{{ $row->created_by_name ?: ('#'.$row->created_by) }}</td>
									<td>{{ $row->created_at }}</td>
									<td class="text-center">
										<a href="{{ route('admin.broadcasts.edit', $row->id) }}" class="icon-edit btn  btn-svg"></a>
									</td>
								</tr>
                                @empty
								<tr>
									<td colspan="6" class="text-center text-muted">
										Рассылок пока нет.
									</td>
								</tr>
                                @endforelse
							</tbody>
						</table>
					</div>
				</div>
                

                    {{ $broadcasts->links() }}

			</div>
		</div>
	</div>
    
</x-voll-layout>