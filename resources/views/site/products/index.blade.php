@extends('layouts.site')
@section('title', 'Catálogo')
@section('content')
<div class="page-shell py-12">
    <p class="eyebrow">Colecciones</p><h1 class="section-title">Encuentra tu próximo uniforme.</h1>
    <form method="get" class="my-8 grid items-end gap-4 rounded bg-neva-mist p-6 md:grid-cols-2 xl:grid-cols-4">
        <label>Buscar<input class="form-input" name="q" value="{{ request('q') }}" maxlength="100" placeholder="Nombre de la prenda"></label>
        <label>Tipo de producto<select class="form-input" name="type"><option value="">Todos</option>@foreach(config('neva.product_types') as $value => $label)<option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>@endforeach</select></label>
        <label>Sector<select class="form-input" name="sector"><option value="">Todos</option>@foreach($sectors as $sector)<option value="{{ $sector->slug }}" @selected(request('sector') === $sector->slug)>{{ $sector->name }}</option>@endforeach</select></label>
        <button class="btn-primary">Filtrar</button>
    </form>
    @php
        $activeFilters = array_filter(request()->only(['q', 'type', 'sector']), fn ($value) => is_string($value) && $value !== '');
        $filterLabels = [
            'q' => 'Búsqueda: '.($activeFilters['q'] ?? ''),
            'type' => 'Tipo: '.config('neva.product_types.'.($activeFilters['type'] ?? ''), ''),
            'sector' => 'Sector: '.($sectors->firstWhere('slug', $activeFilters['sector'] ?? '')?->name ?? 'No disponible'),
        ];
    @endphp
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-neva-muted"><strong class="text-neva-ink">{{ $products->total() }}</strong> {{ $products->total() === 1 ? 'producto encontrado' : 'productos encontrados' }}</p>
        @if($activeFilters)
            <a href="{{ route('products.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-neva-blue underline underline-offset-4">Limpiar filtros</a>
        @endif
    </div>
    @if($activeFilters)
        <nav class="mb-6 flex flex-wrap gap-2" aria-label="Filtros activos">
            @foreach($activeFilters as $key => $value)
                <a href="{{ route('products.index', Illuminate\Support\Arr::except($activeFilters, [$key])) }}" class="inline-flex min-h-11 max-w-full items-center gap-3 rounded border border-neva-line bg-neva-mist px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-neva-blue" aria-label="Quitar filtro {{ $filterLabels[$key] }}"><span class="break-words">{{ $filterLabels[$key] }}</span><span aria-hidden="true">×</span></a>
            @endforeach
        </nav>
    @endif
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">@forelse($products as $product)<x-catalog.product-card :product="$product" />@empty<div class="col-span-full"><x-ui.empty-state title="No encontramos productos" message="Prueba otros filtros o vuelve pronto para conocer las novedades." /></div>@endforelse</div>
    <div class="mt-8">{{ $products->links() }}</div>
</div>
@endsection
