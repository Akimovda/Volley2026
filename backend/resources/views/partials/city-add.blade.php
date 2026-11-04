{{-- «+ Добавить город»: виджет под полем города (.city-autocomplete). Только для Админ/Организатор/Staff. --}}
@php
	$__cityAddUser = auth()->user();
	$__cityAddCan  = \App\Http\Controllers\CityAddController::userCanAdd($__cityAddUser);
@endphp
@if($__cityAddCan)
	@php
		$__cityAddCountries = collect(\App\Support\CountryRules::SUPPORTED)
			->map(fn ($c) => ['code' => $c, 'name' => __('countries.' . $c)])
			->sortBy('name')->values();
	@endphp
	<script>
		window.CITY_ADD = {
			suggestUrl: @json(route('cities.suggest')),
			storeUrl: @json(route('cities.store')),
			defaultCountry: @json($__cityAddUser->country_code ?: 'RU'),
			countries: @json($__cityAddCountries),
			t: @json(__('city_add'))
		};
	</script>
	<script src="@asset_v('js/city-add.js')" defer></script>
@endif
