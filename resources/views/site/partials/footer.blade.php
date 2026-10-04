<footer class="mt-16 bg-neva-ink text-white">
    <div class="page-shell grid gap-8 py-12 md:grid-cols-2">
        <div><p class="font-display text-3xl">NEVA MEDIC</p><p class="mt-3 text-neva-silver">{{ config('neva.tagline') }}</p><p class="mt-2 text-sm text-neva-silver">{{ config('neva.location') }} · Envíos nacionales</p></div>
        <div class="flex flex-wrap items-start gap-6 text-sm"><a href="{{ route('products.index') }}">Catálogo</a><a href="{{ route('contact') }}">Contacto</a><a href="{{ route('privacy') }}">Privacidad</a><a href="{{ route('login') }}">Administración</a></div>
    </div>
</footer>
