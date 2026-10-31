@extends('adminlte::page')

@section('title', 'Pedidos do atendente')

@section('content_header')
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('admin.index') }}">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="{{ route('atendentes.index') }}">Atendentes</a></li>
        <li class="breadcrumb-item active">{{ $atendente->nome }}</li>
    </ol>

    <h1>Pedidos de {{ $atendente->nome }}</h1>
@stop

@section('content')
    @include('admin.includes.alerts')

    <div class="card">
        <div class="card-header">
            <form action="{{ route('atendentes.show', $atendente->id) }}" method="GET" class="form form-inline">
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
                        <th>Pedido</th>
                        <th>Quando</th>
                        <th>Local</th>
                        <th>Itens</th>
                        <th class="text-right">Total</th>
                        <th>Status</th>
                        <th>Corrigir atendente</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pedidos as $p)
                        <tr>
                            <td>{{ $p->identify }}</td>
                            <td>{{ $p->created_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $p->table ? $p->table->identify : '—' }}</td>
                            <td>{{ $p->products->map(fn ($i) => $i->pivot->qty . 'x ' . $i->title)->implode(', ') }}</td>
                            <td class="text-right">R$ {{ number_format($p->total, 2, ',', '.') }}</td>
                            <td>{{ $statusOptions[$p->status] ?? $p->status }}</td>
                            <td>
                                <form action="{{ route('atendentes.reatribuir', $p->id) }}" method="POST" class="form-inline">
                                    @csrf
                                    @method('PATCH')
                                    <select name="attendant_id" class="form-control form-control-sm mr-1">
                                        <option value="">Sem atendente</option>
                                        @foreach ($atendentes as $a)
                                            <option value="{{ $a->id }}" {{ $a->id === $p->attendant_id ? 'selected' : '' }}>{{ $a->nome }}{{ $a->ativo ? '' : ' (inativo)' }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-dark">Salvar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted">Nenhum pedido neste período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{!! $pedidos->links() !!}</div>
    </div>
@stop
