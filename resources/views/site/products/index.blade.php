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
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">@forelse($products as $product)<x-catalog.product-card :product="$product" />@empty<div class="col-span-full"><x-ui.empty-state title="No encontramos productos" message="Prueba otros filtros o vuelve pronto para conocer las novedades." /></div>@endforelse</div>
    <div class="mt-8">{{ $products->links() }}</div>
</div>
@endsection
