@extends('layouts.admin')
@section('title', 'Productos')
@section('content')
<div class="flex flex-wrap items-center justify-between gap-4"><h1 class="text-3xl font-bold">Productos</h1><div class="flex flex-wrap gap-3"><a href="{{ route('admin.products.trash') }}" class="btn-secondary">Papelera</a><a href="{{ route('admin.products.create') }}" class="btn-primary">Nuevo producto</a></div></div>
<form class="my-8 flex flex-col gap-3 sm:flex-row" method="get"><label class="flex-1"><span class="sr-only">Buscar producto</span><input name="q" class="form-input mt-0" placeholder="Buscar por nombre" value="{{ request('q') }}" maxlength="100"></label><button class="btn-secondary">Buscar</button></form>
@include('admin.products.partials.list')
<div class="mt-8">{{ $products->links() }}</div>
@endsection
