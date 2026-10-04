<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Neva Medic') · Neva Medic</title>
    <meta name="description" content="@yield('description', 'Uniformes clínicos, médicos e industriales. Atención y envíos nacionales.')">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Libre+Caslon+Display&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-white font-sans text-neva-ink antialiased">
    <a href="#contenido" class="sr-only focus:not-sr-only focus:block focus:p-4">Saltar al contenido</a>
    @include('site.partials.navigation')
    <main id="contenido">@yield('content')</main>
    @include('site.partials.footer')
</body>
</html>
