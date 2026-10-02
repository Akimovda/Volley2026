{{-- Быстрое создание локации организатором. Открывается в модалке мастера (?embed=1) --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('locations.org_create_title') }}</title>
    <link href="/assets/lib.css" rel="stylesheet">
    <link href="/assets/style.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { padding: 1.6rem; margin: 0; }
        .loc-org-row { margin-bottom: 1.4rem; }
        .loc-org-coords { display: flex; gap: 1rem; }
        .loc-org-coords > div { flex: 1; }
        .loc-org-err { color: #d33; font-size: 1.3rem; margin-top: .4rem; }
    </style>
</head>
<body>
<script>
    // подхватываем тёмную тему родителя (iframe на том же домене)
    try { if (window.parent && window.parent.document.body.classList.contains('dark')) document.body.classList.add('dark'); } catch (e) {}
</script>

<h2 class="mt-0 mb-1">{{ __('locations.org_create_title') }}</h2>
<p class="f-14 mb-2">{{ __('locations.org_create_hint') }}</p>

@if(!$city)
    <div class="alert alert-danger">{{ __('locations.org_choose_city') }}</div>
@else
<form method="POST" action="{{ route('organizer.locations.store') }}" class="form" id="orgLocForm">
    @csrf
    <input type="hidden" name="embed" value="{{ $embed ? 1 : 0 }}">
    <input type="hidden" name="city_id" value="{{ $city->id }}">

    <div class="loc-org-row">
        <label>{{ __('locations.org_label_city') }}</label>
        <input type="text" value="{{ $city->name }}{{ $city->region_display ? ' (' . $city->region_display . ')' : '' }}" disabled>
    </div>

    <div class="loc-org-row">
        <label>{{ __('locations.org_label_name') }} *</label>
        <input type="text" name="name" value="{{ old('name') }}" maxlength="255" required>
        @error('name')<div class="loc-org-err">{{ $message }}</div>@enderror
    </div>

    <div class="loc-org-row">
        <label>{{ __('locations.org_label_address') }} *</label>
        <input type="text" name="address" value="{{ old('address') }}" maxlength="255" required>
        @error('address')<div class="loc-org-err">{{ $message }}</div>@enderror
    </div>

    <div class="loc-org-row loc-org-coords">
        <div>
            <label>{{ __('admin.loc_label_lat') }} (lat) *</label>
            <input type="number" name="lat" step="any" required value="{{ old('lat') }}">
            @error('lat')<div class="loc-org-err">{{ $message }}</div>@enderror
        </div>
        <div>
            <label>{{ __('admin.loc_label_lng') }} (lng) *</label>
            <input type="number" name="lng" step="any" required value="{{ old('lng') }}">
            @error('lng')<div class="loc-org-err">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="loc-org-row">
        @include('locations._map_picker', ['cityName' => $city->name, 'addressSel' => '[name="address"]'])
    </div>

    <button type="submit" class="btn">{{ __('locations.org_btn_save') }}</button>
</form>
@endif
</body>
</html>
