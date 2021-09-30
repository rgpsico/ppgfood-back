<?php

namespace App\Repositories\Contracts;

interface ProductRepositoryInterface
{
    public function getproductsByTenantId(int $idTenant, array $categories);
    public function getProductByUuid(string $uuid);
    public function getProductByid(string $uiid);
    
}
