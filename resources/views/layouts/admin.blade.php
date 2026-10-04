<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Administración') · Neva Medic</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Libre+Caslon+Display&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-neva-mist font-sans text-neva-ink antialiased" x-data="{ menuOpen: false }">
    <a href="#admin-content" class="sr-only focus:not-sr-only focus:block focus:p-4">Saltar al contenido</a>
    <header class="flex items-center justify-between bg-neva-ink p-4 text-white lg:hidden"><a href="{{ route('admin.dashboard') }}" class="font-display text-2xl">NEVA MEDIC</a><button @click="menuOpen = !menuOpen" :aria-expanded="menuOpen" aria-controls="admin-menu" class="min-h-11 px-4">Menú</button></header>
    <div class="lg:flex">
        <aside id="admin-menu" class="hidden bg-neva-ink p-6 text-white lg:sticky lg:top-0 lg:block lg:h-screen lg:w-64 lg:shrink-0" :class="{ '!block': menuOpen }">
            <a href="{{ route('admin.dashboard') }}" class="font-display text-3xl">NEVA MEDIC</a><p class="mt-2 text-xs tracking-widest text-neva-silver">ADMINISTRACIÓN</p>
            <nav class="mt-8 flex flex-col gap-2" aria-label="Administración">
                @foreach(['admin.dashboard' => 'Resumen', 'admin.products.index' => 'Productos', 'admin.sectors.index' => 'Sectores', 'admin.statistics.index' => 'Estadísticas', 'admin.activity.index' => 'Actividad', 'admin.accounts.index' => 'Cuentas', 'profile.edit' => 'Mi cuenta'] as $route => $label)
                    <a href="{{ route($route) }}" class="rounded px-3 py-3 hover:bg-neva-navy {{ request()->routeIs(str_replace('.index', '.*', $route)) ? 'bg-neva-navy' : '' }}">{{ $label }}</a>
                @endforeach
                <a class="px-3 py-3" href="{{ route('home') }}">Ver sitio</a>
                <form method="post" action="{{ route('logout') }}">@csrf<button class="w-full px-3 py-3 text-left">Cerrar sesión</button></form>
            </nav>
        </aside>
        <main id="admin-content" class="min-w-0 flex-1 p-5 md:p-10">
            <div class="mx-auto max-w-6xl"><x-ui.feedback />@yield('content')</div>
        </main>
    </div>
</body>
</html>
