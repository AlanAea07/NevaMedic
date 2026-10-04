@props(['title' => 'Todavía no hay contenido', 'message' => 'La información aparecerá aquí cuando esté disponible.'])
<div class="rounded border border-dashed border-neva-line bg-neva-mist p-8 text-center">
    <h2 class="text-lg font-semibold">{{ $title }}</h2><p class="mt-2 text-neva-muted">{{ $message }}</p>
    {{ $slot }}
</div>
