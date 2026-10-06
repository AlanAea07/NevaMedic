@extends('layouts.admin')
@section('title', 'Actividad')
@section('content')
<h1 class="mb-8 text-3xl font-bold">Actividad administrativa</h1>
<div class="space-y-4">@forelse($entries as $entry)<article class="panel"><p class="font-semibold">{{ $entry->user?->name ?? 'Cuenta no disponible' }} · {{ ['created' => 'Creación', 'updated' => 'Actualización', 'deleted' => 'Archivado', 'activated' => 'Activación de cuenta', 'deactivated' => 'Desactivación de cuenta'][$entry->action] ?? $entry->action }}</p><p class="mt-2 text-sm text-neva-muted">{{ $entry->entity_type }} #{{ $entry->entity_id }} · {{ $entry->created_at->format('d/m/Y H:i') }}</p></article>@empty<x-ui.empty-state title="No hay movimientos" message="Los cambios de productos y cuentas quedarán registrados aquí." />@endforelse</div>
<div class="mt-8">{{ $entries->links() }}</div>
@endsection
