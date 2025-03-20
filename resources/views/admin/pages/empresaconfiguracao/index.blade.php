@extends('adminlte::page')

@section('title', 'Configuração da Empresa')

@section('content_header')
    <h1>Configuração da Empresa</h1>
@stop

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Personalize sua Configuração</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('empresa.config.update') }}" method="POST">
                @csrf
                @method('PUT')

                <div class="form-group">
                    <label>Selecione os métodos de pagamento disponíveis:</label><br>
                    @php
                        $paymentMethods = json_decode($settings['payment_methods'] ?? '[]', true);
                    @endphp
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="payment_methods[]" value="credit_card"
                               {{ in_array('credit_card', $paymentMethods) ? 'checked' : '' }}>
                        <label class="form-check-label">Cartão de Crédito</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="payment_methods[]" value="pix"
                               {{ in_array('pix', $paymentMethods) ? 'checked' : '' }}>
                        <label class="form-check-label">PIX</label>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Salvar Configurações</button>
            </form>
        </div>
    </div>
@stop
