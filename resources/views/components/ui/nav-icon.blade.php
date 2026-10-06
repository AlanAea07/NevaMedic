@props(['name'])
@php
    $paths = [
        'admin.dashboard' => 'M6 3h13v18H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm0 0v18M9 8h7M9 12h7M9 16h4',
        'admin.products.index' => 'm12 3 9 5v8l-9 5-9-5V8l9-5Zm0 10 9-5M12 13 3 8m9 5v8M7.5 5.5l9 5',
        'admin.products.create' => 'M12 16V3m-5 5 5-5 5 5M4 14v6a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-6',
        'admin.sectors.index' => 'M3 3h7v7H3V3Zm11 0h7v7h-7V3ZM3 14h7v7H3v-7Zm11 0h7v7h-7v-7Z',
        'admin.statistics.index' => 'M4 3v18h17M8 17v-5m5 5V7m5 10V4',
        'admin.activity.index' => 'M3 12h4l3-8 4 16 3-8h4',
        'admin.accounts.index' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm10-7a4 4 0 0 1 0 8m3 9v-2a4 4 0 0 0-3-4',
        'profile.edit' => 'M20 21v-2a6 6 0 0 0-6-6h-4a6 6 0 0 0-6 6v2M12 10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z',
        'home' => 'M14 3h7v7m0-7L10 14M10 3H4a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1v-6',
        'logout' => 'M9 3H4a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h5m7-14 5 5-5 5M8 12h13',
    ];
@endphp
<svg {{ $attributes->class(['h-5 w-5 shrink-0']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="{{ $paths[$name] ?? $paths['admin.products.index'] }}" /></svg>
