{{-- resources/views/pages/level_players.blade.php --}}
<x-voll-layout body_class="level">
	<x-slot name="title">
		{{ __('pages.lp_title') }}
	</x-slot>

    <x-slot name="description">
		{{ __('pages.lp_description') }}
	</x-slot>

    <x-slot name="t_description">
		{!! __('pages.lp_t_description') !!}
	</x-slot>

    <x-slot name="canonical">
        {{ route('level_players') }}
	</x-slot>

    <x-slot name="breadcrumbs">
		<li itemprop="itemListElement" itemscope="" itemtype="http://schema.org/ListItem">
			<a href="{{ route('level_players') }}" itemprop="item"><span itemprop="name">{{ __('pages.lp_breadcrumb') }}</span></a>
			<meta itemprop="position" content="2">
		</li>
	</x-slot>
    <x-slot name="h1">
        {{ __('pages.lp_title') }}
	</x-slot>

    <div class="container">
        <div class="ramka">
			<div class="level-section">
				<div class="tabs-content">
					<div class="tabs">
						<h2 class="tab active" data-tab="classic">{{ __('pages.lp_tab_classic') }}</h2>
						<h2 class="tab" data-tab="beach">{{ __('pages.lp_tab_beach') }}</h2>
						<div class="tab-highlight"></div>
					</div>

					<div class="tab-panes">

						<div class="tab-pane active" id="classic">
							<div class="tabs-content">
								<div class="tabs mb-3">
									<h3 class="tab active" data-tab="classic-child">{{ __('pages.lp_tab_child') }}</h3>
									<h3 class="tab" data-tab="classic-old">{{ __('pages.lp_tab_old') }}</h3>
									<div class="tab-highlight"></div>
								</div>

								<div class="tab-panes">
									<div class="tab-pane active" id="classic-child">
										<div class="row">
											<div class="col-xl-4">
												<div class="card level-card">
													<h4>{{ __('pages.lp_child_h_start') }} <span class="level-points">{!! __('pages.lp_child_points') !!}</span></h4>
													<p>{{ __('pages.lp_child_classic_start_p') }}</p>
												</div>
											</div>
											<div class="col-xl-4">
												<div class="card level-card">
													<h4>{{ __('pages.lp_child_h_start_pl') }} <span class="level-points">{!! __('pages.lp_child_points') !!}</span></h4>
													<p>{{ __('pages.lp_child_classic_start_pl_p') }}</p>
												</div>
											</div>
											<div class="col-xl-4">
												<div class="card level-card">
													<h4>{{ __('pages.lp_child_h_mid') }} <span class="level-points">{!! __('pages.lp_child_points') !!}</span></h4>
													<p>{{ __('pages.lp_child_classic_mid_p') }}</p>
												</div>
											</div>
										</div>
									</div>
									<div class="tab-pane" id="classic-old">
										<div class="table-scrollable flex-table">
											<div class="table-drag-indicator"></div>
											<table class="table table-levels">
												<thead>
													<tr>
														<th style="min-width:26rem">{{ __('pages.lp_col_level') }}</th>
														<th>{{ __('pages.lp_col_desc') }}</th>
													</tr>
												</thead>
												<tbody>
													<tr>
														<td><div class="text-center"><strong class="levelmark level-1">{{ __('pages.lp_lvl_1') }}</strong></div></td>
														<td>{{ __('pages.lp_adult_classic_1') }}</td>
													</tr>
													<tr>
														<td><div class="text-center"><strong class="levelmark level-2">{{ __('pages.lp_lvl_2') }}</strong></div></td>
														<td>{{ __('pages.lp_adult_classic_2') }}</td>
													</tr>
													<tr>
														<td><div class="text-center"><strong class="levelmark level-3">{{ __('pages.lp_lvl_3') }}</strong></div></td>
														<td>{{ __('pages.lp_adult_classic_3') }}</td>
													</tr>
													<tr>
														<td><div class="text-center"><strong class="levelmark level-4">{{ __('pages.lp_lvl_4') }}</strong></div></td>
														<td>{{ __('pages.lp_adult_classic_4') }}</td>
													</tr>
													<tr>
														<td><div class="text-center"><strong class="levelmark level-5">{{ __('pages.lp_lvl_5') }}</strong></div></td>
														<td>{{ __('pages.lp_adult_classic_5') }}</td>
													</tr>
													<tr class="level-pro">
														<td><div class="text-center"><strong class="levelmark level-6">{{ __('pages.lp_lvl_6') }}</strong></div></td>
														<td>{{ __('pages.lp_adult_classic_6') }}</td>
													</tr>
													<tr class="level-pro">
														<td><div class="text-center"><strong class="levelmark level-7">{{ __('pages.lp_lvl_7') }}</strong></div></td>
														<td>{{ __('pages.lp_adult_classic_7') }}</td>
													</tr>
													<tr class="level-banned">
														<td>
															<div class="text-center">
																<strong class="text-center">{!! __('pages.lp_lvl_god') !!}</strong>
																<strong class="text-center">{!! __('pages.lp_lvl_ban') !!}</strong>
															</div>
														</td>
														<td>{{ __('pages.lp_adult_classic_god') }}</td>
													</tr>
												</tbody>
											</table>
										</div>
									</div>
								</div>
							</div>
						</div>

						<div class="tab-pane" id="beach">
							<div class="tabs-content">
								<div class="tabs mb-3">
									<h3 class="tab" data-tab="beach-child">{{ __('pages.lp_tab_child') }}</h3>
									<h3 class="tab" data-tab="beach-old">{{ __('pages.lp_tab_old') }}</h3>
									<div class="tab-highlight"></div>
								</div>

								<div class="tab-panes">
									<div class="tab-pane" id="beach-child">
										<div class="row">
											<div class="col-xl-4">
												<div class="card level-card">
													<h4>{{ __('pages.lp_child_h_start') }} <span class="level-points">{!! __('pages.lp_child_points') !!}</span></h4>
													<p>{{ __('pages.lp_child_beach_start_p') }}</p>
												</div>
											</div>
											<div class="col-xl-4">
												<div class="card level-card">
													<h4>{{ __('pages.lp_child_h_start_pl') }} <span class="level-points">{!! __('pages.lp_child_points') !!}</span></h4>
													<p>{{ __('pages.lp_child_beach_start_pl_p') }}</p>
												</div>
											</div>
											<div class="col-xl-4">
												<div class="card level-card">
													<h4>{{ __('pages.lp_child_h_mid') }} <span class="level-points">{!! __('pages.lp_child_points') !!}</span></h4>
													<p>{{ __('pages.lp_child_beach_mid_p') }}</p>
												</div>
											</div>
										</div>
									</div>
									<div class="tab-pane" id="beach-old">
										<div class="table-scrollable flex-table">
											<div class="table-drag-indicator"></div>
											<table class="table table-levels">
												<thead>
													<tr>
														<th style="min-width:26rem">{{ __('pages.lp_col_level') }}</th>
														<th>{{ __('pages.lp_col_desc') }}</th>
													</tr>
												</thead>
												<tbody>
													<tr>
														<td><div class="text-center"><strong class="levelmark level-1">{{ __('pages.lp_lvl_1') }}</strong></div></td>
														<td>{{ __('pages.lp_adult_beach_1') }}</td>
													</tr>
													<tr>
														<td><div class="text-center"><strong class="levelmark level-2">{{ __('pages.lp_lvl_2') }}</strong></div></td>
														<td>{{ __('pages.lp_adult_beach_2') }}</td>
													</tr>
													<tr>
														<td><div class="text-center"><strong class="levelmark level-3">{{ __('pages.lp_lvl_3') }}</strong></div></td>
														<td>{{ __('pages.lp_adult_beach_3') }}</td>
													</tr>
													<tr>
														<td><div class="text-center"><strong class="levelmark level-4">{{ __('pages.lp_lvl_4') }}</strong></div></td>
														<td>{{ __('pages.lp_adult_beach_4') }}</td>
													</tr>
													<tr>
														<td><div class="text-center"><strong class="levelmark level-5">{{ __('pages.lp_lvl_5') }}</strong></div></td>
														<td>{{ __('pages.lp_adult_beach_5') }}</td>
													</tr>
													<tr class="level-pro">
														<td><div class="text-center"><strong class="levelmark level-6">{{ __('pages.lp_lvl_6') }}</strong></div></td>
														<td>{{ __('pages.lp_adult_beach_6') }}</td>
													</tr>
													<tr class="level-pro">
														<td><div class="text-center"><strong class="levelmark level-7">{{ __('pages.lp_lvl_7') }}</strong></div></td>
														<td>{{ __('pages.lp_adult_beach_7') }}</td>
													</tr>
												</tbody>
											</table>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>


				<h2>{{ __('pages.lp_calc_h2') }}</h2>

				<p>{!! __('pages.lp_calc_intro') !!}</p>

				<div class="formula-box">
					<h5 class="mt-0">{{ __('pages.lp_formula1_h') }}</h5>
					<p>{{ __('pages.lp_formula1_p') }}</p>
					<p><small>{{ __('pages.lp_formula1_eg') }}</small></p>
					<pre>((4*14) + (5*3) + 6) / 18 = 4.28</pre>
				</div>

				<div class="formula-box">
					<h5 class="mt-0">{{ __('pages.lp_formula2_h') }}</h5>
					<p>{{ __('pages.lp_formula2_p') }}</p>
					<p><small>{{ __('pages.lp_formula2_eg') }}</small></p>
					<pre>((3*10) + (4*5) + ((4*3) - 3)) / 18 = 3.28</pre>
					<p><small>{{ __('pages.lp_formula2_note') }}</small></p>
				</div>

				<p class="mt-1 text-right">{{ __('pages.lp_signoff') }} <strong class="c3">Your Volley Club!</strong></p>
			</div>
		</div>
	</div>
</x-voll-layout>
