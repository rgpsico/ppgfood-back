@extends('adminlte::page')

@section('title', 'Nova Configuração')

@section('content_header')
    <h1>Criar Nova Configuração</h1>
@stop

@section('content')

@if ($errors->any())
    <div class="alert alert-danger">
        <strong>Erro ao salvar a configuração:</strong>
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.config.store') }}" method="POST">
                @csrf

                <div class="form-group">
                    <label for="chave">Chave da Configuração <small>(ex: pagamento_pix)</small></label>
                    <input type="text" name="chave" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="tipo">Tipo de Configuração</label>
                    <select name="tipo" class="form-control" id="tipo" required>
                        <option value="boolean">Boolean (Sim/Não)</option>
                        <option value="string">Texto</option>
                        <option value="select">Select (Lista de opções)</option>
                        <option value="json">JSON</option>
                    </select>
                </div>

                <div class="form-group" id="valorContainer">
                    <label for="valor">Valor Padrão</label>
                    <input type="text" name="valor" class="form-control">
                </div>

                <div class="form-group">
                    <label for="descricao">Descrição</label>
                    <textarea name="descricao" class="form-control" rows="2"></textarea>
                </div>

                <button type="submit" class="btn btn-success">Salvar</button>
                <a href="" class="btn btn-secondary">Cancelar</a>
            </form>
        </div>
    </div>
@stop

@section('js')
<script>
    // Script para alterar o input do valor conforme o tipo escolhido
    document.getElementById('tipo').addEventListener('change', function () {
        const tipo = this.value;
        const valorContainer = document.getElementById('valorContainer');
        valorContainer.innerHTML = '';

        let inputHtml = '';

        if (tipo === 'boolean') {
            inputHtml = `
                <label for="valor">Valor Padrão</label>
                <select name="valor" class="form-control">
                    <option value="1">Sim</option>
                    <option value="0">Não</option>
                </select>
            `;
        } else if (tipo === 'select') {
            inputHtml = `
                <label for="valor">Opções (separadas por vírgula)</label>
                <input type="text" name="valor" class="form-control" placeholder="ex: credito,pix,dinheiro">
            `;
        } else {
            inputHtml = `
                <label for="valor">Valor Padrão</label>
                <input type="text" name="valor" class="form-control">
            `;
        }

        valorContainer.innerHTML = inputHtml;
    });
</script>
@stop
