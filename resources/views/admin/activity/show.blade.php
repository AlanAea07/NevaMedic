@extends('layouts.admin')
@section('title', 'Detalle de actividad')
@section('content')
<div class="mb-8 flex flex-wrap items-center justify-between gap-4"><h1 class="text-3xl font-bold">{{ $entry->action_label }} · Registro #{{ $entry->id }}</h1><a href="{{ route('admin.activity.index') }}" class="btn-secondary">Volver al historial</a></div>
<dl class="panel grid gap-5 sm:grid-cols-2">
    <div><dt class="text-sm text-neva-muted">Quién</dt><dd class="mt-1 font-semibold">{{ $entry->actor['name'] ?? $entry->user?->name ?? 'Cuenta no disponible' }}</dd><dd class="text-sm">ID: {{ $entry->actor['id'] ?? $entry->user_id ?? '—' }} · Rol: {{ $entry->actor['role'] ?? 'No registrado' }}</dd></div>
    <div><dt class="text-sm text-neva-muted">Cuándo (Ciudad de México)</dt><dd class="mt-1">{{ $entry->created_at->copy()->timezone('America/Mexico_City')->format('d/m/Y H:i:s P') }}</dd><dd class="text-sm text-neva-muted">UTC: {{ $entry->created_at->copy()->utc()->toIso8601String() }}</dd></div>
    <div><dt class="text-sm text-neva-muted">Qué</dt><dd class="mt-1">{{ $entry->action_label }} · {{ $entry->entity_type }} #{{ $entry->entity_id }}</dd><dd class="break-words font-semibold">{{ $entry->after['name'] ?? $entry->before['name'] ?? '' }}</dd></div>
    <div><dt class="text-sm text-neva-muted">Estado registrado</dt><dd class="mt-1">@if($entry->entity_type === 'product'){{ $entry->action === 'purged' ? 'Eliminado definitivamente' : (!empty($entry->after['deleted_at']) ? 'En papelera' : (isset($entry->after['is_available']) ? ($entry->after['is_available'] ? 'Disponible' : 'No disponible') : 'No registrado')) }}@else{{ $entry->metadata['route'] ?? '—' }}@endif</dd></div>
</dl>
@if(!$entry->actor)
    <p class="mt-6 text-sm text-neva-muted">Registro anterior a la ampliación del historial: no se guardaron copias detalladas de los datos ni del rol.</p>
@endif
<div class="mt-6 grid min-w-0 gap-6 lg:grid-cols-2">
@foreach(['before' => 'Datos anteriores', 'after' => 'Datos posteriores'] as $field => $label)
    <section class="panel min-w-0"><h2 class="text-lg font-semibold">{{ $label }}</h2>
    @if($entry->$field)
        <dl class="mt-4 space-y-3">
        @foreach($entry->$field as $key => $value)
            <div><dt class="break-words text-sm font-semibold">{{ ['name' => 'Nombre', 'slug' => 'Identificador del enlace', 'reference_price' => 'Precio de referencia', 'images' => 'Imágenes / archivos', 'variants' => 'Colores y variantes', 'sectors' => 'Sectores', 'deleted_at' => 'Fecha de eliminación', 'purge_at' => 'Límite de recuperación', 'is_available' => 'Disponible', 'available_sizes' => 'Tallas'][$key] ?? $key }}</dt>
            <dd class="mt-1 whitespace-pre-wrap break-words text-sm text-neva-muted">{{ is_array($value) ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (is_bool($value) ? ($value ? 'Sí' : 'No') : ($value ?? '—')) }}</dd></div>
        @endforeach
        </dl>
    @else<p class="mt-4 text-sm text-neva-muted">Sin datos para este estado.</p>@endif
    </section>
@endforeach
</div>
@if($entry->metadata)
<section class="panel mt-6"><h2 class="text-lg font-semibold">Metadatos y archivos subidos</h2><pre class="mt-4 whitespace-pre-wrap break-words text-sm">{{ json_encode($entry->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre></section>
@endif
@endsection
