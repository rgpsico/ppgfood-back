<?php

namespace App\Repositories;

use App\Models\Order;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Support\Facades\DB;

class OrderRepository implements OrderRepositoryInterface
{
    protected $entity;

    public function __construct(Order $order)
    {
        $this->entity = $order;
    }

    public function createNewOrder(
        string $identify,
        float $total,
        string $status,
        int $tenantId,
        string $numero_do_entregador,
        string $comment = '',
        $clientId = '',
        $tableId = '',
        $eEntregador
    ) {

        $data = [
            'tenant_id' => $tenantId,
            'identify' => $identify,
            'codigo_entrega' => $numero_do_entregador, // novo campo aqui
            'total' => $total,
            'status' => $status,
            'comment' => $comment,
            'eEntregador' =>  $eEntregador
        ];

        if ($clientId) {
            $data['client_id'] = $clientId;
        }

        if ($tableId) {
            $data['table_id'] = $tableId;
        }

        if ($eEntregador) {
            $data['eEntregador'] = $eEntregador;
        }

        $order = $this->entity->create($data);

        return $order;
    }



    public function getOrderByIdentify(string $identify)
    {
        return $this->entity
            ->where('identify', $identify)
            ->first();
    }

    public function registerProductsOrder(int $orderId, array $products)
    {
        $order = $this->entity->find($orderId);

        $orderProducts = [];

        foreach ($products as $product) {
            $orderProducts[$product['id']] = [
                'qty' => $product['qty'],
                'price' => $product['price'],
            ];
        }

        $order->products()->attach($orderProducts);

        // foreach ($products as $product) {
        //     array_push($orderProducts, [
        //         'order_id' => $orderId,
        //         'product_id' => $product['id'],
        //         'qty' => $product['qty'],
        //         'price' => $product['price'],
        //     ]);
        // }

        // DB::table('order_product')->insert($orderProducts);
    }

    public function getOrdersByClientId(int $idClient)
    {
        $orders = $this->entity
            ->where('client_id', $idClient)
            ->paginate();

        return $orders;
    }

    public function getOrdersByTenantId(int $idTenant, string $status, string $date = null)
    {

        $ordersQuery = Order::query();

        if ($status != 'all') {
            $ordersQuery->where('status', $status);
        }

        if ($date) {
            $ordersQuery->whereDate('created_at', $date);
        }


        $orders = $ordersQuery->orderBy('created_at', 'desc')->get();
        return $orders;
    }

    public function updateStatusOrder(string $identify, string $status)
    {
        $this->entity->where('identify', $identify)->update(['status' => $status]);

        return $this->entity->where('identify', $identify)->first();
    }
}
