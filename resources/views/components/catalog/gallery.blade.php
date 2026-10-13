@props(['product'])
@php
    $colors = $product->has_variants ? $product->variants->where('is_available', true) : collect();
    $photos = $product->catalog_images;
    $gallery = $photos->map(fn ($image) => ['id' => $image->id, 'src' => $image->url, 'alt' => $image->alt, 'color' => $image->product_variant_id])->values()->all();
    $options = ['colors' => $colors->map(fn ($variant) => ['id' => $variant->id, 'name' => $variant->color])->values()->all(), 'quoteUrl' => route('contact', ['product' => $product->slug, 'direct' => 1]), 'customizationUrl' => route('customization', ['product' => $product->slug])];
@endphp
<div x-data="productGallery({{ Illuminate\Support\Js::from($gallery) }}, {{ Illuminate\Support\Js::from($options) }})">
<div class="mt-8 grid items-start gap-8 lg:grid-cols-2 lg:gap-14">
    <div class="min-w-0 space-y-4">
    @if($photos->isNotEmpty())
        <x-catalog.photo-viewer :photos="$photos" :name="$product->name" />
        @if($photos->contains(fn ($image) => $image->product_variant_id === null) && $colors->isNotEmpty())
            <button x-cloak type="button" class="btn-secondary gap-2" @click="selectColor(null)" :aria-pressed="color === null"><x-ui.nav-icon name="photos" />Vista general</button>
        @endif
        <div x-cloak class="flex gap-3 overflow-x-auto pb-2" aria-label="Fotografías del producto"><template x-for="(image, index) in visibleImages" :key="image.id"><button type="button" class="shrink-0 overflow-hidden rounded border-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-neva-blue" :class="selected === image.id ? 'border-neva-blue' : 'border-transparent'" @click="selectImage(image.id)" :aria-label="'Ver fotografía ' + (index + 1) + (colorName ? ' · ' + colorName : '')" :aria-pressed="selected === image.id"><img :src="image.src" :alt="image.alt" class="h-20 w-20 object-cover" loading="lazy" referrerpolicy="no-referrer"></button></template></div>
    @else<x-ui.empty-state title="Fotografías pendientes" message="Consulta con nuestro equipo las opciones de esta prenda." />@endif
    </div>
    <div class="min-w-0 space-y-6">{{ $slot }}</div>
</div>
{{ $details ?? '' }}
</div>
