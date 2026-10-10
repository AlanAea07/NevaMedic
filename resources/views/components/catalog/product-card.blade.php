@props(['product'])
@php
    $photos = $product->catalog_images;
    $gallery = $photos->map(fn ($image) => ['id' => $image->id, 'src' => $image->url, 'alt' => $image->alt, 'color' => $image->product_variant_id])->values()->all();
@endphp
<article class="product-card flex h-full min-w-0 flex-col overflow-hidden rounded border border-neva-line bg-white" x-data="productGallery({{ Illuminate\Support\Js::from($gallery) }}, { browseAll: true })">
        @if($photos->isNotEmpty())
            <x-catalog.photo-viewer :photos="$photos" :name="$product->name" :compact="true" :url="route('products.show', $product->slug)" />
        @else
            <div class="flex h-72 items-center justify-center bg-neva-mist text-neva-muted">Fotografía pendiente</div>
        @endif
    <a href="{{ route('products.show', $product->slug) }}" class="flex min-w-0 flex-1 flex-col focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-4px] focus-visible:outline-neva-blue">
        <div class="flex flex-1 flex-col items-start gap-3 p-6">
            <h2 class="break-words font-display text-3xl leading-tight">{{ $product->name }}</h2>
            <div>
                <p class="text-xl font-semibold">${{ number_format((float) $product->reference_price, 2) }} <span class="text-sm">{{ $product->currency }}</span></p>
                <p class="text-xs text-neva-muted">Precio de referencia</p>
            </div>
            <p class="text-sm font-semibold {{ $product->is_available ? 'text-green-700' : 'text-neva-muted' }}">{{ $product->is_available ? 'Disponible' : 'No disponible' }}</p>
            <p class="text-xs text-neva-muted">{{ $product->sectors->where('is_active', true)->pluck('name')->join(' · ') }}</p>
            <p class="line-clamp-2 text-sm text-neva-muted">{{ $product->short_description }}</p>
            <span class="mt-auto flex min-h-12 w-full items-center justify-between border-t border-neva-line pt-3 text-sm font-semibold text-neva-blue">Ver detalles <span aria-hidden="true">→</span></span>
        </div>
    </a>
</article>
