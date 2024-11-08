<?php

namespace App\Repositories;

use App\Models\Client;
use App\Repositories\Contracts\ClientRepositoryInterface;

class ClientRepository implements ClientRepositoryInterface
{
    protected $entity;

    public function __construct(Client $client)
    {
        $this->entity = $client;
    }

    public function createNewClient(array $data)
    {
        $data['password'] = bcrypt($data['password']);
        $data['cpfcnpj'] =  $data['cpf'];
        return $this->entity->create($data);
    }

    public function updateAsaasKey(int $id, string $asaasKey)
    {
        $entity = $this->getClient($id);

        if ($entity) {
            $entity->asaas_key = $asaasKey;
            $entity->save();
            return $entity;
        }

        return null;
    }

    public function getClient(int $id)
    {
        return $this->entity->find($id);
    }

    public function getClientbyAsasToken(string $token)
    {
        return $this->entity->where('asaas_key', $token)->first();
    }
}
