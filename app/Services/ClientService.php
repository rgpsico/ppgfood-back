<?php

namespace App\Services;

use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\TenantRepositoryInterface;

class ClientService
{
    protected $clientRepository;

    public function __construct(ClientRepositoryInterface $clientRepository)
    {
        $this->clientRepository = $clientRepository;
    }

    public function createNewClient(array $data)
    {
        return $this->clientRepository->createNewClient($data);
    }

    public function getClientbyAsasToken(string $token)
    {
        return $this->clientRepository->getClientbyAsasToken($token);
    }

    public function updateAsaasKey(int $clientId, string $asaasKey)
    {
        return $this->clientRepository->updateAsaasKey($clientId, $asaasKey);
    }
}
