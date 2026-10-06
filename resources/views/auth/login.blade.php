<x-guest-layout>
    <p class="eyebrow">Administración privada</p>
    <h1 class="mb-6 font-display text-4xl">Bienvenido de nuevo.</h1>
    <x-ui.feedback />
    <form method="post" action="{{ route('login') }}" class="space-y-5">
        @csrf
        <label class="block" for="email">Correo electrónico<input id="email" name="email" type="email" class="form-input" value="{{ old('email') }}" required autofocus autocomplete="username"></label>
        <label class="block" for="password">Contraseña<input id="password" name="password" type="password" class="form-input" required autocomplete="current-password"></label>
        <label class="flex items-center gap-3"><input type="checkbox" name="remember" value="1">Recordarme</label>
        <button class="btn-primary w-full">Iniciar sesión</button>
        <a class="block text-sm text-neva-blue" href="{{ route('password.request') }}">Olvidé mi contraseña</a>
        <a class="btn-secondary w-full" href="{{ route('home') }}">Volver al sitio</a>
        <p class="text-sm text-neva-muted">Acceso para administradores y publicadores de productos.</p>
    </form>
</x-guest-layout>
