@php
    $paths = [
        'dashboard' => 'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z',
        'grid' => 'M4 4h16v16H4z M4 10h16 M10 4v16',
        'cart' => 'M3 3h2l3 12h10l3-9H6 M9 20h.01 M18 20h.01',
        'box' => 'M3 7l9-4 9 4v10l-9 4-9-4z M3 7l9 4 9-4 M12 11v10',
        'factory' => 'M3 21V9l6 3V7l6 3V3h4l2 18z M7 17h1 M12 17h1 M17 17h1',
        'pos' => 'M4 3h16v12H4z M8 21h8 M12 15v6 M7 7h10 M7 11h5',
        'people' =>
            'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M16 4a4 4 0 0 1 0 8 M22 21v-2a4 4 0 0 0-3-3.87 M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
        'chart' => 'M4 3v18h17 M8 16v-5 M13 16V7 M18 16v-9',
        'wallet' => 'M20 8V5H4a2 2 0 0 0 0 4h17v11H4a2 2 0 0 1-2-2V7 M21 12h-6v5h6 M17 14.5h.01',
        'check' => 'M9 3H5v18h14V3h-4 M9 2h6v4H9z M8 13l3 3 5-6',
        'tool' => 'M14 6a5 5 0 0 0-6 6L3 17a3 3 0 0 0 4 4l5-5a5 5 0 0 0 6-6l-3 3-4-4z',
        'alert' => 'M12 3L2 21h20z M12 9v5 M12 17h.01',
        'growth' => 'M3 17l6-6 4 4 8-10 M15 5h6v6',
        'report' => 'M5 3h10l4 4v14H5z M14 3v5h5 M8 12h8 M8 16h8',
        'settings' => 'M4 7h16 M4 17h16 M8 4v6 M16 14v6',
        'menu' => 'M4 6h16 M4 12h16 M4 18h16',
        'close' => 'M6 6l12 12 M18 6L6 18',
        'chevron' => 'M9 5l7 7-7 7',
        'bell' => 'M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9 M10 21h4',
        'logout' => 'M9 4H4v16h5 M12 12h9 M17 8l4 4-4 4',
        'info' => 'M12 11v6 M12 7h.01 M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0',
    ];
@endphp
<svg class="admin-icon {{ $iconClass ?? '' }}" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
    stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
    <path d="{{ $paths[$icon ?? 'grid'] ?? $paths['grid'] }}" />
</svg>
