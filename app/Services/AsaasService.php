<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AsaasService
{
    protected $access_token;
    protected $baseUrl;
    protected $clientService;
    protected $tenant;


    public function __construct(ClientService $clientService)
    {
        $this->access_token = env('ASAAS_ACCESS_TOKEN');
        $this->baseUrl = config('asaas.base_sandbox_url');
        $this->clientService = $clientService;
    }

    protected function request($method, $endpoint, $data = [])
    {
        $response = Http::withHeaders([
            'access_token' => $this->access_token,
            'accept' => 'application/json',
            'content-type' => 'application/json',
        ])->$method("{$this->baseUrl}/$endpoint", $data);

        return $response->json();
    }

    public function createCustomer($request)
    {

        $clienteData = [
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'address' => $request->input('address'),
            'province' => $request->input('province'),
            'postalCode' => $request->input('postalCode'),
            'cpf' => $request->input('cpfcnpj'),
        ];


        // Realizando a requisição POST
        return  $response = Http::withHeaders([
            'accept' => 'application/json',
            'access_token' => $this->access_token,
            'content-type' => 'application/json'
        ])->post('https://sandbox.asaas.com/api/v3/customers', $clienteData);
    }

    public function createCustomerLoja($request)
    {
        $clienteData = [
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'address' => $request->input('address'),
            'province' => $request->input('province'),
            'postalCode' => $request->input('postalCode'),
            'cpf' => $request->input('cpfcnpj'),
        ];

        // Realizando a requisição POST
        $response = Http::withHeaders([
            'accept' => 'application/json',
            'access_token' => $this->access_token,
            'content-type' => 'application/json'
        ])->post('https://sandbox.asaas.com/api/v3/customers', $clienteData);

        // Verifica se a requisição foi bem-sucedida
        if ($response->successful()) {
            return $response->json('id');
        }

        // Caso haja erro, lança uma exceção com a mensagem retornada pela API
        throw new \Exception('Erro ao criar cliente: ' . $response->body());
    }

    public function listarPagamentosLoja($asaasKey)
    {
        // Fazendo a requisição GET para o endpoint de listagem de pagamentos
        $response = Http::withHeaders([
            'accept' => 'application/json',
            'access_token' => env('ASAAS_ACCESS_TOKEN'),
        ])->get('https://sandbox.asaas.com/api/v3/payments', [
            'customer' => $asaasKey, // Filtrando pelo cliente (loja)
        ]);

        if ($response->successful()) {
            // Retorna a lista de pagamentos
            return $response->json(); // Contém todos os pagamentos dessa loja
        }

        // Caso haja erro na requisição
        throw new \Exception('Erro ao listar pagamentos: ' . $response->body());
    }


    public function recuperarClienteAsaas($customerId)
    {
        $accessToken = env('ASAAS_ACCESS_TOKEN');

        $response = Http::withHeaders([
            'accept' => 'application/json',
            'access_token' => $accessToken,
        ])->get("https://sandbox.asaas.com/api/v3/customers/{$customerId}");

        // Verificando se a requisição foi bem-sucedida
        if ($response->successful()) {
            return $response->json();
        } else {
            return null; // Cliente não encontrado ou houve um erro
        }
    }



    public function criarPagamentoComPix($request)
    {
        // Validação dos dados recebidos no request
        $validatedData = $request->validate([
            'asaas_key' => 'required|string',
            'value' => 'required|numeric|min:1',
            'description' => 'nullable|string',
        ]);

        // Dados do pagamento com Pix
        $paymentData = [
            'customer' => $validatedData['asaas_key'], // ID do cliente no Asaas
            'billingType' => 'PIX',
            'dueDate' => now()->format('Y-m-d'), // Definindo a data de vencimento para hoje (ou altere conforme necessário)
            'value' => $validatedData['value'], // Valor do pagamento
            'description' => $validatedData['description'] ?? 'Pagamento via Pix',
        ];

        // Realizando a requisição POST para criar o pagamento via Pix
        $response = Http::withHeaders([
            'accept' => 'application/json',
            'access_token' => env('ASAAS_ACCESS_TOKEN'),
            'content-type' => 'application/json',
        ])->post('https://sandbox.asaas.com/api/v3/payments', $paymentData);

        // Verificando se a requisição foi bem-sucedida
        if ($response->successful()) {
            $responseData = $response->json();

            // Retornando o QR Code URL e outros detalhes necessários para o front-end
            return response()->json([
                'qrCode' => $responseData['bankSlipUrl'] ?? null, // QR Code gerado
                'expirationDate' => $responseData['dueDate'],
                'value' => $responseData['value'],
                'transactionId' => $responseData['id'],
            ], 200);
        } else {
            return response()->json([
                'error' => 'Não foi possível criar o pagamento com Pix',
                'details' => $response->json(),
            ], 500);
        }
    }


    public function cartao_de_credito($request)
    {
        $clienteAssas = $this->recuperarClienteAsaas($request->asaas_key);

        if (!$clienteAssas) {
            return response()->json(['error' => 'Não foi possível recuperar o cliente do Asaas'], 404);
        }

        $cliente = $this->clientService->getClientbyAsasToken($request->asaas_key);

        if (!$cliente) {
            return response()->json(['error' => 'Não foi possível recuperar o cliente do sistema'], 404);
        }

        // Validação dos dados recebidos no request
        $validatedData = $request->validate([
            'asaas_key' => 'required|string',
            'value' => 'required|numeric|min:1',
            'description' => 'nullable|string',
            'creditCard.holderName' => 'required|string',
            'creditCard.number' => 'required|string',
            'creditCard.expiryMonth' => 'required|string',
            'creditCard.expiryYear' => 'required|string',
            'creditCard.ccv' => 'required|string',
            'creditCardHolderInfo.name' => 'required|string',
            'creditCardHolderInfo.postalCode' => 'nullable|string',
            'creditCardHolderInfo.addressNumber' => 'nullable|string',
            'creditCardHolderInfo.phone' => 'nullable|string',
            'creditCardHolderInfo.mobilePhone' => 'nullable|string',
        ]);

        // Dados do pagamento com cartão de crédito
        $paymentData = [
            'customer' => $validatedData['asaas_key'],
            'billingType' => 'CREDIT_CARD',
            'dueDate' => now()->format('Y-m-d'), // Definindo a data de vencimento para hoje (ou altere conforme necessário)
            'value' => $validatedData['value'],
            'description' => $validatedData['description'] ?? 'Pagamento com cartão de crédito',
            'creditCard' => [
                'holderName' => $validatedData['creditCard']['holderName'],
                'number' => $validatedData['creditCard']['number'],
                'expiryMonth' => $validatedData['creditCard']['expiryMonth'],
                'expiryYear' => $validatedData['creditCard']['expiryYear'],
                'ccv' => $validatedData['creditCard']['ccv'],
            ],
            'creditCardHolderInfo' => [
                'name' => $cliente['name'],
                'email' => $cliente['email'],
                'cpfCnpj' => $cliente['cpfcnpj'],
                'postalCode' => $clienteAssas['postalCode'] ?? '22071060',
                'addressNumber' => $request->input('creditCard.addressNumber') ?? '200',
                'addressComplement' => $request->input('creditCard.addressComplement', ''),
                'phone' => $clienteAssas['phone'],
                'mobilePhone' => $clienteAssas['mobilePhone'],
            ],
        ];

        // Realizando a requisição POST para criar o pagamento
        $response = Http::withHeaders([
            'accept' => 'application/json',
            'access_token' => env('ASAAS_ACCESS_TOKEN'),
            'content-type' => 'application/json',
        ])->post('https://sandbox.asaas.com/api/v3/payments', $paymentData);

        if ($response->successful()) {
            // Recuperar o último pedido feito pelo cliente no banco de dados
            $ultimaOrdem = $cliente->orders()->latest()->first();

            if ($ultimaOrdem) {
                return response()->json([
                    'message' => 'Pagamento realizado com sucesso!',
                    'identify' => $ultimaOrdem->identify
                ]);
            }
        }
    }


    public function getCommercialInfo()
    {
        return $this->request('get', 'myAccount/commercialInfo');
    }

    public function createPayment($data)
    {
        return $this->request('post', 'payments', $data);
    }

    public function listPayments($customerId)
    {
        return $this->request('get', 'payments', ['customer' => $customerId]);
    }

    public function getPaymentStatus($paymentId)
    {
        return $this->request('get', "payments/$paymentId");
    }
}
