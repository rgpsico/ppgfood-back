<?php

namespace App\Http\Controllers;

use App\Services\AsaasService;
use App\Services\ClientService;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AsaasController extends Controller
{
    protected $asaasService, $clientService;

    public function __construct(AsaasService $asaasService, ClientService $clientService)
    {
        $this->asaasService = $asaasService;
        $this->clientService = $clientService;
    }

    public function updateAsaasKey(Request $request, $id): JsonResponse
    {
        $request->validate([
            'asaas_key' => 'required|string',
        ]);

        $asaasKey = $request->input('asaas_key');

        $client = $this->clientService->updateAsaasKey($id, $asaasKey);

        if (!$client) {
            return response()->json(['error' => 'Cliente não encontrado.'], 404);
        }

        return response()->json(['message' => 'Asaas key atualizada com sucesso!', 'client' => $client], 200);
    }

    public function updateAsaasKeyByEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:clients,email',
            'asaas_key' => 'required|string',
        ]);

        $client = Client::where('email', $request->email)->first();

        if (!$client) {
            return response()->json(['error' => 'Cliente não encontrado.'], 404);
        }

        $client->asaas_key = $request->asaas_key;
        $client->save();

        return response()->json(['message' => 'Asaas key atualizada com sucesso!'], 200);
    }


    public function criarCliente(Request $request)
    {
        // Pegando o token do arquivo .env
        $accessToken = env('ASAAS_ACCESS_TOKEN');

        // Definindo os dados do cliente
        $clienteData = [
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'address' => $request->input('address'),
            'province' => $request->input('province'),
            'postalCode' => $request->input('postalCode'),
            'cpf' => $request->input('cpf'),
        ];

        // Realizando a requisição POST
        $response = Http::withHeaders([
            'accept' => 'application/json',
            'access_token' => $accessToken,
            'content-type' => 'application/json'
        ])->post('https://sandbox.asaas.com/api/v3/customers', $clienteData);

        // Verificando se a requisição foi bem-sucedida
        if ($response->successful()) {
            return response()->json($response->json());
        } else {
            // Retornar uma resposta de erro em caso de falha
            return response()->json(['error' => 'Não foi possível criar o cliente'], 500);
        }
    }



    public function recuperarDadosComerciais()
    {
        // Pegando o token do arquivo .env
        $accessToken = env('ASAAS_ACCESS_TOKEN');

        // Realizando a requisição GET
        $response = Http::withHeaders([
            'accept' => 'application/json',
            'access_token' => $accessToken,
        ])->get('https://sandbox.asaas.com/api/v3/myAccount/commercialInfo');

        // Verificando se a requisição foi bem-sucedida
        if ($response->successful()) {
            return response()->json($response->json());
        } else {
            // Retornar uma resposta de erro em caso de falha
            return response()->json(['error' => 'Não foi possível recuperar os dados comerciais'], 500);
        }
    }

    public function createPayment(Request $request)
    {
        $data = $request->only(['customer', 'billingType', 'value', 'dueDate']);
        $response = $this->asaasService->createPayment($data);

        return response()->json($response);
    }

    public function listarPagamentos(Request $request)
    {
        $accessToken = env('ASAAS_ACCESS_TOKEN');
        $customerId = $request->input('customer');

        $response = Http::withHeaders([
            'accept' => 'application/json',
            'access_token' => $accessToken,
        ])->get("https://sandbox.asaas.com/api/v3/payments", [
            'customer' => $customerId
        ]);

        // Verificando se a requisição foi bem-sucedida
        if ($response->successful()) {
            return response()->json($response->json());
        } else {
            return response()->json(['error' => 'Não foi possível listar os pagamentos'], 500);
        }
    }

    public function criarPagamentoComCartao(Request $request)
    {
        $accessToken = env('ASAAS_ACCESS_TOKEN');

        // Dados do pagamento com cartão de crédito
        $paymentData = [
            'customer' => $request->input('customer'), // ID do cliente
            'billingType' => 'CREDIT_CARD',
            'dueDate' => $request->input('dueDate'), // Data de vencimento
            'value' => $request->input('value'), // Valor do pagamento
            'description' => $request->input('description', 'Pagamento com cartão de crédito'),
            'creditCard' => [
                'holderName' => $request->input('creditCard.holderName'),
                'number' => $request->input('creditCard.number'),
                'expiryMonth' => $request->input('creditCard.expiryMonth'),
                'expiryYear' => $request->input('creditCard.expiryYear'),
                'ccv' => $request->input('creditCard.ccv'),
            ],
            'creditCardHolderInfo' => [
                'name' => $request->input('creditCardHolderInfo.name'),
                'email' => $request->input('creditCardHolderInfo.email'),
                'cpfCnpj' => $request->input('creditCardHolderInfo.cpfCnpj'),
                'postalCode' => $request->input('creditCardHolderInfo.postalCode'),
                'addressNumber' => $request->input('creditCardHolderInfo.addressNumber'),
                'addressComplement' => $request->input('creditCardHolderInfo.addressComplement', ''),
                'phone' => $request->input('creditCardHolderInfo.phone'),
                'mobilePhone' => $request->input('creditCardHolderInfo.mobilePhone'),
            ]
        ];

        // Realizando a requisição POST para criar o pagamento
        $response = Http::withHeaders([
            'accept' => 'application/json',
            'access_token' => $accessToken,
            'content-type' => 'application/json'
        ])->post('https://sandbox.asaas.com/api/v3/payments', $paymentData);

        // Verificando se a requisição foi bem-sucedida
        if ($response->successful()) {
            return response()->json($response->json());
        } else {
            return response()->json(['error' => 'Não foi possível criar o pagamento com cartão de crédito'], 500);
        }
    }

    public function criarPagamento(Request $request)
    {
        $accessToken = env('ASAAS_ACCESS_TOKEN');

        // Dados do pagamento
        $paymentData = [
            'customer' => $request->input('customer'), // ID do cliente
            'billingType' => $request->input('billingType', 'BOLETO'), // Tipo de cobrança
            'dueDate' => $request->input('dueDate'), // Data de vencimento
            'value' => $request->input('value'), // Valor do pagamento
            'description' => $request->input('description'), // Descrição
            'externalReference' => $request->input('externalReference') // Opcional
        ];

        // Realizando a requisição POST
        $response = Http::withHeaders([
            'accept' => 'application/json',
            'access_token' => $accessToken,
            'content-type' => 'application/json'
        ])->post('https://sandbox.asaas.com/api/v3/payments', $paymentData);

        // Verificando se a requisição foi bem-sucedida
        if ($response->successful()) {
            return response()->json($response->json());
        } else {
            return response()->json(['error' => 'Não foi possível criar o pagamento'], 500);
        }
    }

    public function getPaymentStatus($paymentId)
    {
        $response = $this->asaasService->getPaymentStatus($paymentId);

        return response()->json($response);
    }
}
