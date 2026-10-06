<!doctype html>
<html lang="es"><head><meta charset="utf-8"><title>Catálogo {{ $sector->name }} · Neva Medic</title>
<style>
@page { margin: 36pt 40pt 48pt; }
body { font-family: 'DejaVu Sans', sans-serif; color: #07101e; font-size: 10pt; line-height: 1.45; }
* { box-sizing: border-box; } h1,h2,h3,p { margin: 0; } a { color: {{ $theme['color'] }}; text-decoration: none; }
.page { page-break-after: always; } .last { page-break-after: auto; }
.eyebrow { font-size: 9pt; letter-spacing: 2pt; text-transform: uppercase; color: {{ $theme['color'] }}; }
.muted { color:#627085; } .brand { font-size: 18pt; font-weight: bold; letter-spacing: 2pt; }
.cover-title { font-size: 34pt; line-height: 1.12; margin: 32pt 0 12pt; color: {{ $theme['color'] }}; }
.cover-band { background: {{ $theme['color'] }}; color: white; padding: 17pt 22pt; margin-top: 20pt; }
.cover-photo { text-align:center; background: {{ $theme['light'] }}; padding: 12pt; margin-top: 24pt; height: 300pt; }
.cover-photo img { max-height: 276pt; max-width: 430pt; }
.footer { position: fixed; bottom:-27pt; left:0; right:0; border-top:1pt solid #dce2ea; padding-top:7pt; font-size:7pt; color:#627085; }
.header { border-bottom:2pt solid {{ $theme['color'] }}; padding-bottom:10pt; margin-bottom:16pt; }
.product-name { font-size: 23pt; line-height:1.15; margin:8pt 0 12pt; overflow-wrap:break-word; }
.photo { height:275pt; text-align:center; padding:10pt; background:{{ $theme['light'] }}; }
.photo img { max-height:255pt; max-width:425pt; }
.placeholder { padding-top:110pt; color:#627085; }
.price { color:{{ $theme['color'] }}; font-size:24pt; font-weight:bold; margin-top:12pt; }
.label { font-size:9pt; font-weight:bold; margin-top:13pt; margin-bottom:6pt; }
.chip { display:inline-block; border:1pt solid #dce2ea; padding:3pt 8pt; margin:0 4pt 5pt 0; font-size:9pt; }
.swatch { display:inline-block; width:9pt; height:9pt; border:1pt solid #bcc4cf; margin-right:5pt; vertical-align:middle; }
.contact-box { background:{{ $theme['light'] }}; padding:22pt; margin:22pt 0; }
.qr { width:120pt; height:120pt; } .qr-block { text-align:center; padding:20pt; }
.small {font-size:8pt;} .description { margin:10pt 0; font-size:10pt; }
</style></head><body>
<div class="footer">NEVA MEDIC · {{ $sector->name }} · {{ $siteUrl }}<br>Pedidos: {{ $whatsapp ? '+'.$whatsapp : config('catalogs.example_whatsapp').' (ejemplo)' }}</div>
<div class="page">
    <p class="brand">NEVA MEDIC</p><p class="muted">{{ config('neva.tagline') }}</p>
    <h1 class="cover-title">Catálogo<br>Sector {{ $sector->name }}<br>{{ $generatedAt->year }}</h1>
    <p style="font-size:14pt;">{{ $theme['tagline'] }}</p>
    <div class="cover-photo">@if($cover = collect($photos)->filter()->first())<img src="{{ $cover }}" alt="Prenda de la colección">@else<p class="placeholder">Colección {{ $sector->name }}</p>@endif</div>
    <div class="cover-band"><strong>{{ $products->count() }} {{ $products->count() === 1 ? 'producto disponible' : 'productos disponibles' }}</strong><br>Selección actualizada para tu sector.</div>
    <p class="small muted" style="margin-top:14pt;">Generado el {{ $generatedAt->format('d/m/Y H:i') }}.<br>Precios de referencia en MXN, sujetos a confirmación al cotizar.</p>
</div>
@foreach($products as $product)
<div class="page">
    <div class="header"><span class="brand" style="font-size:13pt;">NEVA MEDIC</span><span class="muted" style="float:right;">Sector {{ $sector->name }}</span></div>
    <p class="eyebrow">Código NM-{{ str_pad((string) $product->id, 6, '0', STR_PAD_LEFT) }}</p>
    <h2 class="product-name">{{ $product->name }}</h2>
    <div class="photo">@if($photos[$product->id])<img src="{{ $photos[$product->id] }}" alt="{{ $product->name }}">@else<p class="placeholder">Fotografía no disponible. Consulta la ficha en línea.</p>@endif</div>
    <p class="price">${{ number_format((float) $product->reference_price, 2) }} <span style="font-size:11pt;">{{ $product->currency }}</span></p>
    <p class="small muted">Precio de referencia actualizado al generar este catálogo.</p>
    <p class="description">{{ $product->short_description }}</p>
    <p class="label">TALLAS DISPONIBLES</p>
    @forelse($product->available_sizes ?? [] as $size)<span class="chip">{{ $size }}</span>@empty<p class="muted">Consultar tallas con nuestro equipo.</p>@endforelse
    <p class="label">COLORES DISPONIBLES</p>
    @forelse($product->has_variants ? $product->variants->where('is_available', true) : collect() as $variant)
        <span class="chip"><span class="swatch" style="background:{{ preg_match('/^#[a-fA-F0-9]{6}$/', $variant->color_hex ?? '') ? $variant->color_hex : '#bcc4cf' }};"></span>{{ $variant->color }}</span>
    @empty<p class="muted">Consultar colores con nuestro equipo.</p>@endforelse
    <p style="margin-top:12pt;"><a href="{{ route('products.show', $product->slug) }}">Ver fotografías y detalles del producto en el sitio →</a></p>
</div>
@endforeach
<div class="last">
    <p class="brand">NEVA MEDIC</p><h2 class="cover-title">De Torreón<br>para todo México.</h2>
    <p style="font-size:14pt;">Hablemos de los uniformes de tu equipo.</p>
    <div class="contact-box"><p class="label">CONTACTO Y PEDIDOS</p><p>{{ config('neva.location') }}</p><p><a href="{{ url('/contacto') }}">{{ url('/contacto') }}</a></p>
    @if($whatsapp)<p style="margin-top:12pt;">WhatsApp: <a href="https://wa.me/{{ $whatsapp }}">+{{ $whatsapp }}</a></p>@else<p style="margin-top:12pt;">WhatsApp de ejemplo: {{ config('catalogs.example_whatsapp') }}</p><p class="small muted">Número ilustrativo. El canal de pedidos está pendiente de confirmación.</p>@endif
    </div>
    <div class="qr-block"><img class="qr" src="{{ $qr }}" alt="QR del catálogo"><p class="label">COMPARTE ESTA COLECCIÓN</p><p>Escanea para descargar la versión actualizada<br>del catálogo {{ $sector->name }}.</p><p class="small" style="margin-top:12pt;"><a href="{{ $downloadUrl }}">{{ $downloadUrl }}</a></p></div>
    <p class="small muted">Este documento refleja los productos disponibles al momento de su generación. Los productos que pertenecen a varios sectores aparecen en cada catálogo correspondiente.</p>
</div>
</body></html>
