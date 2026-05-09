{{-- resources/views/pages/user_agreement.blade.php --}}
<x-voll-layout body_class="politic">
<x-slot name="title">{{ __('pages.ua_title') }} — VolleyPlay.Club</x-slot>

<x-slot name="description">{{ __('pages.ua_description') }}</x-slot>

<x-slot name="canonical">{{ route('user_agreement') }}</x-slot>

<x-slot name="breadcrumbs">
<li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
<a href="{{ route('user_agreement') }}" itemprop="item"><span itemprop="name">{{ __('pages.ua_breadcrumb') }}</span></a>
<meta itemprop="position" content="2">
</li>
</x-slot>

<x-slot name="h1">{{ __('pages.ua_h1') }}</x-slot>

<div class="container">
<div class="ramka">
<p class="f-13" style="opacity:.7">{{ __('pages.ua_last_updated') }}</p>
<hr class="mb-2">

<h2>{{ __('pages.ua_general_h2') }}</h2>
<p>{!! __('pages.ua_general_p1') !!}</p>
<p>{!! __('pages.ua_general_p2') !!}</p>
<p>{!! __('pages.ua_general_p3') !!}</p>
<p>{!! __('pages.ua_general_p4') !!}</p>

<h2>{{ __('pages.ua_account_h2') }}</h2>
<p>{!! __('pages.ua_account_1') !!}</p>
<p>{!! __('pages.ua_account_2') !!}</p>
<p>{!! __('pages.ua_account_3') !!}</p>
<p>{!! __('pages.ua_account_4') !!}</p>

<h2>{{ __('pages.ua_usage_h2') }}</h2>
<p>{!! __('pages.ua_usage_1') !!}</p>
<p>{!! __('pages.ua_usage_2_intro') !!}</p>
<ul class="list">
<li>{{ __('pages.ua_usage_2_li1') }}</li>
<li>{{ __('pages.ua_usage_2_li2') }}</li>
<li>{{ __('pages.ua_usage_2_li3') }}</li>
<li>{{ __('pages.ua_usage_2_li4') }}</li>
</ul>
<p>{!! __('pages.ua_usage_3') !!}</p>

<h2>{{ __('pages.ua_restrictions_h2') }}</h2>
<p>{!! __('pages.ua_restrictions_intro') !!}</p>
<ul class="list">
<li>{{ __('pages.ua_restrictions_li1') }}</li>
<li>{{ __('pages.ua_restrictions_li2') }}</li>
<li>{{ __('pages.ua_restrictions_li3') }}</li>
<li>{{ __('pages.ua_restrictions_li4') }}</li>
<li>{{ __('pages.ua_restrictions_li5') }}</li>
<li>{{ __('pages.ua_restrictions_li6') }}</li>
</ul>

<h2>{{ __('pages.ua_ip_h2') }}</h2>
<p>{!! __('pages.ua_ip_1') !!}</p>
<p>{!! __('pages.ua_ip_2') !!}</p>
<p>{!! __('pages.ua_ip_3') !!}</p>

<h2>{{ __('pages.ua_paid_h2') }}</h2>
<p>{!! __('pages.ua_paid_5_1_t') !!}</p>
<ul class="list">
<li>{{ __('pages.ua_paid_5_1_li1') }}</li>
<li>{{ __('pages.ua_paid_5_1_li2') }}</li>
<li>{{ __('pages.ua_paid_5_1_li3') }}</li>
</ul>
<p>{!! __('pages.ua_paid_5_2_t') !!}</p>
<p>{!! __('pages.ua_paid_5_2_1') !!}</p>
<p>{!! __('pages.ua_paid_5_2_2') !!}</p>
<p>{!! __('pages.ua_paid_5_2_3') !!}</p>
<p>{!! __('pages.ua_paid_5_2_4') !!}</p>
<p>{!! __('pages.ua_paid_5_3_t') !!}</p>
<p>{!! __('pages.ua_paid_5_3_1') !!}</p>
<p>{!! __('pages.ua_paid_5_3_2') !!}</p>
<p>{!! __('pages.ua_paid_5_3_3') !!}</p>

<h2>{{ __('pages.ua_refund_h2') }}</h2>
<p>{!! __('pages.ua_refund_1') !!}</p>
<ul class="list">
<li>{{ __('pages.ua_refund_1_li1') }}</li>
<li>{{ __('pages.ua_refund_1_li2') }}</li>
<li>{{ __('pages.ua_refund_1_li3') }}</li>
</ul>
<p>{!! __('pages.ua_refund_2') !!}</p>
<p>{!! __('pages.ua_refund_3') !!}</p>
<p>{!! __('pages.ua_refund_4') !!}</p>

<h2>{{ __('pages.ua_conduct_h2') }}</h2>
<p>{!! __('pages.ua_conduct_1') !!}</p>
<p>{!! __('pages.ua_conduct_2') !!}</p>
<ul class="list">
<li>{{ __('pages.ua_conduct_2_li1') }}</li>
<li>{{ __('pages.ua_conduct_2_li2') }}</li>
<li>{{ __('pages.ua_conduct_2_li3') }}</li>
<li>{{ __('pages.ua_conduct_2_li4') }}</li>
<li>{{ __('pages.ua_conduct_2_li5') }}</li>
</ul>
<p>{!! __('pages.ua_conduct_3') !!}</p>

<h2>{{ __('pages.ua_liability_h2') }}</h2>
<p>{!! __('pages.ua_liability_1') !!}</p>
<p>{!! __('pages.ua_liability_2') !!}</p>
<p>{!! __('pages.ua_liability_3') !!}</p>
<p>{!! __('pages.ua_liability_4') !!}</p>

<h2>{{ __('pages.ua_changes_h2') }}</h2>
<p>{!! __('pages.ua_changes_1') !!}</p>
<p>{!! __('pages.ua_changes_2') !!}</p>
<p>{!! __('pages.ua_changes_3') !!}</p>

<h2>{{ __('pages.ua_termination_h2') }}</h2>
<p>{!! __('pages.ua_termination_1') !!}</p>
<p>{!! __('pages.ua_termination_2') !!}</p>
<p>{!! __('pages.ua_termination_3') !!}</p>

<h2>{{ __('pages.ua_law_h2') }}</h2>
<p>{!! __('pages.ua_law_1') !!}</p>
<p>{!! __('pages.ua_law_2') !!}</p>
<p>{!! __('pages.ua_law_3') !!}</p>

<h2>{{ __('pages.ua_privacy_h2') }}</h2>
<p>{!! __('pages.ua_privacy_1') !!}</p>
<p>{!! __('pages.ua_privacy_2') !!}</p>

<h2>{{ __('pages.ua_contacts_h2') }}</h2>
<p>{!! __('pages.ua_contacts_block') !!}</p>

<hr class="mt-2">
<p class="f-13 text-center" style="opacity:.7"><em>{{ __('pages.ua_footer') }}</em></p>
</div>
</div>
</x-voll-layout>
