@extends('layouts.site')
@section('title', 'Personalización')
@section('content')
<section class="page-shell py-16"><p class="eyebrow">Proyectos institucionales</p><h1 class="section-title">Una identidad que se viste.</h1><p class="mt-6 max-w-2xl text-xl text-neva-muted">Consulta modelos, telas, colores, tallas y acabados para tu institución.</p><ol class="my-10 grid gap-6 md:grid-cols-3">@foreach(['Selecciona el modelo', 'Consulta colores y tallas', 'Define bordado y acabados'] as $step)<li class="rounded bg-neva-mist p-8"><span class="eyebrow">0{{ $loop->iteration }}</span><h2 class="mt-4 text-xl">{{ $step }}</h2></li>@endforeach</ol><a href="{{ route('contact') }}" class="btn-primary">Solicitar una cotización</a></section>
@endsection
