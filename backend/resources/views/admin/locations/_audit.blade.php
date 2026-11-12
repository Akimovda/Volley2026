{{-- Журнал изменений локации: кто, когда и что изменил (администратор и владелец площадки) --}}
@php
    $fieldLabel = function (string $f): string {
        $key = 'admin.loc_audit_f_' . str_replace('.', '_', $f);
        $t = __($key);
        if ($t !== $key) return $t;
        // direction.classic.courts / price.beach.base → «Классика: …»
        if (preg_match('/^(direction|price)\.(classic|beach)\.(\w+)$/', $f, $m)) {
            $dir = __('admin.loc_audit_dir_' . $m[2]);
            $what = __('admin.loc_audit_f_' . $m[1] . '_' . $m[3]);
            return $dir . ': ' . $what;
        }
        return $f;
    };
    $empty = '—';
    $fmt = function (string $f, $v) use ($empty) {
        $v = (string) $v;
        if ($v === '') return $empty;
        if (str_ends_with($f, '.active')) return $v === '1' ? __('admin.loc_audit_on') : __('admin.loc_audit_off');
        return $v;
    };
@endphp
<div class="ramka" id="location-audit">
    <h2 class="-mt-05">{{ __('admin.loc_audit_title') }}</h2>
    <div class="f-14 cd mb-2">{{ __('admin.loc_audit_hint') }}</div>

    @forelse($locationAudit as $row)
        @php $m = $row->meta; @endphp
        <div class="card mb-1">
            <div class="d-flex between" style="flex-wrap:wrap;gap:8px">
                <div class="b-600">
                    {{ __('admin.loc_audit_a_' . str_replace('.', '_', $row->action)) }}
                    <span class="f-13 cd">— {{ $row->actor }}@if(($m['actor_role'] ?? '') === 'admin') ({{ __('admin.loc_audit_role_admin') }})@endif</span>
                </div>
                <div class="f-13 cd">{{ \Carbon\Carbon::parse($row->created_at)->timezone(config('app.timezone'))->format('d.m.Y H:i') }}</div>
            </div>

            @if(!empty($m['changes']))
                <ul class="list f-14 mt-1">
                    @foreach($m['changes'] as $field => $pair)
                        <li><b>{{ $fieldLabel($field) }}:</b> {{ $fmt($field, $pair[0] ?? '') }} → {{ $fmt($field, $pair[1] ?? '') }}</li>
                    @endforeach
                </ul>
            @endif
            @if(!empty($m['photos_uploaded']))
                <div class="f-14 mt-1">{{ __('admin.loc_audit_photos_uploaded', ['n' => $m['photos_uploaded']]) }}</div>
            @endif
            @if(!empty($m['organizer_name']))
                <div class="f-14 mt-1">{{ $m['organizer_name'] }}@if(!empty($m['trust_level'])) — {{ __('club.trust_level_' . $m['trust_level']) }}@endif</div>
            @endif
        </div>
    @empty
        <div class="f-14 cd">{{ __('admin.loc_audit_empty') }}</div>
    @endforelse
</div>
