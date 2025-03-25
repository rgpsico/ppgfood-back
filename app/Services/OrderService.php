<?php

namespace App\Services;

use App\Models\Order;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\TableRepositoryInterface;
use App\Repositories\Contracts\TenantRepositoryInterface;
use Illuminate\Http\Request;

class OrderService
{
    protected $ConfigService, $orderRepository, $tenantRepository, $tableRepository, $productRepository;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        TenantRepositoryInterface $tenantRepository,
        TableRepositoryInterface $tableRepository,
        ProductRepositoryInterface $productRepository,
        ConfigService $ConfigService
    ) {
        $this->orderRepository = $orderRepository;
        $this->tenantRepository = $tenantRepository;
        $this->tableRepository = $tableRepository;
        $this->productRepository = $productRepository;
        $this->ConfigService = $ConfigService;
    }

    public function ordersByClient()
    {
        $idClient = $this->getClientIdByOrder();

        return $this->orderRepository->getOrdersByClientId($idClient);
    }

    public function getOrderByIdentify(string $identify)
    {
        return $this->orderRepository->getOrderByIdentify($identify);
    }

    public function createNewOrder(array $order)
    {




        $configSEEntregador = $this->ConfigService->getTenantConfigs($order['token_company']);
        $eEntregador = $configSEEntregador->valor;

        $productsOrder = $this->getProductsByOrder($order['products'] ?? []);
        $identify = $this->getIdentifyOrder();
        $numero_do_entregador = $this->gerarCodigoEntrega();
        $total = $this->getTotalOrder($productsOrder);
        $status = 'open';
        $tenantId = $this->getTenantIdByOrder($order['token_company']);
        $comment = isset($order['comment']) ? $order['comment'] : '';
        $clientId = $this->getClientIdByOrder();

        if (!$clientId) {
            $clientId = $order['client_id'];
        }

        $tableId = $this->getTableIdByOrder($order['table'] ?? '');

        // Adiciona o número do entregador no método createNewOrder
        $order = $this->orderRepository->createNewOrder(
            $identify,
            $total,
            $status,
            $tenantId,
            $numero_do_entregador,
            $comment,
            $clientId,
            $tableId,
            $eEntregador
        );


        $this->orderRepository->registerProductsOrder($order->id, $productsOrder);


        return $order;
    }


    private function gerarCodigoEntrega(int $tamanho = 6)
    {
        // Letras e números simples, evitando caracteres confundíveis
        $caracteres = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $codigo = substr(str_shuffle(str_repeat($caracteres, 5)), 0, $tamanho);

        // Verifica se já existe, para garantir unicidade
        if (Order::where('codigo_entrega', $codigo)->exists()) {
            return $this->gerarCodigoEntrega($tamanho);
        }

        return strtoupper($codigo);
    }


    private function getIdentifyOrder(int $qtyCaraceters = 8)
    {
        $smallLetters = str_shuffle('abcdefghijklmnopqrstuvwxyz');

        $numbers = (((date('Ymd') / 12) * 24) + mt_rand(800, 9999));
        $numbers .= 1234567890;

        // $specialCharacters = str_shuffle('!@#$%*-');

        // $characters = $smallLetters.$numbers.$specialCharacters;
        $characters = $smallLetters . $numbers;

        $identify = substr(str_shuffle($characters), 0, $qtyCaraceters);

        if ($this->orderRepository->getOrderByIdentify($identify)) {
            $this->getIdentifyOrder($qtyCaraceters + 1);
        }

        return $identify;
    }

    private function getProductsByOrder(array $productsOrder): array
    {

        $products = [];
        foreach ($productsOrder as $productOrder) {
            $product = $this->productRepository->getProductByUuid($productOrder['identify']);

            array_push($products, [
                'id' => $product->id,
                'qty' => $productOrder['qty'],
                'price' => $product->price,
            ]);
        }

        return $products;
    }

    private function getTotalOrder(array $products): float
    {
        $total = 0;

        foreach ($products as $product) {
            $total += ($product['price'] * $product['qty']);
        }

        return (float) $total;
    }

    private function getTenantIdByOrder(string $uuid)
    {
        $tenant = $this->tenantRepository->getTenantByUuid($uuid);

        return $tenant->id;
    }

    private function getTableIdByOrder(string $uuid = '')
    {
        if ($uuid) {
            $table = $this->tableRepository->getTableByUuid($uuid);

            return $table->id;
        }

        return '';
    }

    private function getClientIdByOrder()
    {
        return auth()->check() ? auth()->user()->id : '';
    }

    public function getOrdersByTenantId(int $idTenant, string $status, string $date)
    {
        return $this->orderRepository->getOrdersByTenantId($idTenant, $status, $date);
    }

    public function updateStatusOrder(string $identify, string $status)
    {
        return $this->orderRepository->updateStatusOrder($identify, $status);
    }
}
