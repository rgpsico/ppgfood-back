<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClient;
use App\Http\Resources\ClientResource;
use App\Services\AsaasService;
use App\Services\ClientService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
        // Criar cliente no Asaas (não bloqueia o cadastro caso falhe)
        $asaasId = null;

        try {
            $criarClienteAsaas = $this->assasService->createCustomer($request);

            if ($criarClienteAsaas->successful()) {
                $asaasId = $criarClienteAsaas['id'];
            } else {
                Log::warning('Falha ao criar cliente no Asaas, prosseguindo sem asaas_key', [
                    'response' => $criarClienteAsaas->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Exceção ao criar cliente no Asaas, prosseguindo sem asaas_key', [
                'message' => $e->getMessage(),
            ]);
        }

        $requestData = $request->all();
        $requestData['asaas_key'] = $asaasId;

        // Criar cliente na base de dados local
        $client = $this->clientService->createNewClient($requestData);

        return new ClientResource($client);
    }
}
