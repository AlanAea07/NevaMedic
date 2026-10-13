@extends('layouts.site')
@section('title', 'Personalización')
@section('content')
<section class="page-shell py-12 md:py-16">
    <p class="eyebrow">Proyectos institucionales</p><h1 class="section-title">Una identidad que se viste.</h1>
    <p class="mt-5 max-w-2xl text-lg text-neva-muted">Prepara tu solicitud con el modelo, las cantidades y los detalles que ya tengas. Nuestro equipo te ayudará a definir el resto por WhatsApp.</p>
    <x-ui.feedback />
    <noscript><p class="mt-6">Activa JavaScript para preparar tu solicitud o <a class="underline" href="{{ route('contact') }}">contacta a nuestro equipo</a>.</p></noscript>
    <form x-cloak x-data="customizationQuote({{ Illuminate\Support\Js::from($models) }}, {{ Illuminate\Support\Js::from($initial) }})" method="post" action="{{ route('customization.quote') }}" class="mt-10 grid items-start gap-8 lg:grid-cols-3">
        @csrf
        <div class="space-y-6 lg:col-span-2">
            <section class="rounded border border-neva-line p-5 md:p-7">
                <p class="eyebrow">01 · Tu modelo</p><h2 class="mt-2 text-xl font-semibold">La prenda que te interesa</h2>
                <label class="mt-5 block">Modelo<select class="form-input" name="product" x-model="product" @change="changeModel()"><option value="">Necesito asesoría para elegir</option>@foreach($models as $model)<option value="{{ $model['slug'] }}">{{ $model['name'] }}</option>@endforeach</select></label>
                <div x-show="model" class="mt-5 flex items-center gap-4"><template x-if="model?.image"><img :src="model.image" :alt="model.name" class="h-24 w-24 rounded object-cover"></template><p class="font-semibold" x-text="model?.name"></p></div>
                <label x-show="model?.colors.length" class="mt-5 block">Color<select class="form-input" name="color" x-model="color"><option value="">Por definir / necesito asesoría</option><template x-for="option in model?.colors || []" :key="option"><option :value="option" x-text="option"></option></template></select></label>
            </section>
            <section class="rounded border border-neva-line p-5 md:p-7">
                <p class="eyebrow">02 · Tu pedido</p><h2 class="mt-2 text-xl font-semibold">Cantidades y tallas</h2>
                <input type="hidden" name="unknown_sizes" :value="unknown ? 1 : 0">
                <label class="mt-5 flex min-h-11 items-center gap-3"><input type="checkbox" x-model="unknown" :disabled="!model?.sizes.length" class="rounded border-neva-line text-neva-blue">Aún no sé las tallas</label>
                <p x-show="!model?.sizes.length" class="mt-2 text-sm text-neva-muted">Te ayudaremos a confirmar las tallas y medidas de este pedido.</p>
                <label x-show="unknown" class="mt-4 block">Cantidad aproximada (opcional)<input class="form-input" type="number" name="quantity" min="1" max="10000" step="1" inputmode="numeric" x-model="quantity" :disabled="!unknown" placeholder="Ej. 15"><span class="mt-2 block text-sm text-neva-muted">Puedes dejarla por definir.</span></label>
                <div x-show="!unknown" class="mt-4"><p class="mb-4 text-sm text-neva-muted">Indica cuántas prendas necesitas de cada talla. El total se calcula automáticamente.</p><div class="grid grid-cols-2 gap-4 sm:grid-cols-3"><template x-for="size in model?.sizes || []" :key="size"><label class="block"><span x-text="'Talla ' + size"></span><input class="form-input" type="number" :name="'sizes[' + size + ']'" min="0" max="10000" step="1" inputmode="numeric" x-model="sizes[size]" :disabled="unknown" placeholder="0"></label></template></div></div>
            </section>
            <section class="rounded border border-neva-line p-5 md:p-7">
                <p class="eyebrow">03 · Personalización opcional</p><h2 class="mt-2 text-xl font-semibold">Bordado y acabados</h2>
                <label class="mt-5 block">¿Deseas incluir el logotipo de una institución o empresa?<select name="logo" class="form-input" x-model="logo"><option value="no">No</option><option value="yes">Sí</option><option value="advice">Necesito asesoría</option></select></label>
                <div x-show="logo === 'yes'" class="mt-4"><label class="block">Nombre de la institución o empresa (opcional)<input name="institution" class="form-input" maxlength="150" x-model="institution" :disabled="logo !== 'yes'"></label><p class="mt-3 text-sm text-neva-muted">Compartirás el archivo del logotipo directamente con nuestro equipo por WhatsApp.</p></div>
                <label class="mt-5 block">Indicaciones de bordado y acabados (opcional)<textarea name="notes" rows="4" class="form-input" maxlength="700" x-model="notes" placeholder="Ej. Nombre bordado en el pecho, ubicación del logotipo o dudas sobre acabados."></textarea></label>
            </section>
        </div>
        <aside class="rounded border border-neva-line bg-neva-mist p-6 lg:sticky lg:top-6">
            <h2 class="text-xl font-semibold">Tu solicitud</h2>
            <dl class="mt-5 space-y-4 text-sm" aria-live="polite">
                <div><dt class="text-neva-muted">Modelo</dt><dd class="font-semibold" x-text="model?.name || 'Necesito asesoría para elegir'"></dd></div>
                <div><dt class="text-neva-muted">Color</dt><dd x-text="color || 'Por definir'"></dd></div>
                <div><dt class="text-neva-muted">Cantidad aproximada</dt><dd x-text="total ? total + (total === 1 ? ' prenda' : ' prendas') : 'Por definir'"></dd></div>
                <div><dt class="text-neva-muted">Tallas</dt><dd x-text="sizeSummary"></dd></div>
                <div><dt class="text-neva-muted">Logotipo</dt><dd x-text="logo === 'yes' ? 'Sí; lo compartiré por WhatsApp' : (logo === 'advice' ? 'Necesito asesoría' : 'No solicitado')"></dd><dd x-show="logo === 'yes' && institution" class="break-words" x-text="institution"></dd></div>
                <div x-show="notes"><dt class="text-neva-muted">Bordado y acabados</dt><dd class="whitespace-pre-wrap break-words" x-text="notes"></dd></div>
            </dl>
            <button type="submit" class="btn-primary mt-6 w-full justify-center">Continuar en WhatsApp</button>
            <p class="mt-3 text-sm text-neva-muted">Se abrirá tu solicitud preparada. Revisa el mensaje y envíalo cuando estés listo.</p>
            <p class="mt-3 text-xs text-neva-muted">Nuestro equipo confirmará disponibilidad, personalización y precio final contigo.</p>
        </aside>
    </form>
</section>
@endsection
