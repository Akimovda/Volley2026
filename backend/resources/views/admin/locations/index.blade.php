{{-- resources/views/admin/locations/index.blade.php --}}
<x-voll-layout body_class="admin-page admin-locations-index">
    <x-slot name="title">
        {{ __('admin.loc_index_title') }} ({{ __('admin.breadcrumb_dashboard') }})
	</x-slot>
	
    <x-slot name="description">
        {{ __('admin.loc_index_t_description') }}
	</x-slot>
	
    <x-slot name="h1">
        {{ __('admin.loc_index_title') }}
	</x-slot>
	
    <x-slot name="h2">
        {{ __('admin.breadcrumb_dashboard') }}
	</x-slot>
	
    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('admin.dashboard') }}" itemprop="item"><span itemprop="name">{{ __('admin.breadcrumb_dashboard') }}</span></a>
            <meta itemprop="position" content="1">
		</li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">{{ __('admin.loc_breadcrumb') }}</span>
            <meta itemprop="position" content="2">
		</li>
	</x-slot>
	
    <x-slot name="t_description">
        {{ __('admin.loc_index_t_description') }}
	</x-slot>	
	
    <x-slot name="d_description">
		<div data-aos-delay="250" data-aos="fade-up">
			<a href="{{ route('admin.locations.create') }}" class="btn mt-2">
				{{ __('admin.loc_btn_create') }}
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
            <div class="alert alert-danger">
                {{ session('error') }}
			</div>
		</div>
        @endif
		
        <div class="ramka">
			
			<div class="table-scrollable mb-0">
				<div class="table-drag-indicator"></div>
				<table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('admin.col_id') }}</th>
                            <th style="min-width: 20rem">{{ __('admin.loc_col_city') }}</th>
                            <th style="min-width: 24rem">{{ __('admin.loc_col_name') }}</th>
                            <th style="min-width: 24rem">{{ __('admin.loc_col_address') }}</th>
                            <th style="min-width: 10rem">{{ __('admin.loc_col_actions') }}</th>
						</tr>
					</thead>
                    <tbody>
                        @foreach($locations as $loc)
                        <tr>
                            <td>{{ $loc->id }}</td>
                            <td>
                                @if($loc->city)
								{{ $loc->city->name }}
								@if($loc->city->region || $loc->city->country_code)
								<div class="city-details">
									@php
									$parts = [];
									if($loc->city->country_code) $parts[] = $loc->city->country_code;
									if($loc->city->region) $parts[] = $loc->city->region;
									@endphp
									{{ implode(' • ', $parts) }}
								</div>
								@endif
                                @else
								—
                                @endif
							</td>
                            <td>
								@php
								$slug = Str::slug($loc->name);
								@endphp
								
                                <a href="{{ route('locations.show', ['location' => $loc->id, 'slug' => $slug]) }}" class="b-600 blink">
                                    {{ $loc->name }}
								</a>
							</td>
                            <td>{{ $loc->address ?? '—' }}</td>
                            <td>
                                <div class="d-flex fc">
                                    <a href="{{ route('admin.locations.edit', $loc) }}" class="icon-edit btn mr-1 btn-svg">
									</a>
                                    <form class="d-inline-block" method="POST"
									action="{{ route('admin.locations.destroy', $loc) }}">
                                        @csrf
                                        @method('DELETE')
										<button type="submit" 
										class="icon-delete btn-alert btn btn-danger btn-svg"
										data-title="{{ __('admin.loc_confirm_delete') }}"
										data-text="{{ __('admin.loc_delete_text') }}"
										data-icon="warning"
										data-confirm-text="{{ __('admin.btn_delete') }}"
										data-cancel-text="{{ __('admin.btn_cancel') }}">
										</button>										
										
										
									</form>
								</div>
							</td>
						</tr>
                        @endforeach
					</tbody>
				</table>
			</div>
			
            @if(method_exists($locations, 'links'))
			
			{{ $locations->links() }}
			
            @endif
		</div>
	</div>
</x-voll-layout>