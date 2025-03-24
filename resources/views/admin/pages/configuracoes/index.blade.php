@extends('adminlte::page')

@section('title', 'Configurações da Empresa')

@section('content_header')
    <h1>Minhas Configurações</h1>
@stop

@section('content')
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form action="{{ route('admin.config.update') }}" method="POST">
        @csrf
        @method('PUT')

        <div class="card">
            <div class="card-header">
                <h3>Configurações Disponíveis</h3>
            </div>
            <div class="card-body">
                @foreach ($configuracoes_modelo as $modelo)
                    @php
                        $valor = $configuracoes_empresa[$modelo->chave] ?? $modelo->valor_padrao;
                    @endphp

                    <div class="form-group">
                        <label><strong>{{ $modelo->descricao ?? $modelo->chave }}</strong></label>

                        @if ($modelo->tipo === 'boolean')
                            <select name="config[{{ $modelo->chave }}]" class="form-control">
                                <option value="1" {{ $valor == '1' ? 'selected' : '' }}>Sim</option>
                                <option value="0" {{ $valor == '0' ? 'selected' : '' }}>Não</option>
                            </select>
                        @elseif ($modelo->tipo === 'select')
                            @php
                                $opcoes = explode(',', $modelo->valor_padrao); // lista das opções
                            @endphp
                            <select name="config[{{ $modelo->chave }}]" class="form-control">
                                @foreach ($opcoes as $opcao)
                                    <option value="{{ trim($opcao) }}" {{ trim($valor) == trim($opcao) ? 'selected' : '' }}>
                                        {{ ucfirst(trim($opcao)) }}
                                    </option>
                                @endforeach
                            </select>
                        @elseif ($modelo->tipo === 'json')
                            <textarea name="config[{{ $modelo->chave }}]" class="form-control" rows="3">{{ $valor }}</textarea>
                        @else
                            <input type="text" name="config[{{ $modelo->chave }}]" class="form-control" value="{{ $valor }}">
                        @endif
                    </div>
                @endforeach

                <button type="submit" class="btn btn-primary">Salvar Configurações</button>
            </div>
        </div>
    </form>
@stop
