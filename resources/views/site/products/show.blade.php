@extends('layouts.site')
@section('title', $product->name)
@section('content')
<div class="page-shell py-12">
    @if($preview ?? false)<p class="mb-6 rounded bg-amber-50 p-4 text-amber-900">Vista previa administrativa · {{ $product->is_available ? 'Visible en el catálogo' : 'Oculto del catálogo' }}</p>@endif
    <a class="text-neva-blue" href="{{ route('products.index') }}">← Catálogo</a>
    <div class="mt-8 grid gap-10 lg:grid-cols-2">
        <x-catalog.gallery :product="$product" />
        <div class="space-y-6">
            <p class="eyebrow">{{ $product->sectors->where('is_active', true)->pluck('name')->join(' · ') }}</p>
            <h1 class="section-title">{{ $product->name }}</h1><p class="text-xl text-neva-muted">{{ $product->short_description }}</p>
            <p class="text-3xl font-semibold">${{ number_format((float) $product->reference_price, 2) }} {{ $product->currency }}</p>
            <p>{{ $product->is_available ? 'Disponible' : 'No disponible' }} · Precio de referencia, sujeto a cotización.</p>
            <p class="whitespace-pre-line">{{ $product->description }}</p>
            @if($product->available_sizes)
                <div><h2 class="mb-3 font-semibold">Tallas disponibles</h2><ul class="flex flex-wrap gap-2" aria-label="Tallas disponibles">@foreach(config('neva.sizes') as $size)@if(in_array($size, $product->available_sizes))<li class="rounded-lg border border-neva-line bg-neva-mist px-4 py-2 font-semibold">{{ $size }}</li>@endif
                @endforeach</ul></div>
            @endif
            <a class="btn-primary" href="{{ route('contact', ['product' => $product->slug]) }}">Consultar esta prenda</a>
            @if($product->material)<div><h2 class="font-semibold">Material</h2><p>{{ $product->material }}</p></div>@endif
            @if($product->care_instructions)<div><h2 class="font-semibold">Cuidados</h2><p class="whitespace-pre-line">{{ $product->care_instructions }}</p></div>@endif
        </div>
    </div>
</div>
@endsection
