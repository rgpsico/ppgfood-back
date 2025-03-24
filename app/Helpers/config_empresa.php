<?php

use App\Models\Configuracao;
use App\Models\ConfiguracaoModelo;

if (!function_exists('config_empresa')) {
    function config_empresa($chave,  $tenantId)
    {


        $modelo = ConfiguracaoModelo::where('chave', $chave)->first();
        if (!$modelo) return null;

        $config = Configuracao::where('tenant_id', $tenantId)
            ->where('configuracoes_modelo_id', $modelo->id)
            ->first();

        return $config->valor ?? $modelo->valor_padrao;
    }
}
