<?php

namespace App\Http\Controllers\Api;

use App\Events\AtendenteOrderCreated;
use App\Events\OrderCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreOrder;
use App\Http\Requests\Api\TenantFormRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Table;
use App\Services\AsaasService;
use App\Services\AtendimentoService;
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
    protected $orderService, $asaasService, $tenantService, $configService, $atendimentoService;

    public function __construct(ConfigService $configService, TenantService $tenantService, OrderService $orderService, AsaasService $asaasService, AtendimentoService $atendimentoService)
    {
        $this->atendimentoService = $atendimentoService;
        $this->orderService = $orderService;
        $this->asaasService = $asaasService;
        $this->tenantService = $tenantService;
        $this->configService = $configService;
    }


    public function store(StoreOrder $request)
    {
        // Atendente validado no backend (empresa, ativo, regras da empresa) ANTES de criar o pedido.
        // attendant_id vindo do cliente e sempre sobrescrito: so vale o que o servico resolveu.
        $tenantDoPedido = $this->tenantService->getTenantByUuid($request->token_company);
        $atendente = $this->atendimentoService->resolverParaPedido(
            $tenantDoPedido,
            $request->input('attendant'),
            (bool) $request->input('table'),
            $request->input('table')
        );

        // Pedido e baixa de estoque na MESMA transacao: sem estoque, nada e criado (antes o pedido
        // ficava gravado e o cliente recebia erro 500). A baixa e atomica, entao dois clientes
        // pedindo o ultimo item ao mesmo tempo nao deixam o estoque negativo.
        $order = DB::transaction(function () use ($request, $atendente) {
            $order = $this->orderService->createNewOrder(
                array_merge($request->all(), ['attendant_id' => $atendente ? $atendente->id : null])
            )->load('products');

            foreach ($order->products as $product) {
                $baixou = \App\Models\Product::withoutGlobalScopes()
                    ->where('id', $product->id)
                    ->where('stock', '>=', $product->pivot->qty)
                    ->decrement('stock', $product->pivot->qty);

                if (!$baixou) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'products' => ["Estoque insuficiente para \"{$product->title}\"."],
                    ]);
                }
            }

            return $order;
        });

        $getTenantByUuid = $this->tenantService->getTenantByUuid($request->token_company);

        $tenantId = $getTenantByUuid->id;

        $configSEEntregador = $this->configService->getTenantConfigs($request->token_company);

        $order['eEntregador'] = $configSEEntregador ? (int) $configSEEntregador->valor : 0;

        event(new OrderCreated($order));

        // Notificacao no celular (funciona com o painel fechado); sai depois que o cliente ja recebeu a resposta
        \App\Jobs\EnviarPushPedido::dispatch(
            $order->id,
            $this->atendimentoService->deveNotificar($tenantDoPedido, $atendente) ? $atendente->user_id : null
        )->afterResponse();

        if ($this->atendimentoService->deveNotificar($tenantDoPedido, $atendente)) {
            event(new AtendenteOrderCreated($order, $atendente->user_id));
        }

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
     * Usuario do painel que e atendente enxerga so os proprios pedidos; usuario sem cadastro de
     * atendente (dono/gerente) continua vendo tudo, como antes.
     */
    private function atendenteDoPainel(Request $request)
    {
        return $request->user()->atendente;
    }

    /**
     * Painel de recebimento: guarda-sois/cadeiras com o pedido ativo
     * de cada um (se houver), mais os pedidos ativos sem mesa (delivery/retirada).
     */
    public function board(Request $request)
    {
        $atendente = $this->atendenteDoPainel($request);
        $attendantId = $atendente ? $atendente->id : null;

        $tables = Table::with(['orders' => function ($query) use ($attendantId) {
            $query->whereIn('status', Order::ACTIVE_STATUSES)
                ->when($attendantId, fn ($q) => $q->where('attendant_id', $attendantId))
                ->with('products', 'client', 'attendant')
                ->latest();
        }])->get();

        // Atendente so enxerga os guarda-sois que esta atendendo: com pedido ativo dele ou cadastrados por ele
        if ($attendantId) {
            $tables = $tables->filter(fn ($table) => $table->orders->isNotEmpty() || $table->attendant_id == $attendantId)->values();
        }

        $tablesData = $tables->map(function ($table) use ($attendantId) {
            $order = $table->orders->first();

            return [
                'identify' => $table->uuid,
                'name' => $table->identify,
                'tipo' => $table->tipo ?: Table::TIPO_GUARDA_SOL,
                'description' => $table->description,
                'position_x' => $table->position_x,
                'position_y' => $table->position_y,
                'beach_row' => $table->beach_row,
                'beach_col' => $table->beach_col,
                'order' => $order ? new OrderResource($order) : null,
                // ponto cadastrado pelo atendente: fica na lista dele mesmo sem pedido
                'mine' => (bool) ($attendantId && $table->attendant_id == $attendantId),
            ];
        });

        $ordersWithoutTable = $this->orderService
            ->ordersActiveWithoutTable(Order::ACTIVE_STATUSES, $attendantId);

        return response()->json([
            'tables' => $tablesData,
            'orders_without_table' => OrderResource::collection($ordersWithoutTable),
            // true = lista ja filtrada para o atendente (o painel nao deve oferecer reorganizar o mapa)
            'only_serving' => (bool) $attendantId,
        ]);
    }

    /**
     * Lista de pedidos com filtro por status (pendente/entregue/todos)
     * e por guarda-sol/cadeira - usado na tela de historico/filtros do painel.
     */
    public function index(Request $request)
    {
        $atendente = $this->atendenteDoPainel($request);
        $attendantId = $atendente ? $atendente->id : null;

        // Dono/gerente pode filtrar por atendente (?attendant=uuid); atendente nao escolhe: so os dele
        if (!$attendantId && $request->query('attendant')) {
            $filtrado = \App\Models\Atendente::where('uuid', $request->query('attendant'))->first();
            $attendantId = $filtrado ? $filtrado->id : 0;
        }

        $orders = $this->orderService->ordersFiltered(
            $request->query('status', 'all'),
            $request->query('table'),
            $attendantId
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
            'status' => 'required|in:open,working,done',
        ]);

        $atendente = $this->atendenteDoPainel($request);

        // Atendente so mexe nos pedidos dele (e o escopo por empresa ja barra pedido de outra barraca)
        if ($atendente && !Order::where('identify', $identify)->where('attendant_id', $atendente->id)->exists()) {
            return response()->json(['message' => 'Pedido não encontrado'], 404);
        }

        $order = $this->orderService->updateStatusOrder($identify, $request->status);

        if (!$order) {
            return response()->json(['message' => 'Pedido não encontrado'], 404);
        }

        return new OrderResource($order->load('products', 'client', 'table', 'attendant'));
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
