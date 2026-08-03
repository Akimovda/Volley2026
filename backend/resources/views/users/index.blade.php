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

    <x-slot name="image">
        <div class="top-section-img" data-aos="fade" data-aos-duration="1000">
            <div class="top-section-light-img">
                <img src="/img/users-light.webp" alt="img">
            </div>
            <div class="top-section-dark-img">
                <img src="/img/users-dark.webp" alt="img">
            </div>
        </div>
    </x-slot>

    <x-slot name="d_description">
		<div data-aos-delay="250" data-aos="fade-up">
			<button type="button" id="btnOpenUsersFilters" class="btn mt-2">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="vertical-align:-0.4rem;margin-right:0.6rem">
					<line x1="4" y1="6" x2="20" y2="6"></line>
					<circle cx="9" cy="6" r="2" fill="currentColor" stroke="none"></circle>
					<line x1="4" y1="12" x2="20" y2="12"></line>
					<circle cx="16" cy="12" r="2" fill="currentColor" stroke="none"></circle>
					<line x1="4" y1="18" x2="20" y2="18"></line>
					<circle cx="11" cy="18" r="2" fill="currentColor" stroke="none"></circle>
				</svg>
				{{ __('profile.idx_btn_filter') }}
			</button>
		</div>
	</x-slot>

    @php
        $levelScope = level_terminology_scope_for_user(auth()->user());
    @endphp

    <div class="container">

		{{-- Поп-ап "Фильтр" (fancybox inline) — скрыто на странице --}}
		<div id="usersFilterModal" style="display:none; max-width: 50rem">
			<h2 class="title-h -mt-05">{{ __('profile.idx_btn_filter') }}</h2>
			<div class="form" style="overflow: visible">
				<form method="GET" action="{{ route('users.index') }}">
					<div class="row g-2">
						<div class="col-12">
							<label class="filter-section-label">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
								{{ __('profile.idx_section_search') }}
							</label>
							<div class="pfx-field-icon">
								<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
								<input name="q" id="users-search-q"
									value="{{ $filters['q'] ?? '' }}"
									placeholder="{{ __('profile.idx_ph_name') }}"
									autocomplete="off"/>
								<div id="users-search-dd" class="form-select-dropdown trainer_dd"></div>
							</div>
						</div>

						<div class="col-12">
							<label class="filter-section-label">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
								{{ __('profile.idx_label_gender') }}
							</label>
							<div class="seg-control">
								<label class="seg-btn">
									<input type="radio" name="gender" value="" {{ ($filters['gender'] ?? '') === '' ? 'checked' : '' }}>
									<span>{{ __('profile.idx_gender_all_seg') }}</span>
								</label>
								<label class="seg-btn">
									<input type="radio" name="gender" value="m" {{ ($filters['gender'] ?? '') === 'm' ? 'checked' : '' }}>
									<span>♂ {{ __('profile.idx_gender_m_seg') }}</span>
								</label>
								<label class="seg-btn">
									<input type="radio" name="gender" value="f" {{ ($filters['gender'] ?? '') === 'f' ? 'checked' : '' }}>
									<span>♀ {{ __('profile.idx_gender_f_seg') }}</span>
								</label>
							</div>
						</div>

						<div class="col-md-6 col-sm-6">
							<label class="filter-section-label">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 12-9 12s-9-5-9-12a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
								{{ __('profile.idx_label_city') }}
							</label>
							<div class="pfx-field-icon">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 12-9 12s-9-5-9-12a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
								<select name="city_id" class="form-select">
									<option value="">{{ __('profile.idx_any') }}</option>
									@foreach($cities as $c)
									<option value="{{ $c->id }}" @selected((string)($filters['city_id'] ?? '') === (string)$c->id)>
										{{ $c->name }}@if($c->region_display) ({{ $c->region_display }})@endif
									</option>
									@endforeach
								</select>
							</div>
						</div>

						<div class="col-md-6 col-sm-6">
							<label class="filter-section-label">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
								{{ __('profile.idx_label_age') }}
							</label>
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

						<div class="col-md-6 col-sm-6">
							<label class="filter-section-label">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="6" y1="20" x2="6" y2="14"></line><line x1="12" y1="20" x2="12" y2="10"></line><line x1="18" y1="20" x2="18" y2="4"></line></svg>
								{{ __('profile.idx_section_classic_lvl') }}
							</label>
							<div class="pfx-field-icon">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="6" y1="20" x2="6" y2="14"></line><line x1="12" y1="20" x2="12" y2="10"></line><line x1="18" y1="20" x2="18" y2="4"></line></svg>
								<select name="classic_level" class="form-select">
									<option value="">{{ __('profile.idx_any') }}</option>
									@foreach(range(1,7) as $lvl)
									<option value="{{ $lvl }}" @selected((string)($filters['classic_level'] ?? '') === (string)$lvl)>{{ $lvl }} — {{ level_name($lvl, $levelScope) }}</option>
									@endforeach
								</select>
							</div>
						</div>

						<div class="col-md-6 col-sm-6">
							<label class="filter-section-label">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="6" y1="20" x2="6" y2="14"></line><line x1="12" y1="20" x2="12" y2="10"></line><line x1="18" y1="20" x2="18" y2="4"></line></svg>
								{{ __('profile.idx_section_beach_lvl') }}
							</label>
							<div class="pfx-field-icon">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="6" y1="20" x2="6" y2="14"></line><line x1="12" y1="20" x2="12" y2="10"></line><line x1="18" y1="20" x2="18" y2="4"></line></svg>
								<select name="beach_level" class="form-select">
									<option value="">{{ __('profile.idx_any') }}</option>
									@foreach(range(1,7) as $lvl)
									<option value="{{ $lvl }}" @selected((string)($filters['beach_level'] ?? '') === (string)$lvl)>{{ $lvl }} — {{ level_name($lvl, $levelScope) }}</option>
									@endforeach
								</select>
							</div>
						</div>

						<div class="col-12 d-flex flex-wrap gap-2 align-items-center mt-1">
							<a class="btn btn-secondary" href="{{ route('users.index') }}">{{ __('profile.idx_btn_reset') }}</a>
							<button class="btn" type="submit">{{ __('profile.idx_btn_show') }}</button>
						</div>
					</div>
				</form>
			</div>
		</div>

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
    <script src="/assets/fas.js"></script>
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

(function() {
    var btnOpenUsersFilters = document.getElementById('btnOpenUsersFilters');
    if (btnOpenUsersFilters) {
        btnOpenUsersFilters.addEventListener('click', function() {
            jQuery.fancybox.open({
                src: '#usersFilterModal',
                type: 'inline',
                opts: { hideScrollbar: false, touch: false, toolbar: false, smallBtn: true, animationEffect: 'zoom-in-out', transitionEffect: 'zoom-in-out', baseClass: 'events-filter-fancybox' }
            });
        });
    }
})();

    </script>
    </x-slot>

</x-voll-layout>
