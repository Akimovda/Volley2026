@php $brand = app(\App\Models\Brand::class); @endphp
@if($brand->logo_day_url)
<img src="{{ $brand->logo_day_url }}" alt="{{ $brand->display_name }}" class="brand-logo-img brand-logo-day">
@endif
@if($brand->logo_night_url)
<img src="{{ $brand->logo_night_url }}" alt="{{ $brand->display_name }}" class="brand-logo-img brand-logo-night">
@endif
