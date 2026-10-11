@extends('layouts.site')
@section('title', 'Inicio')
@section('content')
<section class="home-hero overflow-hidden text-white" aria-labelledby="home-title">
    <div class="page-shell py-10 text-center sm:py-12">
        <p class="text-xs font-bold uppercase tracking-[.18em] text-neva-silver sm:text-sm">NEVA MEDIC TE DA LA BIENVENIDA</p>
        <div class="home-emblem" role="img" aria-label="Emblema Neva Medic">
            <img src="{{ asset('images/brand/emblem-flat-source.png') }}" alt="" aria-hidden="true" class="home-emblem-source" width="1254" height="1254" fetchpriority="high">
        </div>
        <h1 id="home-title" class="font-display text-5xl leading-none sm:text-6xl">Elegancia y confort<br>para salvar vidas.</h1>
        <p class="mt-3 text-sm font-semibold">Uniformes clínicos, médicos e industriales</p>
        <p class="mx-auto mt-3 max-w-xl text-sm leading-relaxed text-neva-silver">Conoce prendas profesionales para personas e instituciones. Consulta modelos, colores, tallas y precios de referencia, con atención y envíos a nivel nacional.</p>
        <div class="mt-6 flex flex-col justify-center gap-3 sm:flex-row sm:flex-wrap">
            <a class="btn-primary gap-2" href="{{ route('products.index') }}"><x-ui.nav-icon name="catalog" />Ver catálogo digital</a>
            <a class="btn-light gap-2" href="{{ route('contact') }}"><x-ui.nav-icon name="chat" />Contactar directamente</a>
            <a class="btn-light gap-2" href="{{ route('customization') }}"><x-ui.nav-icon name="customize" />Solicitar personalización</a>
        </div>
        <a href="#sectores" class="mt-3 inline-flex min-h-11 items-center gap-2 text-sm text-neva-silver underline underline-offset-4 focus-visible:outline focus-visible:outline-2 focus-visible:outline-white">Encuentra tu sector <span aria-hidden="true">↓</span></a>
    </div>
    <div class="border-t border-white/15 bg-black/10"><div class="page-shell grid gap-5 py-6 sm:grid-cols-3">
        <div class="flex items-center gap-3"><x-ui.nav-icon name="truck" class="h-6 w-6 text-blue-200" /><div><p class="text-sm font-semibold">Envíos nacionales</p><p class="text-xs text-neva-silver">De Torreón para todo México</p></div></div>
        <div class="flex items-center gap-3"><x-ui.nav-icon name="admin.products.index" class="h-6 w-6 text-blue-200" /><div><p class="text-sm font-semibold">Prendas para tu equipo</p><p class="text-xs text-neva-silver">Personalización bajo cotización</p></div></div>
        <div class="flex items-center gap-3"><x-ui.nav-icon name="chat" class="h-6 w-6 text-blue-200" /><div><p class="text-sm font-semibold">Atención directa</p><p class="text-xs text-neva-silver">Te ayudamos a elegir</p></div></div>
    </div></div>
</section>
<section id="sectores" class="page-shell scroll-mt-8 py-16 lg:py-20" aria-labelledby="sectors-title">
    <div data-reveal class="max-w-3xl"><p class="eyebrow">Catálogo por sector</p><h2 id="sectors-title" class="section-title">Prendas para cada entorno de trabajo.</h2><p class="mt-4 text-lg text-neva-muted">Encuentra las opciones para tu profesión o explora nuestra colección completa.</p></div>
    <div class="mt-9 grid gap-5 md:grid-cols-3">
        @forelse($sectors as $sector)
            @php
                $theme = config('catalogs.themes.'.$sector->slug, config('catalogs.default_theme'));
            @endphp
            <div data-reveal class="min-w-0" style="--reveal-delay: {{ ($loop->index % 3) * 70 }}ms">
                <a href="{{ route('products.index', ['sector' => $sector->slug]) }}" class="sector-card flex h-full flex-col rounded-2xl border border-neva-line bg-white p-7 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-neva-blue" style="--sector-color: {{ $theme['color'] }}; --sector-light: {{ $theme['light'] }}">
                    <div class="mb-7 flex items-center justify-between gap-3"><span class="sector-symbol inline-flex h-16 w-16 items-center justify-center rounded-2xl"><x-catalog.sector-icon :name="$theme['icon']" /></span><span class="rounded-full bg-neva-mist px-3 py-1 text-xs font-semibold text-neva-muted">{{ $sector->products_count }} {{ $sector->products_count === 1 ? 'producto' : 'productos' }}</span></div>
                    <h3 class="font-display text-4xl text-neva-ink">{{ $sector->name }}</h3>
                    <p class="mb-7 mt-3 leading-relaxed text-neva-muted">{{ $sector->description ?: $theme['tagline'] }}</p>
                    <span class="mt-auto flex min-h-11 items-center justify-between gap-3 border-t border-neva-line pt-4 text-sm font-semibold text-neva-blue">Explorar sector <x-ui.nav-icon name="chevron-right" class="sector-arrow" /></span>
                </a>
            </div>
        @empty
            <div class="md:col-span-3"><x-ui.empty-state title="Estamos preparando nuestras colecciones" /></div>
        @endforelse
    </div>
    <a data-reveal href="{{ route('products.index') }}" class="all-products-card mt-6 flex flex-col gap-5 rounded-2xl bg-neva-navy p-6 text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-neva-blue sm:flex-row sm:items-center sm:p-8">
        <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-white/10"><x-catalog.sector-icon name="catalog" /></span>
        <div class="flex-1"><h3 class="font-display text-3xl">Productos en general</h3><p class="mt-2 text-sm text-neva-silver">Todas las prendas del catálogo, sin filtros por sector.</p></div>
        <span class="inline-flex min-h-11 items-center gap-3 text-sm font-semibold">Explorar todo <x-ui.nav-icon name="chevron-right" /></span>
    </a>
</section>
<section class="bg-neva-mist py-16 lg:py-20" aria-labelledby="featured-title"><div class="page-shell">
    <div data-reveal class="mb-9 flex flex-wrap items-end justify-between gap-5"><div><p class="eyebrow">Selección Neva Medic</p><h2 id="featured-title" class="section-title">Diseño que trabaja contigo.</h2></div><a class="btn-secondary gap-2" href="{{ route('products.index') }}">Productos en general <x-ui.nav-icon name="chevron-right" /></a></div>
    <div class="grid gap-6 md:grid-cols-3">@forelse($products as $product)<div data-reveal class="min-w-0" style="--reveal-delay: {{ $loop->index * 70 }}ms"><x-catalog.product-card :product="$product" /></div>@empty<div class="md:col-span-3"><x-ui.empty-state title="Próximamente, productos destacados" /></div>@endforelse</div>
    <section class="mt-14 border-t border-neva-line pt-12" aria-labelledby="home-catalogs-title">
        <div data-reveal class="mb-7"><p class="eyebrow">Catálogos para llevar</p><h2 id="home-catalogs-title" class="font-display text-4xl md:text-5xl">Descarga tu catálogo en PDF.</h2><p class="mt-3 text-neva-muted">Elige un sector y guarda sus productos disponibles para consultarlos o compartirlos.</p></div>
        <div class="grid gap-5 md:grid-cols-3">
            @forelse($sectors as $sector)
                @php
                    $theme = config('catalogs.themes.'.$sector->slug, config('catalogs.default_theme'));
                    $download = route('catalogs.download', $sector->slug);
                @endphp
                <article data-reveal class="flex min-w-0 flex-col overflow-hidden rounded-xl border border-neva-line bg-white" x-data="catalogCard({{ Illuminate\Support\Js::from($download) }})">
                    <div class="flex items-center gap-4 p-6" style="background: {{ $theme['light'] }}; color: {{ $theme['color'] }}"><span class="rounded-xl bg-white p-3"><x-catalog.sector-icon :name="$theme['icon']" /></span><div><p class="text-xs font-semibold uppercase tracking-widest">Catálogo PDF</p><h3 class="font-display text-3xl">{{ $sector->name }}</h3></div></div>
                    <div class="flex flex-1 flex-col p-6">
                        <p class="mb-5 text-sm text-neva-muted">{{ $sector->products_count }} {{ $sector->products_count === 1 ? 'producto disponible' : 'productos disponibles' }} · Actualizado al descargar</p>
                        @if($sector->products_count)
                            <a href="{{ $download }}" class="btn-primary mt-auto w-full gap-2" @click.prevent="download()" :aria-disabled="busy" :aria-busy="busy" aria-label="Descargar PDF del sector {{ $sector->name }}"><x-ui.nav-icon name="download" /><span x-text="busy ? 'Preparando PDF…' : 'Descargar PDF'">Descargar PDF</span></a>
                            <a href="{{ route('catalogs.preview', $sector->slug) }}" class="mt-2 inline-flex min-h-11 items-center justify-center text-sm font-semibold text-neva-blue underline underline-offset-4" target="_blank" rel="noopener">Ver antes de descargar<span class="sr-only">: {{ $sector->name }}, abre en otra pestaña</span></a>
                            <p class="mt-2 text-sm text-neva-muted" role="status" aria-live="polite" x-text="message"></p>
                        @else
                            <p class="mt-auto rounded bg-neva-mist p-3 text-sm text-neva-muted">Estamos preparando este catálogo. Estará disponible cuando haya productos.</p>
                        @endif
                    </div>
                </article>
            @empty
                <div class="md:col-span-3"><x-ui.empty-state title="Catálogos en preparación" message="Pronto podrás descargar nuestras colecciones." /></div>
            @endforelse
        </div>
    </section>
    <div data-reveal class="mt-10 flex flex-col gap-5 rounded-xl border border-neva-line bg-white p-6 sm:flex-row sm:items-center sm:justify-between"><div><h3 class="text-lg font-semibold">¿Buscas uniformes para tu institución?</h3><p class="mt-1 text-sm text-neva-muted">Conoce las opciones de telas, bordados y acabados para tu equipo.</p></div><a class="btn-primary shrink-0 gap-2" href="{{ route('customization') }}">Ver personalización <x-ui.nav-icon name="chevron-right" /></a></div>
</div></section>
@endsection
