<?php

namespace App\Repositories;

use App\Models\Configuracao;
use App\Models\ConfiguracaoModelo;
use App\Repositories\Contracts\ConfigRepositoryInterface;

class ConfigRepository implements ConfigRepositoryInterface
{
    public function getConfigByTenantUuid(string $uuid)
    {
        return Configuracao::whereHas('tenant', function ($query) use ($uuid) {
            $query->where('uuid', $uuid);
        })->with('modelo')->get();
    }

    public function getCategoriesByTenantId(int $idTenant)
    {
        return Configuracao::where('tenant_id', $idTenant)->first();
    }

    public function getCategoryByUuid(string $uuid)
    {
        return Configuracao::where('uuid', $uuid)->first();
    }

    // Método extra que pode ser útil:
    public function updateOrCreateConfig(int $tenantId, string $chave, $valor)
    {
        $modelo = ConfiguracaoModelo::where('chave', $chave)->first();

        if (!$modelo) {
            return null;
        }

        return Configuracao::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'configuracoes_modelo_id' => $modelo->id,
            ],
            ['valor' => $valor]
        );
    }
}
