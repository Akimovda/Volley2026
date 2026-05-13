{{-- resources/views/user/teams/edit.blade.php --}}
@php
$isNew = !isset($team) || !$team->exists;
$title = $isNew ? 'Новая команда' : ('Редактировать: ' . $team->name);
$posLabels = ['setter'=>'Связующий','outside'=>'Доигровщик','opposite'=>'Диагональный','middle'=>'Центральный','libero'=>'Либеро'];
$roleLabels = ['captain'=>'Капитан','player'=>'Основной','reserve'=>'Запасной'];
$currentMembers = $isNew ? collect() : $team->members->sortBy(fn($m)=>$m->role_code==='captain'?0:1);
$captainId = $isNew ? auth()->id() : (int)$team->user_id;
$validationErrors = session('team_validation_errors', []);
$teamSizeError = session('team_size_error', null);
$event = null;
if (request()->has('event_id') || session('return_event_id')) {
    $eid = request()->input('event_id') ?: session('return_event_id');
    $event = $eid ? \App\Models\Event::find($eid) : null;
}
@endphp

<x-voll-layout>
<x-slot name="title">{{ $title }}</x-slot>
<x-slot name="h1">{{ $title }}</x-slot>

<div class="container">

{{-- Validation errors from tournament check --}}
@if(!empty($validationErrors))
<div class="ramka">
    <div class="alert alert-error">
        <div class="alert-title">⚠️ Некоторые участники не соответствуют требованиям турнира</div>
        @foreach($validationErrors as $err)
        <div class="mt-1">
            <strong>{{ $err['name'] }}</strong>:
            {{ implode('; ', $err['issues']) }}
        </div>
        @endforeach
        @if($event)
        <div class="mt-2 f-15" style="opacity:.7">Исправьте состав команды и попробуйте снова зарегистрироваться на турнир <strong>{{ $event->title }}</strong>.</div>
        @endif
    </div>
</div>
@endif

@if(!empty($teamSizeError))
<div class="ramka">
    <div class="alert alert-error">⚠️ {{ $teamSizeError }}</div>
</div>
@endif

@if(session('status'))
<div class="ramka"><div class="alert alert-success">{{ session('status') }}</div></div>
@endif

<div class="form">
<form method="POST"
    action="{{ $isNew ? route('user.teams.store') : route('user.teams.update', $team->id) }}"
    id="user-team-form">
    @csrf
    @if(!$isNew) @method('PUT') @endif
    @if($event)
    <input type="hidden" name="return_to" value="{{ url()->current() }}">
    @endif

    <div class="ramka">
        <h2 class="-mt-05">Основное</h2>
        <div class="row">
            <div class="col-md-5">
                <div class="card">
                    <label>Название команды</label>
                    <input type="text" name="name" required maxlength="255"
                        value="{{ old('name', $team->name ?? '') }}"
                        placeholder="Название команды">
                    @error('name')<div class="text-xs text-red-600 mt-1">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="col-md-3">
                <div class="card" style="overflow:visible">
                    <label>Направление</label>
                    <select name="direction" id="utm-direction">
                        <option value="classic" @selected(old('direction', $team->direction ?? 'classic') === 'classic')>Классический</option>
                        <option value="beach" @selected(old('direction', $team->direction ?? 'classic') === 'beach')>Пляжный</option>
                    </select>
                </div>
            </div>
            <div class="col-md-4" id="utm-subtype-wrap">
                <div class="card" style="overflow:visible">
                    <label>Формат</label>
                    <select name="subtype" id="utm-subtype">
                        @php $curSubtype = old('subtype', $team->subtype ?? '4x4'); @endphp
                        <optgroup label="Классика">
                            <option value="4x4" @selected($curSubtype==='4x4')>4×4</option>
                            <option value="4x2" @selected($curSubtype==='4x2')>4×2</option>
                            <option value="5x1" @selected($curSubtype==='5x1')>5×1</option>
                        </optgroup>
                        <optgroup label="Пляж">
                            <option value="2x2" @selected($curSubtype==='2x2')>2×2</option>
                            <option value="3x3" @selected($curSubtype==='3x3')>3×3</option>
                            <option value="4x4b" @selected($curSubtype==='4x4b')>4×4 (пляж)</option>
                        </optgroup>
                    </select>
                </div>
            </div>
        </div>
    </div>

    {{-- Members --}}
    <div class="ramka">
        <h2 class="-mt-05">Состав команды</h2>

        <div id="utm-members-list">
            {{-- Captain (always first, non-removable) --}}
            <div class="card mb-1 d-flex between fvc utm-member-row" data-uid="{{ $captainId }}">
                <input type="hidden" name="members[0][user_id]" value="{{ $captainId }}">
                <input type="hidden" name="members[0][role_code]" value="captain">
                <div class="d-flex fvc gap-2">
                    <span class="b-600">{{ trim(auth()->user()->last_name . ' ' . auth()->user()->first_name) ?: auth()->user()->name }}</span>
                    <span class="f-13 alert-info p-1 pt-05 pb-05">Капитан</span>
                </div>
                <div class="d-flex gap-1 utm-pos-wrap">
                    <select name="members[0][position_code]" class="utm-pos-select" style="width:auto">
                        <option value="">Позиция</option>
                        @foreach($posLabels as $k => $v)
                        <option value="{{ $k }}" @selected(($currentMembers->firstWhere('user_id', $captainId)?->position_code ?? '') === $k)>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Other members --}}
            @foreach($currentMembers->where('user_id', '!=', $captainId) as $i => $mem)
            @php $idx = $i + 1; @endphp
            <div class="card mb-1 d-flex between fvc utm-member-row" data-uid="{{ $mem->user_id }}">
                <input type="hidden" name="members[{{ $idx }}][user_id]" value="{{ $mem->user_id }}">
                <div class="d-flex fvc gap-2">
                    <span class="b-600">{{ trim(($mem->user->last_name ?? '') . ' ' . ($mem->user->first_name ?? '')) ?: ($mem->user->name ?? 'User #' . $mem->user_id) }}</span>
                </div>
                <div class="d-flex gap-1 fvc utm-pos-wrap">
                    <select name="members[{{ $idx }}][role_code]" class="utm-role-select" style="width:auto">
                        @foreach(['player'=>'Основной','reserve'=>'Запасной'] as $k => $v)
                        <option value="{{ $k }}" @selected(($mem->role_code ?? 'player') === $k)>{{ $v }}</option>
                        @endforeach
                    </select>
                    <select name="members[{{ $idx }}][position_code]" class="utm-pos-select" style="width:auto">
                        <option value="">Позиция</option>
                        @foreach($posLabels as $k => $v)
                        <option value="{{ $k }}" @selected(($mem->position_code ?? '') === $k)>{{ $v }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="btn btn-danger btn-small utm-remove-member">✕</button>
                </div>
            </div>
            @endforeach
        </div>

        {{-- Add player autocomplete --}}
        <div class="mt-2" style="position:relative" id="utm-ac-wrap">
            <label>Добавить игрока</label>
            <input type="text" id="utm-ac-input" autocomplete="off" class="form-control"
                placeholder="Введите имя или email…">
            <div id="utm-ac-dd" class="form-select-dropdown trainer_dd"></div>
        </div>
        <div class="f-14 mt-1" style="opacity:.6">Добавленные игроки получат приглашение при использовании команды для записи на турнир.</div>
    </div>

    <div class="ramka text-center">
        <a href="{{ route('profile.show') }}" class="btn btn-secondary mr-2">Отмена</a>
        <button type="submit" class="btn">{{ $isNew ? 'Создать команду' : 'Сохранить изменения' }}</button>
    </div>
</form>
</div>
</div>

<x-slot name="script">
<script>
(function() {
    var memberIdx = {{ $isNew ? 1 : ($currentMembers->where('user_id','!=',$captainId)->count() + 1) }};
    var addedUids = new Set([{{ $captainId }}@foreach($currentMembers->where('user_id','!=',$captainId) as $m), {{ $m->user_id }}@endforeach]);
    var isClassic = document.getElementById('utm-direction')?.value !== 'beach';
    var posLabels = @json($posLabels);

    // Hide position selects for beach
    function syncDirection() {
        var dir = document.getElementById('utm-direction')?.value;
        isClassic = dir === 'classic';
        document.querySelectorAll('.utm-pos-select').forEach(function(sel) {
            sel.style.display = isClassic ? '' : 'none';
        });
    }
    document.getElementById('utm-direction')?.addEventListener('change', syncDirection);
    syncDirection();

    // Remove member
    document.getElementById('utm-members-list').addEventListener('click', function(e) {
        var btn = e.target.closest('.utm-remove-member');
        if (!btn) return;
        var row = btn.closest('.utm-member-row');
        var uid = parseInt(row?.getAttribute('data-uid') || 0);
        if (uid) addedUids.delete(uid);
        row?.remove();
        renumberMembers();
    });

    function renumberMembers() {
        document.querySelectorAll('#utm-members-list .utm-member-row').forEach(function(row, i) {
            row.querySelectorAll('input, select').forEach(function(el) {
                el.name = el.name.replace(/members\[\d+\]/, 'members[' + i + ']');
            });
        });
        memberIdx = document.querySelectorAll('#utm-members-list .utm-member-row').length;
    }

    function addMember(id, label) {
        if (addedUids.has(id)) return;
        addedUids.add(id);
        var idx = memberIdx++;
        var posOpts = '<option value="">Позиция</option>';
        Object.entries(posLabels).forEach(function(entry) {
            posOpts += '<option value="' + entry[0] + '">' + entry[1] + '</option>';
        });
        var html = '<div class="card mb-1 d-flex between fvc utm-member-row" data-uid="' + id + '">' +
            '<input type="hidden" name="members[' + idx + '][user_id]" value="' + id + '">' +
            '<div class="d-flex fvc gap-2"><span class="b-600">' + String(label||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;') + '</span></div>' +
            '<div class="d-flex gap-1 fvc utm-pos-wrap">' +
            '<select name="members[' + idx + '][role_code]" class="utm-role-select" style="width:auto"><option value="player">Основной</option><option value="reserve">Запасной</option></select>' +
            '<select name="members[' + idx + '][position_code]" class="utm-pos-select" style="width:auto">' + posOpts + '</select>' +
            '<button type="button" class="btn btn-danger btn-small utm-remove-member">✕</button>' +
            '</div></div>';
        document.getElementById('utm-members-list').insertAdjacentHTML('beforeend', html);
        syncDirection();
    }

    // Autocomplete
    var input = document.getElementById('utm-ac-input');
    var dd    = document.getElementById('utm-ac-dd');
    var timer = null;
    function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
    function showDd() { dd.classList.add('form-select-dropdown--active'); }
    function hideDd() { dd.classList.remove('form-select-dropdown--active'); }

    if (input) {
        input.addEventListener('input', function() {
            clearTimeout(timer);
            var q = input.value.trim();
            if (q.length < 2) { hideDd(); return; }
            dd.innerHTML = '<div class="city-message">Поиск…</div>'; showDd();
            timer = setTimeout(function() {
                fetch('/api/users/search?q=' + encodeURIComponent(q), {
                    headers: {'Accept':'application/json'}, credentials:'same-origin'
                }).then(function(r){return r.json();}).then(function(data){
                    dd.innerHTML = '';
                    var items = data.items || [];
                    if (!items.length) { dd.innerHTML = '<div class="city-message">Ничего не найдено</div>'; showDd(); return; }
                    items.forEach(function(item) {
                        var div = document.createElement('div');
                        div.className = 'trainer-item form-select-option';
                        div.style.opacity = addedUids.has(item.id) ? '0.4' : '1';
                        div.innerHTML = '<span class="b-500">' + esc(item.label || item.name) + '</span>';
                        if (!addedUids.has(item.id)) {
                            div.addEventListener('click', function() {
                                addMember(item.id, item.label || item.name);
                                input.value = '';
                                hideDd();
                            });
                        }
                        dd.appendChild(div);
                    });
                    showDd();
                }).catch(function() {
                    dd.innerHTML = '<div class="city-message">Ошибка загрузки</div>'; showDd();
                });
            }, 250);
        });

        document.addEventListener('click', function(e) {
            if (!document.getElementById('utm-ac-wrap')?.contains(e.target)) hideDd();
        });

        input.addEventListener('keydown', function(e) { if (e.key === 'Escape') hideDd(); });
    }
})();
</script>
</x-slot>
</x-voll-layout>
