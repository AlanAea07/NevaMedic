@extends('layouts.site')
@section('title', 'Catálogos PDF por sector')
@section('content')
<section class="bg-neva-navy text-white"><div class="page-shell py-14"><p class="text-xs font-semibold uppercase tracking-widest text-neva-silver">Colecciones para compartir</p><h1 class="mt-4 font-display text-5xl md:text-6xl">Tu catálogo, listo para llevar.</h1><p class="mt-5 max-w-2xl text-lg text-neva-silver">Elige tu sector y descarga sus productos disponibles, precios, tallas y colores en un solo PDF.</p><p class="mt-6 text-sm">Sin registro · Actualizado al descargar · Fácil de compartir</p></div></section>
<section class="page-shell py-12" aria-label="Catálogos por sector">
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4"><div><p class="eyebrow">Elige tu colección</p><h2 class="section-title">¿Para qué sector buscas?</h2></div><p class="max-w-sm text-sm text-neva-muted">Puedes ver el PDF antes de descargarlo. El enlace compartido siempre lleva a la versión actualizada.</p></div>
    <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
    @forelse($sectors as $sector)
        @php
            $theme = config('catalogs.themes.'.$sector->slug, config('catalogs.default_theme'));
            $download = route('catalogs.download', $sector->slug);
        @endphp
        <article class="flex flex-col overflow-hidden rounded-xl border border-neva-line bg-white shadow-sm" x-data="catalogCard({{ Illuminate\Support\Js::from($download) }})">
            <div class="flex items-center gap-4 p-7" style="background:{{ $theme['light'] }};color:{{ $theme['color'] }};"><span class="rounded-xl bg-white p-3"><x-catalog.sector-icon :name="$theme['icon']" /></span><div><p class="text-xs uppercase tracking-widest">Catálogo PDF</p><h3 class="font-display text-3xl">{{ $sector->name }}</h3></div></div>
            <div class="flex flex-1 flex-col p-7"><p class="text-neva-muted">{{ $theme['tagline'] }}</p><p class="mb-6 mt-4 font-semibold">{{ $sector->products_count }} {{ $sector->products_count === 1 ? 'producto disponible' : 'productos disponibles' }}</p>
            @if($sector->products_count)
                <a class="btn-primary mt-auto w-full" href="{{ $download }}" @click.prevent="download()" :aria-disabled="busy" :aria-busy="busy"><span x-text="busy ? 'Preparando PDF…' : 'Descargar PDF'">Descargar PDF</span></a>
                <a class="btn-secondary mt-3 w-full" href="{{ route('catalogs.preview', $sector->slug) }}" target="_blank" rel="noopener">Vista previa <span class="sr-only">de {{ $sector->name }}, abre en otra pestaña</span></a>
                <div class="mt-5 flex flex-wrap gap-x-5 gap-y-3 text-sm"><button type="button" class="font-semibold text-neva-blue hover:underline" @click="copy()">Copiar enlace del PDF</button><a class="font-semibold text-green-700 hover:underline" href="https://wa.me/?text={{ rawurlencode('Catálogo Neva Medic - '.$sector->name.': '.$download) }}" target="_blank" rel="noopener noreferrer">Compartir por WhatsApp</a></div>
                <p class="mt-3 text-sm text-neva-muted" role="status" aria-live="polite" x-text="message"></p>
                <label class="mt-3 text-sm" x-cloak x-show="showLink">Enlace para compartir<input class="form-input" readonly value="{{ $download }}" @focus="$el.select()"></label>
            @else
                <p class="mt-auto rounded bg-neva-mist p-4 text-sm text-neva-muted">Estamos preparando esta colección. El PDF estará disponible cuando haya productos.</p>
            @endif
            </div>
        </article>
    @empty<x-ui.empty-state title="Colecciones en preparación" message="Pronto podrás consultar nuestros catálogos por sector." />@endforelse
    </div>
    <div class="mt-10 rounded-xl bg-neva-mist p-6 text-sm text-neva-muted"><strong class="text-neva-ink">Una selección completa para cada sector.</strong> Si una prenda pertenece a varios sectores, la encontrarás en cada uno de sus catálogos. Los precios son de referencia y se confirman al cotizar.</div>
</section>
@endsection
