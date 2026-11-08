{{-- Цветные SVG-иконки вместо эмодзи (одинаково выглядят на всех платформах).
     <x-ico name="check" /> · size — любое CSS-значение (по умолчанию 1.15em, под размер текста). --}}
@props(['name', 'size' => '1.15em'])
@php
$stroke = fn (string $inner, string $color, string $fill = 'none') =>
    '<svg viewBox="0 0 24 24" fill="'.$fill.'" stroke="'.$color.'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$inner.'</svg>';
$blue = '#2563eb'; $green = '#16a34a'; $red = '#dc2626'; $amber = '#d97706'; $violet = '#7c3aed';
$icons = [
    'users'     => $stroke('<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>', $blue),
    'user-plus' => $stroke('<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/>', $blue),
    'swap'      => $stroke('<path d="m17 2 4 4-4 4"/><path d="M3 11v-1a4 4 0 0 1 4-4h14"/><path d="m7 22-4-4 4-4"/><path d="M21 13v1a4 4 0 0 1-4 4H3"/>', $blue),
    'crown'     => $stroke('<path d="m2 8 5 4 5-8 5 8 5-4-2 11H4z"/>', $amber, '#fde68a'),
    'trash'     => $stroke('<path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/>', $red),
    'check'     => $stroke('<circle cx="12" cy="12" r="10"/><path d="m8 12 3 3 5-6"/>', $green),
    'x'         => $stroke('<circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/>', $red),
    'warn'      => $stroke('<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/>', $amber),
    'clock'     => $stroke('<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>', $amber),
    'mail'      => $stroke('<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>', $blue),
    'link'      => $stroke('<path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/>', $blue),
    'plus'      => $stroke('<circle cx="12" cy="12" r="10"/><path d="M12 8v8M8 12h8"/>', $green),
    'clipboard' => $stroke('<rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M9 12h6M9 16h6"/>', $violet),
    'card'      => $stroke('<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>', $violet),
    'star'      => $stroke('<path d="M12 2l3.09 6.26L22 9.27l-5 4.87L18.18 21 12 17.77 5.82 21 7 14.14l-5-4.87 6.91-1.01z"/>', $amber, '#fde68a'),
    'chart'     => $stroke('<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>', $blue),
    'door'      => $stroke('<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>', $red),
    'disband'   => $stroke('<circle cx="12" cy="12" r="10"/><path d="m4.9 4.9 14.2 14.2"/>', $red),
    'dot'       => $stroke('<circle cx="12" cy="12" r="6"/>', $amber, '#fde047'),
    'trophy'    => $stroke('<path d="M6 9H4a2 2 0 0 1-2-2V5h4"/><path d="M18 9h2a2 2 0 0 0 2-2V5h-4"/><path d="M6 3h12v6a6 6 0 0 1-12 0z"/><path d="M12 15v4"/><path d="M8 21h8"/>', $amber),
    'sliders'   => $stroke('<path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M20 18h0"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/>', $blue),
    'user-check'=> $stroke('<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="m16 11 2 2 4-4"/>', $green),
    'pin'       => $stroke('<path d="M12 22s7-7.58 7-13a7 7 0 0 0-14 0c0 5.42 7 13 7 13z"/><circle cx="12" cy="9" r="2.5"/>', $red),
    'ball'      => $stroke('<circle cx="12" cy="12" r="10"/><path d="M3.34 7Q10.25 15.74 21.4 15.42"/><path d="M20.66 7Q9.63 8.62 4.34 18.43"/><path d="M12 22Q16.11 11.65 10.26 2.15"/>', '#ea580c'),
    'clipboard-check' => $stroke('<rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="m9 14 2 2 4-4"/>', $violet),
    'wallet'    => $stroke('<rect x="2" y="6" width="20" height="14" rx="2"/><path d="M2 10h20"/><circle cx="17" cy="15" r="1"/>', $green),
    'bell'      => $stroke('<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>', $amber),
    'robot'     => $stroke('<rect x="4" y="8" width="16" height="12" rx="3"/><path d="M12 8V4M9 14h.01M15 14h.01"/><path d="M2 14h2M20 14h2"/>', '#0d9488'),
    'image'     => $stroke('<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21"/>', $blue),
    'file-text' => $stroke('<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8M8 17h8M8 9h2"/>', '#475569'),
    'repeat'    => $stroke('<path d="m17 2 4 4-4 4"/><path d="M3 11v-1a4 4 0 0 1 4-4h14"/><path d="m7 22-4-4 4-4"/><path d="M21 13v1a4 4 0 0 1-4 4H3"/>', $violet),
];
@endphp
@if(isset($icons[$name]))<span {{ $attributes->merge(['class' => 'ico']) }} style="display:inline-flex;width:{{ $size }};height:{{ $size }};vertical-align:-.22em;flex-shrink:0">{!! str_replace('<svg ', '<svg width="100%" height="100%" ', $icons[$name]) !!}</span>@endif
