@extends('layouts.site')
@section('title', 'Catálogos por sector')
@section('content')
<section class="page-shell py-16"><p class="eyebrow">Catálogos</p><h1 class="section-title">Tu colección, por sector.</h1><p class="mt-5 text-neva-muted">Consulta los productos publicados de cada colección. Las descargas PDF estarán disponibles próximamente.</p><div class="mt-8 grid gap-6 md:grid-cols-3">@forelse($sectors as $sector)<article class="rounded border border-neva-line p-8"><h2 class="font-display text-4xl">{{ $sector->name }}</h2><p class="my-4">{{ $sector->products_count }} productos publicados</p><a href="{{ route('products.index', ['sector' => $sector->slug]) }}" class="btn-secondary">Ver colección</a></article>@empty<x-ui.empty-state title="Colecciones en preparación" />@endforelse</div></section>
@endsection
