@props(['photos', 'name', 'compact' => false, 'url' => null])
<div class="photo-viewer relative overflow-hidden bg-neva-mist {{ $compact ? 'h-72' : 'h-96 rounded-xl md:h-[32rem]' }}" role="group" aria-label="Fotografías de {{ $name }}" @keydown.left.prevent="step(-1)" @keydown.right.prevent="step(1)">
    @if($url)<a href="{{ $url }}" class="block h-full focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-4px] focus-visible:outline-neva-blue" aria-label="Ver detalles de {{ $name }}">@endif
    <template x-for="image in (current ? [current] : [])" :key="image.id">
        <img :src="image.src" :alt="image.alt || @js($name)" x-show="!failed" x-on:error="if (selected === image.id) failed = true" class="photo-frame h-full w-full {{ $compact ? 'object-cover' : 'object-contain' }}" {{ $compact ? 'loading=lazy' : '' }} referrerpolicy="no-referrer">
    </template>
    <div x-cloak x-show="!current || failed" class="absolute inset-0 flex items-center justify-center p-12 text-center text-sm text-neva-muted" x-text="failed ? 'Imagen no disponible. Prueba otra fotografía.' : 'No hay fotografías para esta selección.'"></div>
    <noscript><img src="{{ $photos->first()->url }}" alt="{{ $photos->first()->alt ?: $name }}" class="h-full w-full {{ $compact ? 'object-cover' : 'object-contain' }}" referrerpolicy="no-referrer"></noscript>
    @if($url)</a>@endif
    <template x-if="visibleImages.length > 1">
        <div>
            <button type="button" class="photo-arrow left-3" @click="step(-1)" aria-label="Fotografía anterior de {{ $name }}"><x-ui.nav-icon name="chevron-left" /></button>
            <button type="button" class="photo-arrow right-3" @click="step(1)" aria-label="Fotografía siguiente de {{ $name }}"><x-ui.nav-icon name="chevron-right" /></button>
            <p class="pointer-events-none absolute bottom-3 left-1/2 -translate-x-1/2 rounded-full bg-white/95 px-3 py-1 text-xs font-semibold text-neva-ink shadow-sm" role="status" aria-live="polite" aria-atomic="true"><span class="sr-only">Fotografía </span><span x-text="position"></span><span aria-hidden="true"> / </span><span class="sr-only"> de </span><span x-text="visibleImages.length"></span></p>
        </div>
    </template>
</div>
