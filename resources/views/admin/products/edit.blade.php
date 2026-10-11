@extends('layouts.admin')
@section('title', 'Editar producto')
@section('content')
<div class="mb-8 flex flex-wrap items-center justify-between gap-4"><h1 class="text-3xl font-bold">Editar producto</h1><a class="btn-secondary" href="{{ route('admin.products.preview', $product) }}">Vista previa</a></div>
<form method="post" enctype="multipart/form-data" action="{{ route('admin.products.update', $product) }}">@csrf @method('PUT') @include('admin.products.partials.form')</form>
<form class="mt-8" method="post" action="{{ route('admin.products.destroy', $product) }}" x-data @submit="if (!confirm('¿Enviar este producto a la papelera? Dejará de aparecer en el catálogo y podrás restaurarlo durante 14 días. Después se eliminará definitivamente.')) $event.preventDefault()">
    @csrf @method('DELETE')<button class="rounded border border-red-300 px-5 py-3 text-red-700">Enviar a papelera</button>
</form>
@endsection
