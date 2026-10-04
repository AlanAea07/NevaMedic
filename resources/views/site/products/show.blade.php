@extends('layouts.site')
@section('title', $product->name)
@section('content')
<div class="page-shell py-12">
    @if($preview ?? false)<p class="mb-6 rounded bg-amber-50 p-4 text-amber-900">Vista previa administrativa · {{ $product->is_available ? 'Visible en el catálogo' : 'Oculto del catálogo' }}</p>@endif
    <a class="text-neva-blue" href="{{ route('products.index') }}">← Catálogo</a>
    <div class="mt-8 grid gap-10 lg:grid-cols-2">
        <div>@forelse($product->images as $image)<div x-data="{ failed: false }"><img x-show="!failed" x-on:error="failed = true" src="{{ $image->url }}" alt="{{ $image->alt }}" class="mb-4 w-full rounded" loading="lazy" referrerpolicy="no-referrer"><p x-cloak x-show="failed" class="mb-4 rounded bg-neva-mist p-8 text-neva-muted">Imagen no disponible</p></div>@empty<x-ui.empty-state title="Fotografías pendientes" message="Consulta con nuestro equipo las opciones de esta prenda." />@endforelse</div>
        <div class="space-y-6">
            <p class="eyebrow">{{ $product->sectors->where('is_active', true)->pluck('name')->join(' · ') }}</p>
            <h1 class="section-title">{{ $product->name }}</h1><p class="text-xl text-neva-muted">{{ $product->short_description }}</p>
            <p class="text-3xl font-semibold">${{ number_format((float) $product->reference_price, 2) }} {{ $product->currency }}</p>
            <p>{{ $product->is_available ? 'Disponible' : 'No disponible' }} · Precio de referencia, sujeto a cotización.</p>
            <p class="whitespace-pre-line">{{ $product->description }}</p>
            @if($product->variants->isNotEmpty())<div><h2 class="font-semibold">Tallas y variantes</h2><ul class="mt-3 space-y-2">@foreach($product->variants as $variant)<li>{{ $variant->color }} · {{ $variant->size }} {{ $variant->design }} · {{ $variant->is_available ? 'Disponible' : 'No disponible' }}</li>@endforeach</ul></div>@endif
            <a class="btn-primary" href="{{ route('contact', ['product' => $product->slug]) }}">Consultar esta prenda</a>
            @if($product->material)<div><h2 class="font-semibold">Material</h2><p>{{ $product->material }}</p></div>@endif
            @if($product->care_instructions)<div><h2 class="font-semibold">Cuidados</h2><p class="whitespace-pre-line">{{ $product->care_instructions }}</p></div>@endif
        </div>
    </div>
</div>
@endsection
