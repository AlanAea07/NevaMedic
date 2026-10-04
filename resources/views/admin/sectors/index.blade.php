@extends('layouts.admin')
@section('title', 'Sectores')
@section('content')
<div class="mb-8 flex flex-wrap justify-between gap-4"><h1 class="text-3xl font-bold">Sectores</h1><a class="btn-primary" href="{{ route('admin.sectors.create') }}">Nuevo sector</a></div>
<div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">@forelse($sectors as $sector)<article class="panel"><h2 class="font-display text-3xl">{{ $sector->name }}</h2><p class="mt-3 text-neva-muted">{{ $sector->description }}</p><p class="my-4">{{ $sector->products_count }} productos · {{ $sector->is_active ? 'Activo' : 'Inactivo' }}</p><a class="btn-secondary" href="{{ route('admin.sectors.edit', $sector) }}">Editar</a></article>@empty<x-ui.empty-state title="No hay sectores" />@endforelse</div>
@endsection
