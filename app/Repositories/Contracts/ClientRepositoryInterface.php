<?php

namespace App\Repositories\Contracts;

interface ClientRepositoryInterface
{
    public function createNewClient(array $data);
    public function getClient(int $id);
    public function updateAsaasKey(int $id, string $assasKey);
}
