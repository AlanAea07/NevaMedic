@props(['product'])
<article class="overflow-hidden rounded border border-neva-line bg-white">
    <a href="{{ route('products.show', $product->slug) }}" class="block">
        @if($image = $product->cover_image)
            <div x-data="{ failed: false }"><img x-show="!failed" x-on:error="failed = true" class="h-72 w-full object-cover" src="{{ $image->url }}" alt="{{ $image->alt }}" loading="lazy" referrerpolicy="no-referrer"><div x-cloak x-show="failed" class="flex h-72 items-center justify-center bg-neva-mist text-neva-muted">Imagen no disponible</div></div>
        @else
            <div class="flex h-72 items-center justify-center bg-neva-mist text-neva-muted">Fotografía pendiente</div>
        @endif
        <div class="space-y-3 p-6">
            <p class="text-xs font-semibold uppercase tracking-widest text-neva-blue">{{ $product->sectors->where('is_active', true)->pluck('name')->join(' · ') }}</p>
            <h2 class="font-display text-3xl">{{ $product->name }}</h2>
            <p class="text-sm text-neva-muted">{{ $product->short_description }}</p>
            <p class="font-semibold">${{ number_format((float) $product->reference_price, 2) }} {{ $product->currency }}</p>
            <p class="text-sm">{{ $product->is_available ? 'Disponible' : 'No disponible' }} · Precio de referencia</p>
            <span class="inline-block text-sm font-semibold text-neva-blue">Ver producto →</span>
        </div>
    </a>
</article>
