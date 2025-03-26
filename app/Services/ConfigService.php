<?php

namespace App\Services;

use App\Repositories\Contracts\ConfigRepositoryInterface;
use App\Services\TenantService;

class ConfigService
{
    protected $configRepository;
    protected $tenantService;

    public function __construct(
        ConfigRepositoryInterface $configRepository,
        TenantService $tenantService
    ) {
        $this->configRepository = $configRepository;
        $this->tenantService = $tenantService;
    }

    public function getTenantConfigs($uuid)
    {

        $tenant = $this->tenantService->getTenantByUuid($uuid)->first();

        if (!$tenant) {
            return null;
        }

        return $this->configRepository->getCategoriesByTenantId($tenant->id);
    }

    public function getTenantConfigsByIdTentant($idtenant)
    {
        return $this->configRepository->getCategoriesByTenantId($idtenant);
    }

    public function updateTenantConfigs(int $tenantId, array $configs)
    {
        foreach ($configs as $chave => $valor) {
            $this->configRepository->updateOrCreateConfig($tenantId, $chave, $valor);
        }

        return true;
    }

    public function getConfigValue($uuid, $chave)
    {
        $configs = $this->getTenantConfigs($uuid);
        $config = $configs->firstWhere('modelo.chave', $chave);

        return $config ? $config->valor : null;
    }
}
