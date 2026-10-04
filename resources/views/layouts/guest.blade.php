<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Acceso administrativo · Neva Medic</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Libre+Caslon+Display&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-neva-mist font-sans text-neva-ink">
<div class="min-h-screen lg:grid lg:grid-cols-2">
    <div class="flex flex-col items-center justify-center bg-neva-ink px-6 py-8 text-center text-white lg:p-16">
        <a href="{{ route('home') }}"><img src="{{ asset('images/brand/emblem.png') }}" class="h-28 w-32 object-contain lg:h-72 lg:w-80" alt="Neva Medic"></a>
        <p class="mt-6 font-display text-3xl lg:text-5xl">{{ config('neva.tagline') }}</p>
    </div>
    <main class="flex items-center justify-center px-5 py-10"><div class="w-full max-w-md rounded bg-white p-6 sm:p-8">{{ $slot }}</div></main>
</div>
</body>
</html>
