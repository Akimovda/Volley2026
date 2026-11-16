{{-- «+ Добавить город»: виджет под полем города (.city-autocomplete). Только для Админ/Организатор/Staff. --}}
@php
	$__cityAddUser = auth()->user();
	$__cityAddCan  = \App\Http\Controllers\CityAddController::userCanAdd($__cityAddUser);
@endphp
@if($__cityAddCan)
	@php
		// все страны мира (таблица countries): поиск по названию на ru/en, коротким формам и синонимам (Китай, China, КНР)
		$__cityAddCountries = \App\Support\CountryRules::pickerList();
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
