<x-voll-layout body_class="school-trainers-page">

    <x-slot name="title">{{ __('trainers.school_roster_title') }} — {{ $school->name }}</x-slot>
    <x-slot name="h1">{{ __('trainers.school_roster_title') }}</x-slot>
    <x-slot name="h2">{{ $school->name }}</x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('volleyball_school.index') }}" itemprop="item"><span itemprop="name">Школы волейбола</span></a>
            <meta itemprop="position" content="2">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('volleyball_school.show', $school->slug) }}" itemprop="item"><span itemprop="name">{{ $school->name }}</span></a>
            <meta itemprop="position" content="3">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">{{ __('trainers.school_roster_title') }}</span>
            <meta itemprop="position" content="4">
        </li>
    </x-slot>

    <div class="container">

        @if(session('status'))
        <div class="ramka"><div class="alert alert-success">{{ session('status') }}</div></div>
        @endif
        @if($errors->any())
        <div class="ramka">
            <div class="alert alert-error">
                <ul class="list">
                    @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
                </ul>
            </div>
        </div>
        @endif

        {{-- Пригласить тренера --}}
        <div class="ramka">
            <h2 class="-mt-05">{{ __('trainers.school_invite_title') }}</h2>
            <div class="card form" style="position:relative;">
                <form method="POST" action="{{ route('volleyball_school.trainers.store', $school) }}" id="invite-trainer-form">
                    @csrf
                    <div class="ac-box" data-users-search-url="{{ route('api.users.search') }}" style="position:relative;">
                        <input type="text" id="invite-trainer-search" placeholder="{{ __('trainers.school_invite_search_ph') }}" autocomplete="off">
                        <input type="hidden" name="user_id" id="invite-trainer-id">
                        <div id="invite-trainer-dd"></div>
                    </div>
                    <button class="btn mt-1" type="submit" id="invite-trainer-submit" disabled>{{ __('trainers.school_invite_btn') }}</button>
                </form>
            </div>
        </div>

        {{-- Список тренеров --}}
        <div class="ramka">
            <h2 class="-mt-05">{{ __('trainers.school_roster_list_title') }}</h2>

            @if($memberships->isEmpty())
            <div class="f-15" style="opacity:.6;">{{ __('trainers.school_roster_empty') }}</div>
            @endif

            @foreach($memberships as $m)
            @php
                $currentRate = $currentRates[$m->user_id] ?? null;
                $statusLabel = [
                    'pending'   => __('trainers.status_pending'),
                    'confirmed' => __('trainers.status_confirmed'),
                    'declined'  => __('trainers.status_declined'),
                ][$m->status] ?? $m->status;
            @endphp
            <div class="card mb-2" style="height:auto;">
                <div class="d-flex fvc between mb-1" style="flex-wrap:wrap;gap:8px;">
                    <div class="b-600">
                        {{ $m->trainer?->name ?? ('#' . $m->user_id) }}
                        <span class="f-13" style="opacity:.6;">— {{ $statusLabel }}</span>
                    </div>
                    <form method="POST" action="{{ route('volleyball_school.trainers.destroy', [$school, $m]) }}" onsubmit="return confirm('{{ __('trainers.school_trainer_remove_confirm') }}');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-secondary btn-small">{{ __('trainers.school_trainer_remove_btn') }}</button>
                    </form>
                </div>

                @if($m->status === 'confirmed')
                <form method="POST" action="{{ route('volleyball_school.trainers.update', [$school, $m]) }}" class="form mb-2">
                    @csrf
                    @method('PATCH')
                    <div class="d-flex" style="gap:16px;flex-wrap:wrap;">
                        <label class="checkbox-item">
                            <input type="checkbox" name="can_manage_schedule" value="1" {{ $m->can_manage_schedule ? 'checked' : '' }}>
                            <div class="custom-checkbox"></div>
                            <span>{{ __('trainers.perm_manage_schedule') }}</span>
                        </label>
                        <label class="checkbox-item">
                            <input type="checkbox" name="can_manage_registrations" value="1" {{ $m->can_manage_registrations ? 'checked' : '' }}>
                            <div class="custom-checkbox"></div>
                            <span>{{ __('trainers.perm_manage_registrations') }}</span>
                        </label>
                        <label class="checkbox-item">
                            <input type="checkbox" name="can_view_analytics" value="1" {{ $m->can_view_analytics ? 'checked' : '' }}>
                            <div class="custom-checkbox"></div>
                            <span>{{ __('trainers.perm_view_analytics') }}</span>
                        </label>
                    </div>
                    <button class="btn btn-small mt-1" type="submit">{{ __('trainers.perm_save_btn') }}</button>
                </form>

                <div class="f-15 mb-1">
                    {{ __('trainers.current_rate_label') }}:
                    @if($currentRate)
                        <strong>{{ $currentRate->rate }} ₽</strong> ({{ [
                            'hourly' => __('trainers.rate_type_hourly'),
                            'per_session' => __('trainers.rate_type_per_session'),
                            'fixed_monthly' => __('trainers.rate_type_fixed_monthly'),
                        ][$currentRate->rate_type] ?? $currentRate->rate_type }})
                    @else
                        <span style="opacity:.6;">{{ __('trainers.current_rate_none') }}</span>
                    @endif
                </div>

                <form method="POST" action="{{ route('volleyball_school.trainers.rate', [$school, $m]) }}" class="form">
                    @csrf
                    <div class="row row2">
                        <div class="col-4">
                            <label class="f-13 mb-05">{{ __('trainers.rate_type_label') }}</label>
                            <select name="rate_type">
                                <option value="hourly">{{ __('trainers.rate_type_hourly') }}</option>
                                <option value="per_session">{{ __('trainers.rate_type_per_session') }}</option>
                                <option value="fixed_monthly">{{ __('trainers.rate_type_fixed_monthly') }}</option>
                            </select>
                        </div>
                        <div class="col-4">
                            <label class="f-13 mb-05">{{ __('trainers.rate_amount_label') }}</label>
                            <input type="number" name="rate" min="0" step="0.01" required>
                        </div>
                        <div class="col-4">
                            <label class="f-13 mb-05">{{ __('trainers.rate_effective_from_label') }}</label>
                            <input type="date" name="effective_from">
                        </div>
                    </div>
                    <button class="btn btn-small mt-1" type="submit">{{ __('trainers.rate_save_btn') }}</button>
                </form>
                @endif
            </div>
            @endforeach
        </div>

    </div>

    <script>
    (function(){
        function boot(){
            var el = document.getElementById('invite-trainer-search');
            var dd = document.getElementById('invite-trainer-dd');
            var hidden = document.getElementById('invite-trainer-id');
            var submitBtn = document.getElementById('invite-trainer-submit');
            if (!el || !dd || !hidden || typeof jQuery === 'undefined') {
                return setTimeout(boot, 150);
            }
            var acBox = el.closest('.ac-box');
            var url = acBox && acBox.getAttribute('data-users-search-url');
            if (!url) return;

            function pos(){
                var r = el.getBoundingClientRect();
                dd.style.cssText = 'position:fixed;left:'+r.left+'px;top:'+(r.bottom+2)+'px;width:'+r.width+'px;z-index:99999;background:#fff;border:1px solid #ccc;max-height:30rem;overflow:auto;box-shadow:0 .4rem 1.2rem rgba(0,0,0,.15);display:block';
            }
            function hide(){ dd.style.display='none'; dd.innerHTML=''; }

            function render(items){
                if(!items.length){hide();return;}
                dd.innerHTML = items.map(function(u){
                    var lbl = String(u.label).replace(/</g,'&lt;').replace(/"/g,'&quot;');
                    return '<div class="invite-trainer-item" data-id="'+u.id+'" data-label="'+lbl+'" style="padding:.6rem 1rem;cursor:pointer;border-bottom:1px solid #eee;color:#222">'+lbl+'</div>';
                }).join('');
                pos();
                dd.querySelectorAll('.invite-trainer-item').forEach(function(it){
                    it.addEventListener('click', function(){
                        hidden.value = it.getAttribute('data-id');
                        el.value = it.getAttribute('data-label');
                        submitBtn.disabled = false;
                        hide();
                    });
                });
            }

            if (dd.parentNode !== document.body) document.body.appendChild(dd);

            var timer=null, last=el.value.trim();
            setInterval(function(){
                var v=(el.value||'').trim();
                if(v===last)return;
                last=v;
                hidden.value='';
                submitBtn.disabled = true;
                clearTimeout(timer);
                if(v.length<2){hide();return;}
                timer=setTimeout(function(){
                    jQuery.ajax({
                        url:url, data:{q:v,limit:10}, dataType:'json',
                        success:function(d){ render((d&&d.items)||[]); },
                        error:function(){ hide(); }
                    });
                },200);
            },200);

            document.addEventListener('click', function(e){
                if(e.target===el)return;
                if(dd.contains(e.target))return;
                hide();
            });
            window.addEventListener('scroll', function(){ if(dd.style.display==='block')pos(); }, true);
            window.addEventListener('resize', function(){ if(dd.style.display==='block')pos(); });
            hide();
        }
        boot();
    })();
    </script>

</x-voll-layout>
