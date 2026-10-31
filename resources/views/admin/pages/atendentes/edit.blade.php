@extends('adminlte::page')

@section('title', 'Editar atendente')

@section('content_header')
    <h1>Editar atendente</h1>
@stop

@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('atendentes.update', $atendente->id) }}" class="form" method="POST">
                @csrf
                @method('PUT')
                @include('admin.pages.atendentes._partials.form')
            </form>
        </div>
    </div>
@endsection
