@extends('layouts.admin')
@section('title', 'Cuentas')
@section('content')
<h1 class="text-3xl font-bold">Cuentas</h1>
<p class="mb-8 mt-3 text-neva-muted">Las cuentas no se eliminan. Desactivarlas bloquea su acceso y conserva su información.</p>
<div class="space-y-4">
@foreach($accounts as $account)
<article class="panel flex flex-wrap items-center justify-between gap-4">
    <div><h2 class="font-semibold">{{ $account->name }}</h2><p>{{ $account->email }}</p><p class="mt-2 text-sm">{{ $account->isAdministrator() ? 'Administrador' : 'Publicador de productos' }} · {{ $account->is_active ? 'Activa' : 'Desactivada' }}</p></div>
    @if($account->is(auth()->user()))<span class="text-sm text-neva-muted">Tu cuenta</span>
    @else<form method="post" action="{{ route('admin.accounts.update', $account) }}">@csrf @method('PATCH')<input type="hidden" name="is_active" value="{{ $account->is_active ? '0' : '1' }}"><button class="btn-secondary">{{ $account->is_active ? 'Desactivar cuenta' : 'Reactivar cuenta' }}</button></form>@endif
</article>
@endforeach
</div><div class="mt-6">{{ $accounts->links() }}</div>
@endsection
