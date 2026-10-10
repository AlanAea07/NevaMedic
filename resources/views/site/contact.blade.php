@extends('layouts.site')
@section('title', 'Contacto')
@section('content')
<section class="page-shell grid gap-10 py-16 md:grid-cols-2"><div><p class="eyebrow">Atención directa</p><h1 class="section-title">De Torreón para todo México.</h1><p class="mt-6 text-xl text-neva-muted">Te ayudamos a revisar disponibilidad, personalización y condiciones de envío.</p><p class="mt-4">{{ config('neva.location') }}</p></div><div class="rounded bg-neva-navy p-8 text-white"><h2 class="font-display text-4xl">Hablemos de tu próximo uniforme.</h2><p class="my-6">Comparte el modelo, talla, color y ciudad para preparar tu cotización.</p>
@if($product)
<div class="mb-6 rounded border border-white/20 p-4"><p class="font-semibold">Tu selección: {{ $product->name }}</p>@if($size)<p>Talla: {{ $size }}</p>@endif @if($color)<p>Color: {{ $color }}</p>@endif</div>
@endif
@if($sizeAdvice)<p class="mb-6 text-sm">Necesitas asesoría para elegir tu talla. Incluiremos esta solicitud en tu mensaje.</p>@endif
@if(preg_match('/^[1-9][0-9]{9,14}$/', (string) config('neva.whatsapp')))
<a class="btn-light" href="https://wa.me/{{ config('neva.whatsapp') }}?text={{ rawurlencode($quoteMessage) }}" target="_blank" rel="noopener noreferrer">Contactar por WhatsApp</a>
@else<p class="text-neva-silver">El canal de contacto estará disponible próximamente.</p>@endif
</div></section>
<section class="page-shell pb-16" aria-labelledby="location-title">
    <p class="eyebrow">Nuestra ciudad</p>
    <h2 id="location-title" class="section-title">Torreón, Coahuila</h2>
    <p class="mb-6 mt-3 text-neva-muted">Ubicación general de la ciudad. Atención y envíos a todo México.</p>
    <iframe title="Mapa de Torreón, Coahuila, México" src="https://maps.google.com/maps?q=Torreon%2C%20Coahuila%2C%20Mexico&amp;z=12&amp;output=embed" width="100%" height="420" class="w-full rounded-xl border border-neva-line" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
</section>
@endsection
