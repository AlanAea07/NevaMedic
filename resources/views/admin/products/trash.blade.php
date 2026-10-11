@extends('layouts.admin')
@section('title', 'Papelera de productos')
@section('content')
<div class="flex flex-wrap items-center justify-between gap-4"><h1 class="text-3xl font-bold">Papelera de productos</h1><a href="{{ route('admin.products.index') }}" class="btn-secondary">Volver a productos</a></div>
<p class="mt-4 text-sm text-neva-muted">Los productos se conservan durante 14 días junto con sus imágenes y colores. Al restaurarlos recuperan su disponibilidad anterior. Después del plazo se eliminan definitivamente de forma automática.</p>
<form class="my-8 flex flex-wrap gap-3" method="get"><label class="flex-1"><span class="sr-only">Buscar en papelera</span><input class="form-input mt-0" name="q" value="{{ request('q') }}" maxlength="100" placeholder="Buscar por nombre"></label><button class="btn-secondary">Buscar</button></form>
<div class="space-y-4">
@forelse($products as $product)
    <article class="panel flex flex-wrap items-center justify-between gap-4">
        <div class="flex min-w-0 items-center gap-4">
            @if($image = $product->cover_image)<img src="{{ $image->url }}" alt="{{ $product->name }}" class="h-24 w-24 rounded object-cover" loading="lazy">@endif
            <div class="min-w-0"><h2 class="break-words text-lg font-semibold">{{ $product->name }}</h2>
                <p class="mt-2 text-sm text-neva-muted">Eliminado: {{ $product->deleted_at->copy()->timezone('America/Mexico_City')->format('d/m/Y H:i:s') }}</p>
                <p class="text-sm text-neva-muted">Límite de recuperación: {{ $product->purge_at->timezone('America/Mexico_City')->format('d/m/Y H:i:s') }} (Ciudad de México)</p>
            </div>
        </div>
        @if($product->purge_at->isFuture())
            <form method="post" action="{{ route('admin.products.restore', $product->id) }}">@csrf<button class="btn-primary">Restaurar producto</button></form>
        @else
            <p class="text-sm text-neva-muted">Plazo vencido · eliminación pendiente</p>
        @endif
    </article>
@empty
    <x-ui.empty-state title="La papelera está vacía" message="Los productos eliminados aparecerán aquí durante su plazo de recuperación." />
@endforelse
</div>
<div class="mt-8">{{ $products->links() }}</div>
@endsection
