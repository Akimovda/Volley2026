{{--
    Partial: events._partials.trainer  (multi)

    Expects:
      - $event              (Event)
      - $trainers           (Collection<User>) — эффективные тренеры
      - $trainerInherited   (bool)

    JS (/js/occurrence-edit.js) ищет id:
      occ_trainer_chips, occ_trainer_search, occ_trainer_dd, occ_trainer_clear
--}}
@php
    $oldIds = old('trainer_user_ids');
    if (is_string($oldIds)) $oldIds = [$oldIds];
    if (!is_array($oldIds)) {
        $oldIds = $trainers->pluck('id')->map(fn($v) => (int)$v)->all();
    } else {
        $oldIds = array_values(array_filter(array_map('intval', $oldIds), fn($v) => $v > 0));
    }

    $namesById = $trainers->keyBy('id')->map(fn($u) => $u->name)->all();
    $missing = array_diff($oldIds, array_keys($namesById));
    if (!empty($missing)) {
        $extra = \App\Models\User::whereIn('id', $missing)->get();
        foreach ($extra as $u) {
            $namesById[$u->id] = $u->name;
        }
    }
@endphp
<div class="ramka">
    <h2 class="-mt-05">Тренеры</h2>
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <label>Тренеры занятия</label>

                @if($trainerInherited && $trainers->count() > 0)
                    <div class="f-13">Сейчас тренеры наследуются от серии. Измени список — сохранится как override для этого занятия.</div>
                @elseif($trainerInherited && $trainers->count() === 0)
                    <div class="f-13">В серии тренеры не указаны. Добавь — сохранится только для этого занятия.</div>
                @else
                    <div class="f-13">Override: список отличается от серии. Верни его в точности как в серии, чтобы снова наследовать.</div>
                @endif

                <div class="ac-box" data-users-search-url="{{ route('api.users.search') }}">
                    <div id="occ_trainer_chips" class="mb-1">
                        @foreach($oldIds as $tid)
                            <span class="chip custom-chip" data-chip-id="{{ $tid }}">
                                {{ $namesById[$tid] ?? ('User #' . $tid) }}
                                <span class="occ-trainer-chip-remove chip-remove" data-id="{{ $tid }}">×</span>
                            </span>
                            <input type="hidden" name="trainer_user_ids[]" value="{{ $tid }}" data-occ-trainer-hidden="{{ $tid }}">
                        @endforeach
                    </div>

                    <input type="text"
                           id="occ_trainer_search"
                           placeholder="Начни вводить имя или фамилию"
                           value=""
                           autocomplete="off">

                    <div id="occ_trainer_dd"></div>
                </div>

                <ul class="list f-16 mt-1">
                    <li>Можно выбрать несколько тренеров.</li>
                    <li><a onclick="return false;" href="#" type="button" id="occ_trainer_clear" class="f-16 blink">Сбросить все</a></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    // Trainer multi-autocomplete — inline, запускается сразу при парсе
    function boot(){
        var el    = document.getElementById('occ_trainer_search');
        var dd    = document.getElementById('occ_trainer_dd');
        var chips = document.getElementById('occ_trainer_chips');
        var clr   = document.getElementById('occ_trainer_clear');
        if (!el || !dd || !chips || typeof jQuery === 'undefined') {
            return setTimeout(boot, 150); // пока не все готово — ждем
        }
        if (window.__occTrainerBooted) return;
        window.__occTrainerBooted = true;

        var acBox = el.closest('.ac-box');
        var url = acBox && acBox.getAttribute('data-users-search-url');
        if (!url) return;

        if (dd.parentNode !== document.body) document.body.appendChild(dd);

        function pos(){
            var r = el.getBoundingClientRect();
            dd.style.cssText = 'position:fixed;left:'+r.left+'px;top:'+(r.bottom+2)+'px;width:'+r.width+'px;z-index:99999;background:#fff;border:1px solid #ccc;max-height:30rem;overflow:auto;box-shadow:0 .4rem 1.2rem rgba(0,0,0,.15);display:block';
        }
        function hide(){ dd.style.display='none'; dd.innerHTML=''; }

        function currentIds(){
            var a=[];
            chips.querySelectorAll('input[data-occ-trainer-hidden]').forEach(function(h){
                var v=parseInt(h.value,10); if(v>0)a.push(v);
            });
            return a;
        }

        function addChip(id,label){
            id=parseInt(id,10);
            if(!(id>0)||currentIds().indexOf(id)>-1)return;
            var s=document.createElement('span');
            s.className='chip custom-chip';
            s.setAttribute('data-chip-id',String(id));
            s.textContent=label||('#'+id);
            var b=document.createElement('span');
            b.className='chip-remove';
            b.setAttribute('data-id',String(id));
            b.textContent='\u00d7';
            b.style.cssText='margin-left:.5rem;cursor:pointer';
            b.addEventListener('click',function(){rmChip(id);});
            s.appendChild(b);
            var h=document.createElement('input');
            h.type='hidden'; h.name='trainer_user_ids[]';
            h.value=String(id); h.setAttribute('data-occ-trainer-hidden',String(id));
            chips.appendChild(s); chips.appendChild(h);
        }
        function rmChip(id){
            id=parseInt(id,10);
            chips.querySelectorAll('[data-occ-trainer-hidden="'+id+'"]').forEach(function(x){x.remove();});
            chips.querySelectorAll('[data-chip-id="'+id+'"]').forEach(function(x){x.remove();});
        }

        chips.addEventListener('click',function(e){
            var t=e.target;
            if(t&&t.classList&&t.classList.contains('chip-remove')){
                var id=t.getAttribute('data-id'); if(id)rmChip(id);
            }
        });
        if(clr) clr.addEventListener('click',function(e){e.preventDefault();chips.innerHTML='';});

        function render(items){
            if(!items.length){hide();return;}
            var ex={}; currentIds().forEach(function(i){ex[i]=true;});
            dd.innerHTML=items.map(function(u){
                var dis=ex[u.id]?';opacity:.4;pointer-events:none':'';
                var lbl=String(u.label).replace(/</g,'&lt;').replace(/"/g,'&quot;');
                var meta=u.meta?'<div style="font-size:1.3rem;color:#888">'+String(u.meta).replace(/</g,'&lt;')+'</div>':'';
                return '<div class="trainer-item" data-id="'+u.id+'" data-label="'+lbl+'" style="padding:.6rem 1rem;cursor:pointer;border-bottom:1px solid #eee;color:#222'+dis+'">'+lbl+meta+'</div>';
            }).join('');
            pos();
            dd.querySelectorAll('.trainer-item').forEach(function(it){
                it.addEventListener('click',function(){
                    addChip(it.getAttribute('data-id'),it.getAttribute('data-label'));
                    el.value=''; hide();
                });
            });
        }

        var timer=null, last=el.value.trim();
        setInterval(function(){
            var v=(el.value||'').trim();
            if(v===last)return;
            last=v;
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

        el.addEventListener('keydown',function(e){
            if(e.key==='Enter'){e.preventDefault();}
        });

        document.addEventListener('click',function(e){
            if(e.target===el)return;
            if(dd.contains(e.target))return;
            hide();
        });

        window.addEventListener('scroll',function(){ if(dd.style.display==='block')pos(); },true);
        window.addEventListener('resize',function(){ if(dd.style.display==='block')pos(); });
        hide();
    }
    boot();
})();
</script>

