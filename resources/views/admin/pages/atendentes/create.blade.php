@extends('adminlte::page')

@section('title', 'Cadastrar atendente')

@section('content_header')
    <h1>Cadastrar atendente</h1>
@stop

@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('atendentes.store') }}" class="form" method="POST">
                @csrf
                @include('admin.pages.atendentes._partials.form')
            </form>
        </div>
    </div>
@endsection
