<?php

namespace App\Http\Controllers\Api;

use App\Events\OrderCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreOrder;
use App\Http\Requests\Api\TenantFormRequest;
use App\Http\Resources\OrderResource;
use App\Services\ClientService;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class OrderApiController extends Controller
{
    protected $orderService, $clientService;

    public function __construct(OrderService $orderService, ClientService $clientService)
    {
        $this->orderService = $orderService;
        $this->clientService = $clientService;
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


    public function store(StoreOrder $request)
    {


        $order = $this->orderService->createNewOrder($request->all());

        event(new OrderCreated($order));

        if ($request->payment_method == 'cartao_credito') {
            return $this->cartao_de_credito($request);
        }


        return new OrderResource($order);
    }

    public function show($identify)
    {
        if (!$order = $this->orderService->getOrderByIdentify($identify)) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        return new OrderResource($order);
    }

    public function myOrders()
    {
        $orders = $this->orderService->ordersByClient();

        return OrderResource::collection($orders);
    }
}
