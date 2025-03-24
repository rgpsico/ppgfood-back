@extends('adminlte::page')

@section('title', 'Configurações Globais')

@section('content_header')
    <h1>Configurações Globais do Sistema</h1>
@stop

@section('content')
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card">
        <div class="card-body">
            <form method="GET" class="form-inline mb-3">
                <input type="text" name="filtro" value="{{ request('filtro') }}" class="form-control mr-2" placeholder="Buscar por chave...">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="{{ route('empresa.config.create') }}" class="btn btn-success ml-2">Nova Configuração</a>
            </form>

            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Chave</th>
                        <th>Tipo</th>
                        <th>Valor Padrão</th>
                        <th>Descrição</th>
                        <th width="150">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($configuracoes as $config)
                        <tr id="row-{{ $config->id }}">
                            <td>{{ $config->chave }}</td>
                            <td>{{ $config->tipo }}</td>
                            <td>{{ $config->valor_padrao }}</td>
                            <td>{{ $config->descricao }}</td>
                            <td>
                                <button class="btn btn-sm btn-info btn-edit" 
                                    data-id="{{ $config->id }}"
                                    data-chave="{{ $config->chave }}"
                                    data-tipo="{{ $config->tipo }}"
                                    data-valor="{{ $config->valor_padrao }}"
                                    data-descricao="{{ $config->descricao }}"
                                >Editar</button>

                                <button class="btn btn-sm btn-danger btn-delete" data-id="{{ $config->id }}">Excluir</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{ $configuracoes->links() }}
        </div>
    </div>

    <!-- Modal de edição -->
    <div class="modal fade" id="modalEditarConfig" tabindex="-1" role="dialog" aria-labelledby="editarModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form method="POST" id="formEditConfig" action="">
                @csrf
                @method('PUT')
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Editar Configuração</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

                    <div class="modal-body">
                        <input type="hidden" name="id" id="edit-id">
                        
                        <div class="form-group">
                            <label>Chave</label>
                            <input type="text" class="form-control" name="chave" id="edit-chave" readonly>
                        </div>

                        <div class="form-group">
                            <label>Tipo</label>
                            <input type="text" class="form-control" name="tipo" id="edit-tipo" readonly>
                        </div>

                        <div class="form-group">
                            <label>Valor Padrão</label>
                            <input type="text" class="form-control" name="valor_padrao" id="edit-valor">
                        </div>

                        <div class="form-group">
                            <label>Descrição</label>
                            <textarea class="form-control" name="descricao" id="edit-descricao"></textarea>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Salvar</button>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@stop

@section('js')
<script>
    // Ação do botão de editar
    $('.btn-edit').click(function () {
        let btn = $(this);

        $('#edit-id').val(btn.data('id'));
        $('#edit-chave').val(btn.data('chave'));
        $('#edit-tipo').val(btn.data('tipo'));
        $('#edit-valor').val(btn.data('valor'));
        $('#edit-descricao').val(btn.data('descricao'));

        $('#formEditConfig').attr('action', `/admin/empresa/config/update/${btn.data('id')}`);
        $('#modalEditarConfig').modal('show');
    });

    // Exclusão com AJAX
    $('.btn-delete').click(function () {
        if (!confirm('Tem certeza que deseja excluir esta configuração?')) return;

        let id = $(this).data('id');

        $.ajax({
            url: `/admin/empresa/config/destroy/${id}`,
            type: 'DELETE',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function () {
                $(`#row-${id}`).remove();
                alert('Configuração excluída com sucesso!');
            },
            error: function () {
                alert('Erro ao tentar excluir.');
            }
        });
    });
</script>
@stop
