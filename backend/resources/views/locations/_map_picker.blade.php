{{--
    Выбор точки на карте (Яндекс.Карты). Заполняет inputs name="lat" / name="lng" в ближайшей форме.
    Параметры: $cityName (string|null) — центр карты, если координат ещё нет;
               $mapHeight (string|null) — высота карты, по умолчанию 34rem;
               $addressSel (string|null) — CSS-селектор поля адреса: кнопка «Найти» ищет по нему,
               при клике по карте пустой адрес заполняется из геокодера.
--}}
@php
    $mpId = 'locmap' . substr(md5(uniqid('', true)), 0, 6);
@endphp
<div class="loc-map-picker" id="{{ $mpId }}-wrap">
    <div class="d-flex gap-1 mb-1" style="flex-wrap:wrap;align-items:center;">
        <button type="button" class="btn btn-secondary" id="{{ $mpId }}-find">{{ __('locations.map_find_by_address') }}</button>
        <span class="f-13" id="{{ $mpId }}-msg">{{ __('locations.map_hint') }}</span>
    </div>
    <div id="{{ $mpId }}" style="height:{{ $mapHeight ?? '34rem' }};width:100%;border-radius:1rem;overflow:hidden;background:#e5e5e5;"></div>
</div>
<script>
(function () {
    var ID = @json($mpId);
    var cityName = @json((string) ($cityName ?? ''));
    var addressSel = @json((string) ($addressSel ?? ''));
    var T = {
        notFound: @json(__('locations.map_not_found')),
        picked:   @json(__('locations.map_picked')),
        hint:     @json(__('locations.map_hint')),
        noKey:    @json(__('locations.map_unavailable')),
    };
    var wrap = document.getElementById(ID + '-wrap');
    var form = wrap.closest('form');
    var latInp = form.querySelector('[name="lat"]');
    var lngInp = form.querySelector('[name="lng"]');
    var msg = document.getElementById(ID + '-msg');
    var addrInp = addressSel ? form.querySelector(addressSel) : null;
    var cityEl = form.querySelector('#city_search'); // админские формы: город — живой автокомплит
    function getCity() {
        if (cityEl && cityEl.value.trim()) return cityEl.value.split('(')[0].trim();
        return cityName;
    }
    if (!latInp || !lngInp) return;

    function num(v) { var n = parseFloat(String(v).replace(',', '.')); return isFinite(n) ? n : null; }

    function init() {
        var lat = num(latInp.value), lng = num(lngInp.value);
        var has = lat !== null && lng !== null;
        var map = new ymaps.Map(ID, {
            center: has ? [lat, lng] : [55.751244, 37.618423],
            zoom: has ? 16 : 10,
            controls: ['zoomControl', 'geolocationControl', 'fullscreenControl']
        });
        var mark = null;

        function place(coords) {
            if (!mark) {
                mark = new ymaps.Placemark(coords, {}, { draggable: true, preset: 'islands#redSportIcon' });
                mark.events.add('dragend', function () { setCoords(mark.geometry.getCoordinates(), true); });
                map.geoObjects.add(mark);
            } else {
                mark.geometry.setCoordinates(coords);
            }
        }
        function setCoords(coords, fillAddr) {
            latInp.value = coords[0].toFixed(6);
            lngInp.value = coords[1].toFixed(6);
            latInp.dispatchEvent(new Event('change', { bubbles: true }));
            place(coords);
            msg.textContent = T.picked;
            if (fillAddr && addrInp && !addrInp.value.trim()) {
                ymaps.geocode(coords, { results: 1 }).then(function (r) {
                    var o = r.geoObjects.get(0);
                    if (o && !addrInp.value.trim()) {
                        var line = String(o.getAddressLine() || '');
                        // убираем страну/город в начале — город и так выбран отдельно
                        addrInp.value = line.split(',').slice(2).join(',').trim() || line;
                    }
                });
            }
        }

        if (has) {
            place([lat, lng]);
        } else if (getCity()) {
            ymaps.geocode(getCity(), { results: 1 }).then(function (r) {
                var o = r.geoObjects.get(0);
                if (o) map.setCenter(o.geometry.getCoordinates(), 11);
            });
        }

        map.events.add('click', function (e) { setCoords(e.get('coords'), true); });

        function manual() {
            var a = num(latInp.value), b = num(lngInp.value);
            if (a !== null && b !== null && Math.abs(a) <= 90 && Math.abs(b) <= 180) {
                place([a, b]); map.setCenter([a, b], 16);
            }
        }
        latInp.addEventListener('change', manual);
        lngInp.addEventListener('change', manual);

        function find() {
            var q = (addrInp ? addrInp.value.trim() : '');
            if (!q) { msg.textContent = T.hint; if (addrInp) addrInp.focus(); return; }
            ymaps.geocode((getCity() ? getCity() + ', ' : '') + q, { results: 1 }).then(function (r) {
                var o = r.geoObjects.get(0);
                if (!o) { msg.textContent = T.notFound; return; }
                var c = o.geometry.getCoordinates();
                map.setCenter(c, 17);
                setCoords(c, false);
            });
        }
        document.getElementById(ID + '-find').addEventListener('click', find);
        if (addrInp) addrInp.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); find(); }
        });
    }

    var key = @json((string) config('services.yandex_maps.key'));
    if (!key) { msg.textContent = T.noKey; return; }
    function boot() { ymaps.ready(init); }
    if (window.ymaps) { boot(); return; }
    var s = document.createElement('script');
    s.src = 'https://api-maps.yandex.ru/2.1/?apikey=' + encodeURIComponent(key) + '&lang=' + @json(__('locations.yandex_lang'));
    s.onload = boot;
    s.onerror = function () { msg.textContent = T.noKey; };
    document.head.appendChild(s);
})();
</script>
