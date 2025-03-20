@extends('adminlte::page')

@section('title', 'Configuração Geral')

@section('content_header')
    <h1>Configuração Geral do Sistema</h1>
@stop

@section('content')
    <div class="card">
        <div class="card-header">
            <h3>Opções de Pagamento</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.config.update') }}" method="POST">
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
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="payment_methods[]" value="cash_on_delivery"
                               {{ in_array('cash_on_delivery', $paymentMethods) ? 'checked' : '' }}>
                        <label class="form-check-label">Pagamento na Entrega</label>
                    </div>
                </div>

                <div class="form-group">
                    <label>Configuração dos Entregadores:</label>
                    <select class="form-control" name="delivery_mode">
                        <option value="auto" {{ ($settings['delivery_mode'] ?? '') == 'auto' ? 'selected' : '' }}>
                            Atribuição Automática
                        </option>
                        <option value="manual" {{ ($settings['delivery_mode'] ?? '') == 'manual' ? 'selected' : '' }}>
                            Atribuição Manual
                        </option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">Salvar Configurações</button>
            </form>
        </div>
    </div>
@stop
