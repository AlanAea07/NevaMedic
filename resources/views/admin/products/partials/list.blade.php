<div class="space-y-4">
@forelse($products as $product)
<article class="panel flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
    <div class="h-24 w-24 shrink-0 overflow-hidden rounded bg-neva-mist">
        @if($image = $product->images->first())<img src="{{ $image->url }}" alt="{{ $product->name }}" class="h-full w-full object-cover" loading="lazy" referrerpolicy="no-referrer">@else<span class="flex h-full items-center justify-center text-center text-xs text-neva-muted">Sin imagen</span>@endif
    </div>
    <div class="min-w-0"><h2 class="text-lg font-semibold">{{ $product->name }}</h2><p class="mt-1 text-sm text-neva-muted">{{ $product->sectors->pluck('name')->join(' · ') }}</p><p class="mt-2 text-sm">{{ $product->is_available ? 'Visible en el catálogo' : 'Oculto del catálogo' }}</p></div>
    <div class="flex shrink-0 flex-wrap items-center gap-4"><p class="font-semibold">${{ number_format((float) $product->reference_price, 2) }} {{ $product->currency }}</p><a class="btn-secondary" href="{{ route('admin.products.edit', $product) }}">Editar</a></div>
</article>
@empty
<x-ui.empty-state title="Aún no hay productos" message="Crea una prenda y asígnale sus sectores para comenzar." />
@endforelse
</div>
