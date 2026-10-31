@extends('adminlte::page')

@section('title', 'Atendentes')

@section('content_header')
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">Dashboard</a></li>
        <li class="breadcrumb-item active">Atendentes</li>
    </ol>

    <h1>Atendentes <a href="{{ route('atendentes.create') }}" class="btn btn-dark">ADD</a></h1>
@stop

@section('content')
    @include('admin.includes.alerts')

    @unless ($habilitado)
        <div class="alert alert-info">
            O atendimento por funcionário está <strong>desligado</strong> para esta empresa: o cliente não vê a escolha de atendente.
            Para ligar, vá em <a href="{{ route('admin.config.index') }}">Minhas Configurações</a> e ative
            “Atendimento por funcionário: o cliente escolhe quem está atendendo”.
        </div>
    @endunless

    <div class="card">
        <div class="card-header">
            <form action="{{ route('atendentes.index') }}" method="GET" class="form form-inline">
                <label class="mr-2">Período:</label>
                <input type="date" name="de" class="form-control mr-2" value="{{ $de }}">
                <input type="date" name="ate" class="form-control mr-2" value="{{ $ate }}">
                <button type="submit" class="btn btn-dark">Filtrar</button>
            </form>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-condensed">
                <thead>
                    <tr>
                        <th>Atendente</th>
                        <th>Situação</th>
                        <th>Login</th>
                        <th class="text-right">Pedidos</th>
                        <th class="text-right">Concluídos</th>
                        <th class="text-right">Vendido (concluídos)</th>
                        <th class="text-right">Pendentes</th>
                        <th class="text-right">Cancelados</th>
                        <th width="230">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($linhas as $l)
                        <tr>
                            <td>{{ $l->atendente->nome }}</td>
                            <td>
                                @if (!$l->atendente->ativo)
                                    <span class="badge badge-secondary">Inativo</span>
                                @elseif ($l->atendendo_agora)
                                    <span class="badge badge-success">Atendendo agora</span>
                                @else
                                    <span class="badge badge-light">Livre</span>
                                @endif
                            </td>
                            <td>{{ $l->atendente->user_id ? 'Sim' : 'Não' }}</td>
                            <td class="text-right">{{ $l->pedidos }}</td>
                            <td class="text-right">{{ $l->concluidos }}</td>
                            <td class="text-right">R$ {{ number_format($l->vendido, 2, ',', '.') }}</td>
                            <td class="text-right">{{ $l->pendentes }} <small class="text-muted">(R$ {{ number_format($l->valor_pendente, 2, ',', '.') }})</small></td>
                            <td class="text-right">{{ $l->cancelados }} <small class="text-muted">(R$ {{ number_format($l->valor_cancelado, 2, ',', '.') }})</small></td>
                            <td>
                                <a href="{{ route('atendentes.show', ['atendente' => $l->atendente->id, 'de' => $de, 'ate' => $ate]) }}" class="btn btn-warning btn-sm">Pedidos</a>
                                <a href="{{ route('atendentes.edit', $l->atendente->id) }}" class="btn btn-info btn-sm">Editar</a>
                                <form action="{{ route('atendentes.toggle', $l->atendente->id) }}" method="POST" style="display:inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-sm {{ $l->atendente->ativo ? 'btn-outline-danger' : 'btn-outline-success' }}">
                                        {{ $l->atendente->ativo ? 'Desativar' : 'Ativar' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted">Nenhum atendente cadastrado ainda.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <p class="text-muted mb-0"><small>Só pedidos concluídos entram no valor vendido; pendentes e cancelados aparecem separados.</small></p>
        </div>
    </div>
@stop
