@props(['name'])
@php
    $paths = [
        'medical' => 'M9 3h6v6h6v6h-6v6H9v-6H3V9h6V3Z',
        'clinical' => 'M9 3h6m-5 0v7l-6 9a1 1 0 0 0 1 2h14a1 1 0 0 0 1-2l-6-9V3M7 16h10',
        'industrial' => 'M3 21V10l6 3V8l6 4V3h5v18H3Zm3-4h2m3 0h2m3 0h2',
        'school' => 'm2 9 10-5 10 5-10 5L2 9Zm4 2v6l6 3 6-3v-6m4-2v8',
        'catalog' => 'M4 3h12l4 4v14H4V3Zm12 0v5h4M8 12h8m-8 4h8',
    ];
@endphp
<svg {{ $attributes->class(['h-8 w-8']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $paths[$name] ?? $paths['catalog'] }}" /></svg>
