@extends('layouts.site')
@section('title', 'Inicio')
@section('content')
<section class="bg-neva-ink px-6 py-16 text-center text-white">
    <p class="text-sm font-semibold tracking-[.18em]">NEVA MEDIC TE DA LA BIENVENIDA</p>
    <img src="{{ asset('images/brand/emblem.png') }}" alt="Emblema Neva Medic" class="mx-auto my-8 h-56 w-64 object-contain md:h-72 md:w-80">
    <h1 class="font-display text-5xl leading-tight md:text-7xl">Elegancia y confort<br>para salvar vidas.</h1>
    <p class="mt-5 text-lg">Uniformes clínicos, médicos e industriales</p>
    <p class="mx-auto mt-4 max-w-2xl text-neva-silver">Prendas profesionales para personas e instituciones, con atención y envíos a nivel nacional.</p>
    <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row"><a class="btn-primary" href="{{ route('products.index') }}">Ver catálogo digital</a><a class="btn-light" href="{{ route('contact') }}">Contactar directamente</a><a class="btn-light" href="{{ route('customization') }}">Solicitar personalización</a></div>
</section>
<section class="page-shell py-16">
    <p class="eyebrow">Catálogo por sector</p><h2 class="section-title">Prendas para cada entorno de trabajo.</h2>
    <div class="mt-8 grid gap-6 md:grid-cols-3">
        @forelse($sectors as $sector)
            <a href="{{ route('products.index', ['sector' => $sector->slug]) }}" class="space-y-5 rounded bg-neva-navy p-8 text-white"><h3 class="font-display text-4xl">{{ $sector->name }}</h3><p class="text-neva-silver">{{ $sector->description }}</p><span class="block font-semibold">Explorar sector →</span></a>
        @empty
            <div class="md:col-span-3"><x-ui.empty-state title="Estamos preparando nuestras colecciones" /></div>
        @endforelse
    </div>
</section>
<section class="bg-neva-mist py-16"><div class="page-shell"><p class="eyebrow">Selección Neva Medic</p><h2 class="section-title">Diseño que trabaja contigo.</h2><div class="mt-8 grid gap-6 md:grid-cols-3">@forelse($products as $product)<x-catalog.product-card :product="$product" />@empty<div class="md:col-span-3"><x-ui.empty-state title="Próximamente, productos destacados" /></div>@endforelse</div></div></section>
@endsection
