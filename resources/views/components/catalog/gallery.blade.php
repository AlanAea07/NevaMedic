@props(['product'])
@php
    $colors = $product->has_variants ? $product->variants->where('is_available', true) : collect();
    $photos = $product->images->filter(fn ($image) => !$image->product_variant_id || $colors->contains('id', $image->product_variant_id));
    $gallery = $photos->map(fn ($image) => ['id' => $image->id, 'src' => $image->url, 'alt' => $image->alt, 'color' => $image->product_variant_id])->values()->all();
    $options = ['colors' => $colors->map(fn ($variant) => ['id' => $variant->id, 'name' => $variant->color])->values()->all(), 'quoteUrl' => route('contact', ['product' => $product->slug])];
@endphp
<div x-data="productGallery({{ Illuminate\Support\Js::from($gallery) }}, {{ Illuminate\Support\Js::from($options) }})" class="mt-8 grid items-start gap-8 lg:grid-cols-2 lg:gap-14">
    <div class="min-w-0 space-y-4">
    @if($photos->isNotEmpty())
        <img src="{{ $photos->first()->url }}" :src="current?.src || ''" :alt="current?.alt || 'Fotografía del producto'" x-show="current && !failed" x-on:error="failed = true" @load="failed = false" class="h-96 w-full rounded-xl bg-neva-mist object-contain md:h-[32rem]" referrerpolicy="no-referrer">
        <p x-cloak x-show="failed || !current" class="rounded bg-neva-mist p-10 text-neva-muted">Imagen no disponible para esta selección.</p>
        <div class="flex flex-wrap gap-3" aria-label="Fotografías del producto"><template x-for="image in visibleImages" :key="image.id"><button type="button" class="overflow-hidden rounded border-2" :class="selected === image.id ? 'border-neva-blue' : 'border-transparent'" @click="selectImage(image.id)" :aria-label="'Ver fotografía: ' + image.alt" :aria-pressed="selected === image.id"><img :src="image.src" :alt="image.alt" class="h-20 w-20 object-cover" referrerpolicy="no-referrer"></button></template></div>
    @else<x-ui.empty-state title="Fotografías pendientes" message="Consulta con nuestro equipo las opciones de esta prenda." />@endif
    </div>
    <div class="min-w-0 space-y-6">{{ $slot }}</div>
</div>
