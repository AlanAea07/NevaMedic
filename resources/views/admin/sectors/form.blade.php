@extends('layouts.admin')
@section('title', $sector->exists ? 'Editar sector' : 'Nuevo sector')
@section('content')
<h1 class="mb-8 text-3xl font-bold">{{ $sector->exists ? 'Editar sector' : 'Nuevo sector' }}</h1>
<form class="panel max-w-2xl space-y-6" method="post" action="{{ $sector->exists ? route('admin.sectors.update', $sector) : route('admin.sectors.store') }}">
@csrf @if($sector->exists) @method('PUT') @endif
<label class="block">Nombre<input name="name" class="form-input" maxlength="100" value="{{ old('name', $sector->name) }}" required></label>
<label class="block">URL (minúsculas y guiones)<input name="slug" class="form-input" maxlength="120" value="{{ old('slug', $sector->slug) }}" required></label>
<label class="block">Descripción<textarea name="description" class="form-input" rows="4" maxlength="2000">{{ old('description', $sector->description) }}</textarea></label>
<input type="hidden" name="is_active" value="0"><label class="flex items-center gap-3"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $sector->is_active))>Activo</label>
<div class="flex flex-wrap gap-3"><button class="btn-primary">Guardar sector</button><a class="btn-secondary" href="{{ route('admin.sectors.index') }}">Cancelar</a></div>
</form>
@endsection
