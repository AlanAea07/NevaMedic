@extends('layouts.admin')
@section('title', 'Actividad')
@section('content')
<h1 class="mb-8 text-3xl font-bold">Actividad administrativa</h1>
<div class="space-y-4">
@forelse($entries as $entry)
    <article class="panel">
        <a class="block focus-visible:outline focus-visible:outline-2 focus-visible:outline-neva-blue" href="{{ route('admin.activity.show', $entry) }}">
            <p class="font-semibold">{{ $entry->actor['name'] ?? $entry->user?->name ?? 'Cuenta no disponible' }} · {{ $entry->action_label }}</p>
            <p class="mt-2 break-words">{{ $entry->after['name'] ?? $entry->before['name'] ?? $entry->metadata['route'] ?? '' }}</p>
            <p class="mt-2 text-sm text-neva-muted">{{ $entry->entity_type }} #{{ $entry->entity_id }} · {{ $entry->created_at->copy()->timezone('America/Mexico_City')->format('d/m/Y H:i:s P') }}</p>
            <span class="mt-3 inline-block text-sm font-semibold text-neva-blue">Ver detalle →</span>
        </a>
    </article>
@empty
    <x-ui.empty-state title="No hay movimientos" message="Las acciones de las cuentas de la plataforma quedarán registradas aquí." />
@endforelse
</div>
<div class="mt-8">{{ $entries->links() }}</div>
@endsection
