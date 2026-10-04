@extends('layouts.admin')
@section('title', 'Nuevo producto')
@section('content')
<h1 class="mb-8 text-3xl font-bold">Nuevo producto</h1>
<form method="post" enctype="multipart/form-data" action="{{ route('admin.products.store') }}">@csrf @include('admin.products.partials.form')</form>
@endsection
