@props([
    'name',
    'size' => 20,
    'label' => null,
])
@php
    $paths = [
        'account' => '<path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/>',
        'alert' => '<path d="M10.3 2.9 1.8 17a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 2.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        'arrow-left' => '<path d="m15 18-6-6 6-6"/>',
        'arrow-right' => '<path d="m9 18 6-6-6-6"/>',
        'battery' => '<rect width="16" height="10" x="3" y="7" rx="2"/><path d="M21 11v2"/><path d="M7 11h4"/><path d="M9 9v4"/>',
        'calculator' => '<rect width="16" height="20" x="4" y="2" rx="2"/><path d="M8 6h8"/><path d="M8 10h.01M12 10h.01M16 10h.01M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01M16 18h.01"/>',
        'cart' => '<circle cx="9" cy="20" r="1"/><circle cx="19" cy="20" r="1"/><path d="M3 4h2l2.7 11.2a2 2 0 0 0 2 1.5h7.7a2 2 0 0 0 2-1.6L21 8H6"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>',
        'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'close' => '<path d="M18 6 6 18M6 6l12 12"/>',
        'color' => '<circle cx="13.5" cy="6.5" r=".5" fill="currentColor" stroke="none"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor" stroke="none"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor" stroke="none"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor" stroke="none"/><path d="M12 2a10 10 0 0 0 0 20c1.1 0 2-.9 2-2 0-.5-.2-.9-.5-1.3-.3-.4-.5-.8-.5-1.2a2 2 0 0 1 2-2h1.8A5.2 5.2 0 0 0 22 10.3C22 5.7 17.5 2 12 2Z"/>',
        'delete' => '<path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="m19 6-1 15H6L5 6"/><path d="M10 11v5M14 11v5"/>',
        'direction' => '<path d="m12 3 7 7-7 11-7-11 7-7Z"/><path d="M9 10h6M12 7v6"/>',
        'instagram' => '<rect width="18" height="18" x="3" y="3" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r=".5" fill="currentColor" stroke="none"/>',
        'location' => '<path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
        'lock' => '<rect width="16" height="12" x="4" y="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'minus' => '<path d="M5 12h14"/>',
        'orders' => '<path d="M6 2h12l2 4v16H4V6l2-4Z"/><path d="M4 6h16M9 10h6"/>',
        'phone' => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7l.4 3a2 2 0 0 1-.6 1.7L7.6 9.7a16 16 0 0 0 6.7 6.7l1.3-1.3a2 2 0 0 1 1.7-.6l3 .4a2 2 0 0 1 1.7 2Z"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'power' => '<path d="M13 2 4 14h7l-1 8 9-12h-7l1-8Z"/>',
        'privacy' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/>',
        'security' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/>',
        'store' => '<path d="M3 9h18l-2-5H5L3 9Z"/><path d="M5 9v11h14V9M9 20v-6h6v6"/><path d="M3 9a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0"/>',
        'whatsapp' => '<path d="M20.5 11.7a8.5 8.5 0 0 1-12.6 7.5L3 20.5l1.3-4.7A8.5 8.5 0 1 1 20.5 11.7Z"/><path d="M8.5 7.7c.3-.3.6-.2.8.1l1 2c.1.3 0 .6-.2.8l-.7.6c.7 1.6 1.8 2.7 3.4 3.4l.6-.7c.2-.2.5-.3.8-.2l2 1c.3.2.4.5.1.8-.7.8-1.8 1.2-2.8.9-3.7-1-6.3-3.6-7.3-7.3-.3-1 .1-2.1.9-2.8Z"/>',
    ];
    $path = $paths[$name] ?? $paths['alert'];
@endphp
<svg
    {{ $attributes->merge(['class' => 'storefront-icon']) }}
    width="{{ $size }}"
    height="{{ $size }}"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="2"
    stroke-linecap="round"
    stroke-linejoin="round"
    @if($label) role="img" aria-label="{{ $label }}" @else aria-hidden="true" @endif
>{!! $path !!}</svg>
