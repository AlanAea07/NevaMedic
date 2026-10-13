@extends('layouts.site')
@section('title', $product->name)
@section('content')
@php($colors = $product->has_variants ? $product->variants->where('is_available', true) : collect())
@php($hasWhatsapp = preg_match('/^[1-9][0-9]{9,14}$/', (string) config('neva.whatsapp')))
<div class="page-shell py-12">
    @if($preview ?? false)<p class="mb-6 rounded bg-amber-50 p-4 text-amber-900">Vista previa administrativa · {{ $product->is_available ? 'Visible en el catálogo' : 'Oculto del catálogo' }}</p>@endif
    <nav aria-label="Ruta del producto" class="text-sm text-neva-muted"><a class="hover:text-neva-blue" href="{{ route('products.index') }}">Catálogo</a><span aria-hidden="true" class="mx-2">/</span>{{ $product->name }}</nav>
    <x-catalog.gallery :product="$product">
        <p class="eyebrow">{{ $product->sectors->where('is_active', true)->pluck('name')->join(' · ') }}</p>
        <h1 class="section-title">{{ $product->name }}</h1>
        <div class="space-y-1">
            <p class="text-3xl font-semibold">${{ number_format((float) $product->reference_price, 2) }} <span class="text-base">{{ $product->currency }}</span></p>
            <p class="text-sm text-neva-muted">Precio de referencia. Confirma tu cotización con nuestro equipo.</p>
        </div>
        <p class="flex items-center gap-1.5 text-sm font-semibold {{ $product->is_available ? 'text-green-700' : 'text-neva-muted' }}">@if($product->is_available)<x-ui.nav-icon name="check" />@endif{{ $product->is_available ? 'Disponible' : 'No disponible' }}</p>
        @if($product->short_description)<p class="text-lg text-neva-muted">{{ $product->short_description }}</p>@endif
        @if($colors->isNotEmpty())
            <fieldset>
                <legend class="mb-2 text-sm font-semibold">Elige un color<span x-cloak x-show="colorName" x-text="' · ' + colorName"></span></legend>
                <div class="flex flex-wrap gap-2">
                    @foreach($colors as $variant)
                        <button type="button" class="flex h-11 w-11 items-center justify-center rounded-full border-2 transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-neva-blue" :class="color === {{ $variant->id }} ? 'border-neva-ink' : 'border-transparent hover:border-neva-silver'" @click="selectColor({{ $variant->id }})" :aria-pressed="color === {{ $variant->id }}" aria-label="{{ $variant->color }}" title="{{ $variant->color }}">
                            <span class="h-7 w-7 rounded-full border border-neva-line" style="background-color: {{ preg_match('/^#[a-fA-F0-9]{6}$/', $variant->color_hex ?? '') ? $variant->color_hex : '#bcc4cf' }}"></span><span class="sr-only">{{ $variant->color }}</span>
                        </button>
                    @endforeach
                </div>
            </fieldset>
        @endif
        @if($product->available_sizes)
            <fieldset>
                <legend class="mb-3 text-sm font-semibold"><span class="flex items-center gap-2"><x-ui.nav-icon name="ruler" />Tallas disponibles</span></legend>
                <div class="flex flex-wrap gap-2">
                    @foreach(config('neva.sizes') as $size)
                        @if(in_array($size, $product->available_sizes))
                            <button type="button" data-size="{{ $size }}" class="h-12 min-w-[3.5rem] rounded-sm border px-4 text-sm font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-neva-blue" :class="size === '{{ $size }}' ? 'border-neva-ink bg-neva-ink text-white' : 'border-neva-line bg-white text-neva-ink hover:border-neva-ink'" @click="size = '{{ $size }}'; sizeAdvice = false" :aria-pressed="size === '{{ $size }}'">{{ $size }}</button>
                        @endif
                    @endforeach
                </div>
                <button type="button" class="mt-2 min-h-11 rounded px-2 text-sm underline underline-offset-4 focus-visible:outline focus-visible:outline-2 focus-visible:outline-neva-blue" :class="sizeAdvice ? 'bg-neva-mist font-semibold text-neva-blue' : 'text-neva-muted'" @click="sizeAdvice = !sizeAdvice; if (sizeAdvice) size = null" :aria-pressed="sizeAdvice">Necesito asesoría de talla</button>
            </fieldset>
        @endif
        <div class="space-y-3 rounded border border-neva-line bg-neva-mist p-5">
            <p x-cloak x-show="size || colorName || sizeAdvice" class="text-sm" role="status" aria-live="polite" aria-atomic="true"><span class="font-semibold">Tu selección: </span><span x-text="selectionSummary"></span></p>
            <a class="btn-primary flex w-full items-center justify-center gap-2" href="{{ route('contact', ['product' => $product->slug, 'direct' => 1]) }}" :href="quoteUrl" @if($hasWhatsapp) target="_blank" rel="noopener noreferrer" @endif><x-ui.nav-icon name="chat" />{{ $hasWhatsapp ? 'Cotizar por WhatsApp' : 'Consultar opciones de contacto' }}</a>
            <p class="text-xs text-neva-muted">{{ $hasWhatsapp ? 'Se abrirá WhatsApp con tu selección. Puedes cotizar aunque aún no sepas tu talla o color.' : 'Consulta el canal de atención disponible para tu cotización.' }}</p>
            <p class="flex items-center gap-2 text-sm text-neva-muted"><x-ui.nav-icon name="truck" />Envíos nacionales · Personalización bajo cotización</p>
        </div>
    <x-slot:details>
    @if($product->description)
        <section class="mt-10 max-w-3xl" aria-labelledby="product-description">
            <h2 id="product-description" class="mb-3 text-xl font-semibold">Detalles de la prenda</h2>
            <p class="whitespace-pre-line text-neva-muted">{{ $product->description }}</p>
        </section>
    @endif
    <section class="mt-10 grid gap-8 bg-neva-mist p-6 md:grid-cols-2 md:p-8" aria-label="Detalles y personalización">
        @if($product->material || $product->care_instructions)
            <div>
                <h2 class="mb-3 flex items-center gap-2 font-semibold"><x-ui.nav-icon name="care" />Material y cuidados</h2>
                @if($product->material)<p class="text-sm text-neva-muted">{{ $product->material }}</p>@endif
                @if($product->care_instructions)<p class="whitespace-pre-line text-sm text-neva-muted">{{ $product->care_instructions }}</p>@endif
            </div>
        @endif
        <div>
            <h2 class="mb-3 flex items-center gap-2 font-semibold"><x-ui.nav-icon name="admin.products.index" />Hazla parte de tu equipo</h2>
            <p class="text-sm text-neva-muted">Consulta telas, bordados y acabados para tu institución.</p>
            <a href="{{ route('customization', ['product' => $product->slug]) }}" :href="customizationUrl" class="mt-3 inline-block text-sm font-semibold text-neva-blue underline underline-offset-4">Personalizar esta prenda</a>
        </div>
    </section>
    </x-slot:details>
    </x-catalog.gallery>
</div>
@endsection
