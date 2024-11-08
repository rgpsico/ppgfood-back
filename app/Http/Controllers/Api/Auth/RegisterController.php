<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClient;
use App\Http\Resources\ClientResource;
use App\Services\AsaasService;
use App\Services\ClientService;
use Illuminate\Http\Request;

class RegisterController extends Controller
{
    protected $clientService, $assasService;

    public function __construct(ClientService $clientService, AsaasService $assasService)
    {
        $this->clientService = $clientService;
        $this->assasService = $assasService;
    }


    public function store(StoreClient $request)
    {
        // Criar cliente no Asaas
        $criarClienteAsaas = $this->assasService->createCustomer($request);

        if (!$criarClienteAsaas->successful()) {
            return response()->json(['error' => 'Não foi possível criar o cliente no Asaas'], 500);
        }

        // Pegar o ID retornado do Asaas
        $asaasId = $criarClienteAsaas['id'];

        $requestData = $request->all();
        $requestData['asaas_key'] = $asaasId;

        // Criar cliente na base de dados local
        $client = $this->clientService->createNewClient($requestData);

        return new ClientResource($client);
    }
}
