{{-- Цветные SVG-иконки вместо эмодзи (одинаково выглядят на всех платформах).
     <x-ico name="check" /> · size — любое CSS-значение (по умолчанию 1.15em, под размер текста). --}}
@props(['name', 'size' => '1.15em', 'color' => null])
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
    'pointer'   => $stroke('<path d="m3 3 7.07 16.97 2.51-7.39 7.39-2.51z"/>', '#2563eb', '#bfdbfe'),
    'phone'     => $stroke('<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>', '#16a34a', '#bbf7d0'),
    'telegram'  => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="12" fill="#229ED9"/><path fill="#fff" d="M5.4 11.8l11.2-4.3c.5-.2 1 .1.8.9l-1.9 9c-.1.6-.5.8-1 .5l-2.9-2.1-1.4 1.4c-.2.2-.3.3-.6.3l.2-3 5.4-4.9c.2-.2 0-.3-.4-.1l-6.7 4.2-2.9-.9c-.6-.2-.6-.6.1-.9z"/></svg>',
    'vk'        => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect width="24" height="24" rx="6" fill="#0077FF"/><text x="12" y="16.2" text-anchor="middle" font-family="Arial,Helvetica,sans-serif" font-weight="700" font-size="11.5" fill="#fff">VK</text></svg>',
    'ball'      => '<svg viewBox="0 0 24 24" fill="#ea580c" aria-hidden="true"><path fill-rule="evenodd" d="M11.5 1L12.25 1L13.55 1.1L14.6 1.3L15.75 1.65L16.9 2.15L17.75 2.65L19.3 3.75L20.15 4.6L20.7 5.3L21.35 6.25L21.9 7.2L22.3 8.15L22.65 9.2L22.9 10.4L23 11.35L23 12.5L22.9 13.55L22.7 14.6L22.45 15.4L22.1 16.35L21.7 17.2L21.1 18.2L20.5 19L19.7 19.85L19 20.5L18 21.2L17.2 21.7L16.25 22.15L15.45 22.45L14.15 22.8L13.1 22.95L12 23L10.9 22.95L9.85 22.8L8.7 22.5L7.65 22.1L6.8 21.7L5.8 21.1L5 20.5L4.15 19.7L3.45 18.95L2.7 17.9L2.2 17L1.7 15.85L1.35 14.8L1.1 13.6L1 12.65L1 11.45L1.1 10.4L1.3 9.4L1.65 8.3L2.05 7.3L2.5 6.45L3.15 5.5L3.8 4.65L4.65 3.8L5.45 3.15L6.25 2.65L7.2 2.1L8.25 1.65L9.4 1.3L10.45 1.1ZM12.35 12.8L13.35 12.85L14.6 13.1L15.85 13.55L17 14.15L16.2 14.95L15.25 15.7L14.35 16.4L13.3 17.05L11.55 17.95L9.6 18.6L7.6 19.05L5.9 19.2L5 18.35L4.2 17.4L4.25 17.35L5.55 17.15L6.5 16.9L7.75 16.45L8.75 15.95L9.75 15.35L10.7 14.6L11.75 13.55ZM18.25 15.05L18.35 15.1L19.35 16.1L19.9 16.8L20 17.05L19.3 18L18.35 19L17.55 19.65L16.5 20.35L15.4 20.85L14.35 21.2L13.35 21.35L12 21.45L11.05 21.45L10.05 21.25L8.85 20.95L8 20.55L9.3 20.3L10.7 19.9L12.2 19.3L13.45 18.7L14.85 17.9L16 17.1L17.2 16.1ZM11.35 7.15L12.25 7.2L13.65 7.4L15 7.75L16.2 8.15L17.45 8.7L18.4 9.25L19.55 10.05L20.35 10.75L21.45 11.85L21.4 12.95L21.3 13.7L21.15 14.55L20.8 15.45L20 14.5L19 13.65L18 12.95L16.9 12.3L15.9 11.9L14.65 11.5L13.55 11.3L12.45 11.25L12 10.35L11.6 9.2L11.4 8.25ZM10.6 2.65L10.8 2.65L10.25 4.1L9.9 5.7L9.8 6.85L9.85 7.9L10 9.05L10.25 9.95L10.65 11.05L11.05 11.95L10.55 12.55L9.7 13.4L9 14L8.05 14.6L7.75 13.8L7.4 12.6L7.15 11.5L7 10.45L6.95 9.45L6.95 8.1L7.05 7.05L7.25 5.95L7.55 4.75L8 3.45L9.45 2.85ZM12.5 2.6L12.8 2.55L13.7 2.7L15.15 3.05L16.5 3.65L17.75 4.5L18.9 5.55L19.7 6.45L20.55 7.9L21.05 9.2L21 9.25L19.8 8.35L18.6 7.6L17.55 7.05L16.25 6.5L15.1 6.15L13.85 5.85L12.45 5.65L11.5 5.6L11.75 4.4ZM5.85 4.8L5.95 4.8L5.65 6.25L5.45 7.9L5.45 10.05L5.55 11.4L5.8 12.65L6.15 13.95L6.65 15.2L6.15 15.4L5.1 15.65L3.4 15.85L3.15 15.4L2.85 14.55L2.6 13L2.55 11.6L2.75 10.05L3.15 8.6L3.85 7.15L4.7 6Z"/></svg>',
    'clipboard-check' => $stroke('<rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="m9 14 2 2 4-4"/>', $violet),
    'wallet'    => $stroke('<rect x="2" y="6" width="20" height="14" rx="2"/><path d="M2 10h20"/><circle cx="17" cy="15" r="1"/>', $green),
    'bell'      => $stroke('<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>', $amber),
    'robot'     => $stroke('<rect x="4" y="8" width="16" height="12" rx="3"/><path d="M12 8V4M9 14h.01M15 14h.01"/><path d="M2 14h2M20 14h2"/>', '#0d9488'),
    'image'     => $stroke('<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21"/>', $blue),
    'file-text' => $stroke('<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8M8 17h8M8 9h2"/>', '#475569'),
    'repeat'    => $stroke('<path d="m17 2 4 4-4 4"/><path d="M3 11v-1a4 4 0 0 1 4-4h14"/><path d="m7 22-4-4 4-4"/><path d="M21 13v1a4 4 0 0 1-4 4H3"/>', $violet),
    'globe'     => $stroke('<circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>', $blue),
    'download'  => $stroke('<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/>', $blue),
    'archive'   => $stroke('<rect x="2" y="3" width="20" height="5" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8"/><path d="M10 12h4"/>', '#64748b'),
    'lock'      => $stroke('<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>', $red),
    'layers'    => $stroke('<path d="m12 2 10 5-10 5L2 7z"/><path d="m2 17 10 5 10-5"/><path d="m2 12 10 5 10-5"/>', $violet),
    'eye'       => $stroke('<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>', $blue),
    'building'  => $stroke('<path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6"/><path d="M9 10h.01M15 10h.01"/>', '#0d9488'),
    'sun'       => $stroke('<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>', $amber),
    'pencil'    => $stroke('<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/>', $blue),
    'trend'     => $stroke('<path d="m22 7-8.5 8.5-5-5L2 17"/><path d="M16 7h6v6"/>', $green),
    'ticket'    => $stroke('<path d="M3 9a2 2 0 0 0 0 6v3a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1v-3a2 2 0 0 1 0-6V6a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1z"/><path d="M13 5v14" stroke-dasharray="2 2"/>', $violet),
    'tag'       => $stroke('<path d="M20.59 13.41 13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><circle cx="7" cy="7" r="1.2"/>', '#ea580c'),
    'user'      => $stroke('<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>', $blue),
    'smartphone'=> $stroke('<rect x="6" y="2" width="12" height="20" rx="2"/><path d="M11 18h2"/>', '#6366f1'),
    'activity'  => $stroke('<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>', $red),
    'megaphone' => $stroke('<path d="M3 11v3a1 1 0 0 0 1 1h2l5 4V6L6 10H4a1 1 0 0 0-1 1z"/><path d="M15.5 9a4 4 0 0 1 0 6"/><path d="M18.5 6.5a8 8 0 0 1 0 11"/>', $amber),
    'shield'    => $stroke('<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>', '#0d9488'),
    'shield-check' => $stroke('<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>', $green),
    'eye-off'   => $stroke('<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-10-8-10-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 10 8 10 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="m2 2 20 20"/>', '#0d9488'),
    'medal'     => $stroke('<circle cx="12" cy="15" r="6"/><path d="m8.5 9.5-2.5-7h4l2 4M15.5 9.5l2.5-7h-4l-2 4"/>', $amber),
    'zap'       => $stroke('<path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/>', $amber, '#fde68a'),
    'heart'     => $stroke('<path d="M19 14c1.5-1.5 3-3.2 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4 3 5.5l7 7z"/>', $red, '#fecaca'),
    'ghost'     => $stroke('<path d="M9 10h.01M15 10h.01"/><path d="M12 2a8 8 0 0 0-8 8v12l3-3 2.5 2.5L12 19l2.5 2.5L17 19l3 3V10a8 8 0 0 0-8-8z"/>', $violet),
    'flame'     => $stroke('<path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.07-2.14-.22-4.05 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.15.43-2.29 1-3a2.5 2.5 0 0 0 2.5 2.5z"/>', '#ea580c', '#fed7aa'),
    'calendar'  => $stroke('<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>', $blue),
    'calendar-check' => $stroke('<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="m9 16 2 2 4-4"/>', $green),
    'calendar-plus'  => $stroke('<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="M12 14v4M10 16h4"/>', $green),
    'id-card'   => $stroke('<rect x="2" y="5" width="20" height="14" rx="2"/><circle cx="8" cy="12" r="2"/><path d="M13 10h5M13 14h4"/>', $violet),
    'video'     => $stroke('<rect x="2" y="6" width="14" height="12" rx="2"/><path d="m22 8-6 4 6 4z"/>', $red),
    'send'      => $stroke('<path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/>', $blue),
    'help'      => $stroke('<circle cx="12" cy="12" r="10"/><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3"/><path d="M12 17h.01"/>', $blue),
    'info'      => $stroke('<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>', $blue),
    'search'    => $stroke('<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>', $blue),
    'message'   => $stroke('<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>', $violet),
    'bulb'      => $stroke('<path d="M9 18h6M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.2 1 2V18h6v-1.3c0-.8.4-1.5 1-2A7 7 0 0 0 12 2z"/>', $amber),
    'key'       => $stroke('<circle cx="7.5" cy="15.5" r="4.5"/><path d="m10.7 12.3 9.8-9.8M16 7l3 3M14 9l2 2"/>', $amber),
    'refresh'   => $stroke('<path d="M21 12a9 9 0 0 0-15.5-6.2L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 15.5 6.2L21 16"/><path d="M21 21v-5h-5"/>', $blue),
    'arrow-right' => $stroke('<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>', $blue),
    'bluetooth' => $stroke('<path d="m7 7 10 10-5 5V2l5 5L7 17"/>', $blue),
    'dot-green' => $stroke('<circle cx="12" cy="12" r="7"/>', '#16a34a', '#4ade80'),
    'dot-red'   => $stroke('<circle cx="12" cy="12" r="7"/>', '#dc2626', '#f87171'),
    'dot-gray'  => $stroke('<circle cx="12" cy="12" r="7"/>', '#6b7280', '#d1d5db'),
];
if ($color && isset($icons[$name])) {
    $icons[$name] = preg_replace('/stroke="#[0-9a-fA-F]{3,8}"/', 'stroke="' . e($color) . '"', $icons[$name], 1);
    $icons[$name] = preg_replace('/fill="#[0-9a-fA-F]{3,8}"/', 'fill="' . e($color) . '"', $icons[$name], 1);
}
@endphp
@php
// Многоцветные иконки-иллюстрации (отдельные SVG-файлы, не inline): <x-ico name="beach" /> — пляжный зонт с мячом
$files = ['beach' => '/assets/img/beach-umbrella.svg'];
@endphp
@if(isset($files[$name]))<span {{ $attributes->merge(['class' => 'ico']) }} style="display:inline-flex;width:{{ $size }};height:{{ $size }};vertical-align:-.28em;flex-shrink:0"><img src="{{ asset(ltrim($files[$name], '/')) }}" alt="" aria-hidden="true" width="100%" height="100%" style="display:block;width:100%;height:100%;object-fit:contain"></span>
@elseif(isset($icons[$name]))<span {{ $attributes->merge(['class' => 'ico']) }} style="display:inline-flex;width:{{ $size }};height:{{ $size }};vertical-align:-.22em;flex-shrink:0">{!! str_replace('<svg ', '<svg width="100%" height="100%" ', $icons[$name]) !!}</span>@endif
