<header class="relative z-40 border-b border-neva-line bg-white" x-data="{ open: false, productsOpen: false }" @keydown.escape.stop="if (productsOpen) { productsOpen = false; $refs.productsTrigger.focus() } else { open = false }">
    <nav class="page-shell relative flex min-h-20 flex-wrap items-center justify-between gap-4 py-4" aria-label="Principal">
        <a href="{{ route('home') }}" class="flex items-center gap-3 font-display text-2xl"><img src="{{ asset('images/brand/emblem.png') }}" class="h-10 w-10 object-contain" alt="">NEVA MEDIC</a>
        <button type="button" class="btn-secondary lg:hidden" @click="open = !open; if (!open) productsOpen = false" :aria-expanded="open" aria-controls="site-menu">Menú</button>
        <div id="site-menu" class="hidden w-full flex-col gap-5 lg:flex lg:w-auto lg:flex-row lg:items-center" :class="{ '!flex': open }">
            <div @click.outside="productsOpen = false" @focusout="if (!$el.contains($event.relatedTarget)) productsOpen = false">
                <button type="button" x-ref="productsTrigger" class="flex min-h-11 w-full items-center justify-between gap-2 rounded text-left hover:text-neva-blue focus-visible:outline-neva-blue" @click="productsOpen = !productsOpen" :aria-expanded="productsOpen" aria-controls="products-menu">
                    Productos
                    <svg class="h-4 w-4 transition-transform" :class="{ 'rotate-180': productsOpen }" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="m5 7.5 5 5 5-5"/></svg>
                </button>
                <div id="products-menu" x-cloak x-show="productsOpen" class="mt-3 max-h-[70vh] overflow-y-auto rounded-xl border border-neva-line bg-white shadow-xl lg:absolute lg:inset-x-6 lg:top-full lg:mt-0">
                    <div class="grid lg:grid-cols-4">
                        <div class="bg-neva-navy p-6 text-white lg:p-8">
                            <p class="text-xs font-semibold uppercase tracking-widest text-neva-silver">Catálogo digital</p>
                            <p class="mt-4 font-display text-3xl">Encuentra la prenda indicada para cada entorno.</p>
                            <a href="{{ route('products.index') }}" class="mt-6 inline-flex min-h-11 items-center gap-3 underline underline-offset-4">Productos en general <span aria-hidden="true">→</span></a>
                        </div>
                        @foreach($navigationSectors as $sector)
                            <div class="border-t border-neva-line p-6 lg:border-l lg:border-t-0 lg:p-8">
                                <a href="{{ route('products.index', ['sector' => $sector->slug]) }}" class="inline-flex min-h-11 items-center text-lg font-semibold text-neva-navy hover:underline">{{ $sector->name }}</a>
                                <ul class="mt-2 space-y-1">
                                    @foreach(config('neva.sector_types.'.$sector->slug, []) as $type)
                                        <li><a href="{{ route('products.index', ['sector' => $sector->slug, 'type' => $type]) }}" class="block rounded px-2 py-3 text-sm text-neva-muted transition hover:bg-neva-mist hover:text-neva-blue focus-visible:outline-neva-blue">{{ config('neva.product_types.'.$type) }}</a></li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            <a href="{{ route('customization') }}">Personalización</a>
            <a href="{{ route('catalogs.index') }}">Catálogos PDF</a>
            <a href="{{ route('contact') }}" class="btn-primary">Cotizar</a>
        </div>
    </nav>
</header>
