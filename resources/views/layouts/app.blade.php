@extends('layouts.admin')
@section('title', 'Mi cuenta')
@section('content')
    @isset($header)<div class="mb-8">{{ $header }}</div>@endisset
    {{ $slot }}
@endsection
