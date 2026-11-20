{{--
    Partial: events._partials.auto_quorum — тумблер «Авто увеличение кворума» (docs/auto-quorum.md).
    Используется в форме создания (step1, блок «Режим регистрации») и редактирования (после «Макс. игроков»).

    Expects:
      - $aqEnabled (bool), $aqPer (int|null), $aqMax (int|null) — текущие значения (old() поверх)
      - $aqShowIf (string|null) — data-show-if для формы создания (видимость по формату/режиму)
      - $aqHideIf (string|null) — data-hide-if (например, collect_stats=1)
      - $aqIds (array) — id полей формы: direction, subtype, libero, teams, stats
      - $aqDirection (string|null) — направление, если в форме его не меняют (редактирование)
    Тумблер — тот же компонент .up-switch, что в профиле (как на iPhone). Иконки — x-ico, без эмодзи.
--}}
@php
    $aqOn  = (bool) old('auto_quorum_enabled', $aqEnabled ?? false);
    $aqPerV = old('auto_quorum_players_per_team', $aqPer ?? null);
    $aqMaxV = old('auto_quorum_max_teams', $aqMax ?? null);
    $aqI18n = [
        'per_team'   => __('events.auto_quorum_per_team'),
        'per_pair'   => __('events.auto_quorum_per_pair'),
        'max_teams'  => __('events.auto_quorum_max_teams'),
        'max_pairs'  => __('events.auto_quorum_max_pairs'),
        'hint'       => __('events.auto_quorum_hint'),
        'hint_beach' => __('events.auto_quorum_hint_beach'),
        'first_team' => __('events.auto_quorum_calc_first_team'),
        'first_pair' => __('events.auto_quorum_calc_first_pair'),
        'next'       => __('events.auto_quorum_calc_next'),
        'none'       => __('events.auto_quorum_calc_none'),
        'none_pair'  => __('events.auto_quorum_calc_none_pair'),
    ];
@endphp
<div class="aq-block mt-1" id="aq_block"
     @if(!empty($aqShowIf)) data-show-if="{{ $aqShowIf }}" @endif
     @if(!empty($aqHideIf)) data-hide-if="{{ $aqHideIf }}" @endif
     data-aq-ids="{{ json_encode($aqIds ?? [], JSON_UNESCAPED_UNICODE) }}"
     data-aq-direction="{{ $aqDirection ?? '' }}"
     data-aq-i18n="{{ json_encode($aqI18n, JSON_UNESCAPED_UNICODE) }}">
    <input type="hidden" name="auto_quorum_enabled" id="aq_enabled_input" value="{{ $aqOn ? 1 : 0 }}">
    <div class="up-row" style="border-top:0;padding-top:0">
        <span class="up-row-l"><x-ico name="trend" size="1.2em" /> {{ __('events.auto_quorum_title') }}</span>
        <button type="button" class="up-switch {{ $aqOn ? 'is-on' : '' }}" id="aq_switch" role="switch"
                aria-checked="{{ $aqOn ? 'true' : 'false' }}" aria-label="{{ __('events.auto_quorum_title') }}"><i></i></button>
    </div>
    <div class="f-13" id="aq_hint" style="opacity:.7">{{ __('events.auto_quorum_hint') }}</div>

    <div id="aq_fields" class="mt-1" style="{{ $aqOn ? '' : 'display:none' }}">
        <div class="row">
            <div class="col-6" style="display:flex;flex-direction:column">
                <label id="aq_per_label" for="aq_per" style="flex:1">{{ __('events.auto_quorum_per_team') }}</label>
                <input type="number" name="auto_quorum_players_per_team" id="aq_per" min="1" max="20" value="{{ $aqPerV }}">
            </div>
            <div class="col-6" style="display:flex;flex-direction:column">
                <label id="aq_max_label" for="aq_max" style="flex:1">{{ __('events.auto_quorum_max_teams') }}</label>
                <input type="number" name="auto_quorum_max_teams" id="aq_max" min="2" max="50" value="{{ $aqMaxV }}">
            </div>
        </div>
        <div class="f-16 b-600 mt-1" id="aq_calc"></div>
        <div class="f-13" style="opacity:.7">{{ __('events.auto_quorum_counted') }}</div>
    </div>
    @foreach(['auto_quorum_enabled', 'auto_quorum_players_per_team', 'auto_quorum_max_teams'] as $__aqField)
        @error($__aqField)<div class="text-xs text-red-600 mt-1">{{ $message }}</div>@enderror
    @endforeach
</div>
<script>
(function () {
    var box = document.getElementById('aq_block');
    if (!box || box.dataset.aqInit) return;
    box.dataset.aqInit = '1';
    var ids = JSON.parse(box.dataset.aqIds || '{}');
    var t = JSON.parse(box.dataset.aqI18n || '{}');
    var sw = document.getElementById('aq_switch'), inp = document.getElementById('aq_enabled_input');
    var fields = document.getElementById('aq_fields'), per = document.getElementById('aq_per'), max = document.getElementById('aq_max');
    var calc = document.getElementById('aq_calc');
    function el(id) { return id ? document.getElementById(id) : null; }
    function val(id, d) { var e = el(id); return e ? String(e.value || '') : d; }
    function direction() { return box.dataset.aqDirection || val(ids.direction, 'classic') || 'classic'; }
    function isBeach() { return direction() === 'beach'; }
    // Игроков на команду по умолчанию: классика — размер команды − 1 (4-2/5-1 → 5, с либеро → 6, 4×4 → 3); пляж — 2
    function defaultPer() {
        if (isBeach()) { var s = val(ids.subtype, '2x2'); return s === '3x3' ? 3 : (s === '4x4' ? 4 : 2); }
        var st = val(ids.subtype, '4x2'), lib = val(ids.libero, '');
        if (st === '4x4') return 3;
        if (st === '5x1' && lib === 'with_libero') return 6;
        return 5;
    }
    function fmt(s, n, c) { return String(s).replace(':n', n).replace(':count', c); }
    function setOn(on) {
        sw.classList.toggle('is-on', on);
        sw.setAttribute('aria-checked', on ? 'true' : 'false');
        inp.value = on ? '1' : '0';
        fields.style.display = on ? '' : 'none';
        if (on && !per.value) per.value = defaultPer();
        if (on && !max.value) max.value = (parseInt(val(ids.teams, '2'), 10) || 2) + 2; // пример расчёта сразу виден
        render();
    }
    function render() {
        var beach = isBeach();
        document.getElementById('aq_per_label').textContent = beach ? t.per_pair : t.per_team;
        document.getElementById('aq_max_label').textContent = beach ? t.max_pairs : t.max_teams;
        document.getElementById('aq_hint').textContent = beach ? t.hint_beach : t.hint;
        per.placeholder = defaultPer();
        var base = parseInt(val(ids.teams, '2'), 10) || 2;
        var p = parseInt(per.value, 10) || defaultPer();
        var m = parseInt(max.value, 10) || 0;
        if (m <= base) { calc.textContent = beach ? t.none_pair : t.none; return; }
        var parts = [];
        for (var k = base + 1; k <= m && parts.length < 6; k++) {
            parts.push(fmt(parts.length ? t.next : (beach ? t.first_pair : t.first_team), k, k * p));
        }
        calc.textContent = parts.join(', ');
    }
    // скрытый блок (неподходящий формат/режим, включена «Статистика») — функция выключается
    function syncHidden() {
        var stats = el(ids.stats);
        var hidden = box.classList.contains('hidden') || (stats && stats.checked);
        if (stats) box.style.display = stats.checked ? 'none' : '';
        if (hidden && inp.value === '1') setOn(false);
    }
    sw.addEventListener('click', function () { setOn(inp.value !== '1'); });
    [per, max].forEach(function (e) { e.addEventListener('input', render); });
    [ids.direction, ids.subtype, ids.libero, ids.teams, ids.stats, 'format', 'registration_mode'].forEach(function (id) {
        var e = el(id);
        if (e) { e.addEventListener('change', function () { setTimeout(function () { syncHidden(); render(); }, 0); }); e.addEventListener('input', render); }
    });
    render();
    setTimeout(syncHidden, 0);
})();
</script>
