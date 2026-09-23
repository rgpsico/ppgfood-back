<?php

namespace App\Http\Controllers\Api;

use App\Events\OrderCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreOrder;
use App\Http\Requests\Api\TenantFormRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Table;
use App\Services\AsaasService;
use App\Services\ClientService;
use App\Services\ConfigService;
use App\Services\OrderService;
use App\Services\TenantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\DB;

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

        
   $order = $this->orderService->createNewOrder($request->all())->load('products');

            // Update stock in a transaction
            DB::transaction(function () use ($order) {
                foreach ($order->products as $product) {
                    // Ensure the product exists and has sufficient stock
                    if ($product && $product->stock >= $product->pivot->qty) {
                        $product->decrement('stock', $product->pivot->qty);
                    } else {
                        // Log and throw an error if stock is insufficient or product is null
                        $errorMessage = $product ? 
                            "Insufficient stock for product ID: {$product->id}. Requested: {$product->pivot->qty}, Available: {$product->stock}" :
                            "Product is null for order ID: {$order->id}";
                        // \Log::error($errorMessage);
                        throw new \Exception($errorMessage);
                    }
                }
            });

     
        $getTenantByUuid = $this->tenantService->getTenantByUuid($request->token_company);

        $tenantId = $getTenantByUuid->id;

        $configSEEntregador = $this->configService->getTenantConfigs($request->token_company);

        $order['eEntregador'] = $configSEEntregador ? (int) $configSEEntregador->valor : 0;

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



    /**
     * Painel de recebimento: guarda-sois/cadeiras com o pedido ativo
     * de cada um (se houver), mais os pedidos ativos sem mesa (delivery/retirada).
     */
    public function board()
    {
        $tables = Table::with(['orders' => function ($query) {
            $query->whereIn('status', Order::ACTIVE_STATUSES)
                ->with('products', 'client')
                ->latest();
        }])->get();

        $tablesData = $tables->map(function ($table) {
            $order = $table->orders->first();

            return [
                'identify' => $table->uuid,
                'name' => $table->identify,
                'description' => $table->description,
                'position_x' => $table->position_x,
                'position_y' => $table->position_y,
                'beach_row' => $table->beach_row,
                'beach_col' => $table->beach_col,
                'order' => $order ? new OrderResource($order) : null,
            ];
        });

        $ordersWithoutTable = $this->orderService
            ->ordersActiveWithoutTable(Order::ACTIVE_STATUSES);

        return response()->json([
            'tables' => $tablesData,
            'orders_without_table' => OrderResource::collection($ordersWithoutTable),
        ]);
    }

    /**
     * Lista de pedidos com filtro por status (pendente/entregue/todos)
     * e por guarda-sol/cadeira - usado na tela de historico/filtros do painel.
     */
    public function index(Request $request)
    {
        $orders = $this->orderService->ordersFiltered(
            $request->query('status', 'all'),
            $request->query('table')
        );

        return OrderResource::collection($orders);
    }

    /**
     * Atualiza o status de um pedido a partir do painel de recebimento.
     * O painel so trabalha com dois estados: pedido pendente (open) ou
     * entregue (done) - os demais status (rejeitado/cancelado/etc) continuam
     * sendo geridos pelo admin Blade.
     */
    public function updateStatus(Request $request, $identify)
    {
        $request->validate([
            'status' => 'required|in:open,done',
        ]);

        $order = $this->orderService->updateStatusOrder($identify, $request->status);

        if (!$order) {
            return response()->json(['message' => 'Pedido não encontrado'], 404);
        }

        return new OrderResource($order->load('products', 'client', 'table'));
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
