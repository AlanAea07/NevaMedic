@if(session('status'))
    <div role="status" class="mb-6 rounded border border-green-200 bg-green-50 p-4 text-green-900">{{ session('status') }}</div>
@endif
@if($errors->any())
    <div role="alert" class="mb-6 rounded border border-red-200 bg-red-50 p-4 text-red-900">
        <p class="font-semibold">Revisa los datos del formulario.</p>
        <ul class="mt-2 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif
