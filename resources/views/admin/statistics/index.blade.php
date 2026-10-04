@extends('layouts.admin')
@section('title', 'Estadísticas')
@section('content')
<h1 class="text-3xl font-bold">Estadísticas</h1><p class="mb-8 mt-3 text-neva-muted">Conteos agregados por producto y día, últimos 30 días. La captura de eventos se conectará en la etapa de analítica.</p>
<div class="space-y-4">@forelse($metrics as $metric)<article class="panel"><h2 class="font-semibold">{{ $metric->product?->name ?? 'Producto archivado' }}</h2><p>{{ $metric->date->format('d/m/Y') }} · {{ $metric->views }} vistas · {{ $metric->whatsapp_clicks }} clics en WhatsApp</p></article>@empty<x-ui.empty-state title="Sin estadísticas registradas" message="Aquí aparecerán los conteos cuando se conecte la captura de eventos." />@endforelse</div>
<div class="mt-8">{{ $metrics->links() }}</div>
@endsection
