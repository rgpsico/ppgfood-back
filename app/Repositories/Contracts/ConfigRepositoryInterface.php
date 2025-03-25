<?php

namespace App\Repositories\Contracts;

interface ConfigRepositoryInterface
{
    public function getConfigByTenantUuid(string $uuid);
    public function getCategoriesByTenantId(int $idTenant);
    public function getCategoryByUuid(string $uuid);
    public function updateOrCreateConfig(int $tenantId, string $chave, $valor);
}
