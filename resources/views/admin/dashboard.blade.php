@extends('layouts.admin')
@section('title', 'Resumen')
@section('content')
<h1 class="text-3xl font-bold">Resumen del catálogo</h1><p class="mt-2 text-neva-muted">Actividad y contenido de Neva Medic.</p>
<div class="my-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
@foreach(['Productos' => $productCount, 'Publicados' => $publishedCount, 'Sectores activos' => $sectorCount, 'Vistas en 30 días' => $views] as $label => $value)
<div class="panel"><p class="text-neva-muted">{{ $label }}</p><p class="mt-3 text-4xl font-bold">{{ $value }}</p></div>
@endforeach
</div>
<div class="mb-6 flex flex-wrap items-center justify-between gap-4"><h2 class="text-xl font-semibold">Productos recientes</h2><a class="btn-primary" href="{{ route('admin.products.create') }}">Nuevo producto</a></div>
@include('admin.products.partials.list')
@endsection
