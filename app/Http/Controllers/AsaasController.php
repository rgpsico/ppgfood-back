<?php

namespace App\Http\Controllers;

use App\Services\AsaasService;
use App\Services\ClientService;
use App\Models\Client;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AsaasController extends Controller
{
    protected $asaasService, $clientService, $orderService;

    public function __construct(
        AsaasService $asaasService,
        ClientService $clientService,
        OrderService $orderService
    ) {
        $this->asaasService = $asaasService;
        $this->clientService = $clientService;
        $this->orderService = $orderService;
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
        $response = $this->asaasService->createCustomer($request);

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




    public function criarPagamentoComCartao(Request $request)
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
            } else {
                return response()->json([
                    'message' => 'Pagamento realizado, mas a ordem não foi encontrada.',
                    'order_identify' => null
                ]);
            }
        } else {
            return response()->json([
                'error' => 'Não foi possível criar o pagamento com cartão de crédito',
                'details' => $response->json()
            ], 500);
        }
    }


    public function asaasWebhook(Request $request)
    {
        // Capture o payload do webhook
        $data = $request->all();

        // Verifica se é uma notificação de pagamento Pix concluído
        if ($data['event'] === 'PAYMENT_RECEIVED' && $data['payment']['billingType'] === 'PIX') {
            // Atualiza o status do pedido no banco de dados
            $paymentId = $data['payment']['id']; // ID do pagamento no Asaas
            $status = $data['payment']['status'];

            // Aqui você pode atualizar seu banco de dados para indicar que o pagamento foi recebido
            Log::info("Pagamento Pix recebido: $paymentId com status: $status");

            return response()->json(['message' => 'Pagamento recebido e processado.'], 200);
        }

        return response()->json(['message' => 'Evento não tratado.'], 400);
    }


    public function criarPixQrcodeEstatico()
    {

        $accessToken = env('ASAAS_ACCESS_TOKEN');
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'access_token' => $accessToken,
        ])->post("https://sandbox.asaas.com/api/v3/pix/qrCodes/static", []);

        if ($response->successful()) {
            $data = $response->json();
            // Faça algo com os dados retornados, como salvar no banco ou retornar para a view
            return response()->json($data);
        } else {
            // Tratamento de erro
            $error = $response->json();
            return response()->json($error, $response->status());
        }
    }
    public function criarPagamentoPix(Request $request)
    {
        $accessToken = env('ASAAS_ACCESS_TOKEN');

        // Dados do QR Code estático
        $qrCodeData = [
            'addressKey' => $request->input('addressKey'), // Chave de endereço Pix
            'value' => $request->input('value'), // Valor do pagamento
            'format' => $request->input('format', 'ALL'), // Formato (opcional)
            'expirationDate' => $request->input('expirationDate'), // Data de expiração
            'expirationSeconds' => $request->input('expirationSeconds', 10) // Expiração em segundos (opcional)
        ];

        // Realizando a requisição POST para criar o QR Code estático
        $response = Http::withHeaders([
            'accept' => 'application/json',
            'access_token' => $accessToken,
            'content-type' => 'application/json'
        ])->post('https://sandbox.asaas.com/api/v3/pix/qrCodes/static', $qrCodeData);

        // Verificando se a requisição foi bem-sucedida
        if ($response->successful()) {
            return response()->json($response->json());
        } else {
            return response()->json(['error' => 'Não foi possível gerar o QR Code Pix'], 500);
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
