<?php

namespace App\Http\Controllers\Api;

use App\Events\OrderCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreOrder;
use App\Http\Requests\Api\TenantFormRequest;
use App\Http\Resources\OrderResource;
use App\Services\AsaasService;
use App\Services\ClientService;
use App\Services\ConfigService;
use App\Services\OrderService;
use App\Services\TenantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class OrderApiController extends Controller
{
    protected $orderService, $asaasService, $tenantService, $configService;

    public function __construct(ConfigService $configService, TenantService $tenantService, OrderService $orderService, AsaasService $asaasService)
    {
        $this->orderService = $orderService;
        $this->asaasService = $asaasService;
        $this->tenantService = $tenantService;
        $this->configService = $configService;
    }


    public function store(StoreOrder $request)
    {

        dd("aaa");
        $order = $this->orderService->createNewOrder($request->all());

        $getTenantByUuid = $this->tenantService->getTenantByUuid($request->token_company);

        $tenantId = $getTenantByUuid->id;

        $configSEEntregador = $this->configService->getTenantConfigs($request->token_company);

        $order['eEntregador'] = (int) $configSEEntregador->valor;

        event(new OrderCreated($order));

        if ($request->payment_method == 'cartao_credito') {
            return $this->asaasService->cartao_de_credito($request);
        }

        if ($request->payment_method == 'PIX') {
            return $this->asaasService->criarPagamentoComPix($request);
        }


        $result =  new OrderResource($order);

        if (config_empresa('entregador_externo', $tenantId) === '1') {
            $this->enviarPedidoEntregador($result);
        }


        return $result;
    }

    public function enviarPedidoEntregador($data)
    {
        $url = 'https://www.comunidadeppg.com.br:3000/enviarpedidoparaentregadores';

        $client = new Client();

        try {
            $response = $client->post($url, [
                'json' => $data // Envia os dados no formato JSON
            ]);

            // Retorna a resposta como um objeto JSON
            return json_decode($response->getBody()->getContents());
        } catch (RequestException $e) {
            // Em caso de erro, retorna a mensagem
            return [
                'error' => true,
                'message' => $e->getMessage()
            ];
        }
    }

    public function show($identify)
    {

        if (!$order = $this->orderService->getOrderByIdentify($identify)) {
            return response()->json(['message' => 'Not Found'], 404);
        }
        $tenantId = $order->tenant_id;

        $valorConfigSeEEntregador = $this->configService->getTenantConfigsByIdTentant($tenantId);

        $order['eEntregador'] = $valorConfigSeEEntregador->valor ?? 0;
        return new OrderResource($order);
    }

    public function myOrders()
    {
        $orders = $this->orderService->ordersByClient();

        return OrderResource::collection($orders);
    }



    public function getUuidByCompanyUrl($url)
    {
        $tenant = Tenant::where('url', $url)->first();

        if (!$tenant) {
            return response()->json(['message' => 'Empresa não encontrada'], 404);
        }

        return response()->json(['uuid' => $tenant->uuid]);
    }
}
