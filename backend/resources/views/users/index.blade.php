{{-- resources/views/users/index.blade.php --}}
<x-voll-layout body_class="users-page">
	
    <x-slot name="title">
        {{ __('profile.idx_title_page', ['n' => request()->page ?? 1]) }}
	</x-slot>
	
    <x-slot name="description">
        @if(request()->has('role'))
		{{ __('profile.idx_desc_role', ['role' => request()->role]) }}
        @else
		{{ __('profile.idx_desc_all') }}
        @endif
	</x-slot>
	
    <x-slot name="canonical">
        {{ route('users.index') }}
	</x-slot>
	

	
    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('users.index') }}" itemprop="item">
                <span itemprop="name">{{ __('profile.idx_breadcrumb') }}</span>
			</a>
            <meta itemprop="position" content="2">
		</li>
	</x-slot>
	
    <x-slot name="h1">{{ __('profile.idx_h1') }}</x-slot>
    <x-slot name="t_description">
		
@php
    $count = (int)($users->total() ?? $users->count() ?? 0);
    $word = trans_choice(__('profile.idx_count_people'), $count);
    
    if(!empty(array_filter($filters ?? []))) {
        $foundWord = trans_choice(__('profile.idx_count_found'), $count);
    }
@endphp

@if(!empty(array_filter($filters ?? [])))
    {{ ucfirst($foundWord) }} <strong class="cd">{{ $count }}</strong> {{ $word }}
@else
    {{ __('profile.idx_t_registered') }} <strong class="cd">{{ $count }}</strong> {{ $word }}
@endif
		
		
	</x-slot>
    <x-slot name="d_description">
		<div data-aos-delay="250" data-aos="fade-up">
			<button class="btn ufilter-btn mt-2">{{ __('profile.idx_btn_filter') }}</button>
		</div> 
	</x-slot>

	
	
	
    <div class="container">	
        <div class="users-filter">
            <div class="ramka">
                <form method="GET" action="{{ route('users.index') }}" class="form">
                    <div class="row">
                        <div class="col-md-4 col-sm-6">
                            <label>{{ __('profile.idx_label_name') }}</label>
                            <div style="position:relative;">
                                <input name="q" id="users-search-q"
                                    value="{{ $filters['q'] ?? '' }}"
                                    placeholder="{{ __('profile.idx_ph_name') }}"
                                    autocomplete="off"/>
                                <div id="users-search-dd" class="form-select-dropdown trainer_dd"></div>
                            </div>
                        </div>
						
                        <div class="col-md-4 col-sm-6">
                            <label>{{ __('profile.idx_label_city') }}</label>
                            <select name="city_id">
                                <option value="">{{ __('profile.idx_any') }}</option>
                                @foreach($cities as $c)
								<option value="{{ $c->id }}" @selected((string)($filters['city_id'] ?? '') === (string)$c->id)>
									{{ $c->name }}@if($c->region) ({{ $c->region }})@endif
								</option>
                                @endforeach
							</select>
						</div>
						
						<div class="col-md-4 col-sm-6">
                            <label>{{ __('profile.idx_label_gender') }}</label>
                            <select name="gender">
                                <option value="">{{ __('profile.idx_any') }}</option>
                                <option value="m" @selected(($filters['gender'] ?? '') === 'm')>{{ __('profile.idx_gender_m') }}</option>
                                <option value="f" @selected(($filters['gender'] ?? '') === 'f')>{{ __('profile.idx_gender_f') }}</option>
							</select>
						</div>
						
						<div class="col-md-4 col-sm-6">
                            <label>{{ __('profile.idx_label_classic_lvl') }}</label>
                            <select name="classic_level">
                                <option value="">{{ __('profile.idx_any') }}</option>
                                @foreach(range(1,7) as $lvl)
                                <option value="{{ $lvl }}" @selected((string)($filters['classic_level'] ?? '') === (string)$lvl)>{{ $lvl }} — {{ level_name($lvl) }}</option>
                                @endforeach
                            </select>
                        </div>
						
						<div class="col-md-4 col-sm-6">
                            <label>{{ __('profile.idx_label_beach_lvl') }}</label>
                            <select name="beach_level">
                                <option value="">{{ __('profile.idx_any') }}</option>
                                @foreach(range(1,7) as $lvl)
                                <option value="{{ $lvl }}" @selected((string)($filters['beach_level'] ?? '') === (string)$lvl)>{{ $lvl }} — {{ level_name($lvl) }}</option>
                                @endforeach
                            </select>
                        </div>
						
                        <div class="col-md-4 col-sm-6">
                            <label>{{ __('profile.idx_label_age') }}</label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <input
									name="age_min"
									value="{{ $filters['age_min'] ?? '' }}"
									placeholder="{{ __('profile.idx_ph_age_min') }}"
									inputmode="numeric"
                                    />
								</div>
                                <div class="col-6">
                                    <input
									name="age_max"
									value="{{ $filters['age_max'] ?? '' }}"
									placeholder="{{ __('profile.idx_ph_age_max') }}"
									inputmode="numeric"
                                    />
								</div>
							</div>
						</div>
						
                        <div class="col-12 text-right m-center">
                            <button class="btn" type="submit">{{ __('profile.idx_btn_search') }}</button>
							
							@if(!empty(array_filter($filters ?? [])))
							<a class="btn btn-secondary" href="{{ route('users.index') }}">{{ __('profile.idx_btn_reset') }}</a>
							@endif
						</div>
					</div>
				</form>
			</div>
		</div>	
		
		
		
        
            {{-- Header line --}}
			
            {{-- Results --}}
            @if(($users ?? collect())->isEmpty())
			<div class="ramka">
			<div class="alert alert-info">
					{{ __('profile.idx_empty_filtered') }}
			</div>
			</div>
            @else
			<div class="row mb-0">
				@foreach($users as $u)
				<div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-3">
					@include('users._card', ['u' => $u])
				</div>
				@endforeach
			</div>

            @endif
		{{ $users->links() }}
	</div>
	
    <x-slot name="script">
    <script>

(function(){
    var inp = document.getElementById('users-search-q');
    var dd = document.getElementById('users-search-dd');
    var timer = null;
    var url = '/api/users/search';
    
    if (!inp || !dd) return;
    
    function esc(s) { 
        return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); 
    }
    
    function showDd() { 
        dd.classList.add('form-select-dropdown--active'); 
    }
    
    function hideDd() { 
        dd.classList.remove('form-select-dropdown--active'); 
    }
    
    function render(items) {
        dd.innerHTML = '';
        
        if (!items.length) {
            dd.innerHTML = '<div class="city-message">' + @json(__('profile.idx_search_no_results')) + '</div>';
            showDd();
            return;
        }
        
        items.slice(0, 8).forEach(function(u) {
            var div = document.createElement('div');
            div.className = 'trainer-item form-select-option';
            div.setAttribute('data-name', u.label || '');
            var botBadge = u.is_bot ? '<span style="display:inline-block;padding:1px 8px;border-radius:10px;font-size:11px;font-weight:600;background:#fef3c7;color:#92400e;margin-left:.5rem">🤖 бот</span>' : '';
            div.innerHTML = '<div class="text-sm text-gray-900">' + esc(u.label || '') + botBadge + '</div>';
            
            div.addEventListener('click', function() {
                inp.value = div.getAttribute('data-name');
                hideDd();
                inp.closest('form').submit();
            });
            
            dd.appendChild(div);
        });
        
        showDd();
    }
    
    inp.addEventListener('input', function() {
        clearTimeout(timer);
        var q = inp.value.trim();
        
        if (q.length < 2) { 
            hideDd(); 
            return; 
        }
        
        dd.innerHTML = '<div class="city-message">' + @json(__('profile.idx_search_searching')) + '</div>';
        showDd();
        
        timer = setTimeout(function() {
            fetch(url + '?q=' + encodeURIComponent(q), {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var items = Array.isArray(data) ? data : (data.items || []);
                render(items);
            })
            .catch(function() {
                dd.innerHTML = '<div class="city-message">' + @json(__('profile.idx_search_error')) + '</div>';
                showDd();
            });
        }, 250);
    });
    
    document.addEventListener('click', function(e) {
        var wrap = inp.closest('div');
        if (wrap && !wrap.contains(e.target) && !dd.contains(e.target)) {
            hideDd();
        } else if (!inp.contains(e.target) && !dd.contains(e.target)) {
            hideDd();
        }
    });
    
    inp.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') hideDd();
    });
})();

    </script>
    </x-slot>

</x-voll-layout>
