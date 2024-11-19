<?php

namespace App\Http\Controllers\Api;

use App\Events\OrderCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreOrder;
use App\Http\Requests\Api\TenantFormRequest;
use App\Http\Resources\OrderResource;
use App\Services\AsaasService;
use App\Services\ClientService;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class OrderApiController extends Controller
{
    protected $orderService, $asaasService;

    public function __construct(OrderService $orderService, AsaasService $asaasService)
    {
        $this->orderService = $orderService;
        $this->asaasService = $asaasService;
    }



    public function store(StoreOrder $request)
    {


        $order = $this->orderService->createNewOrder($request->all());

        event(new OrderCreated($order));

        if ($request->payment_method == 'cartao_credito') {
            return $this->asaasService->cartao_de_credito($request);
        }

        if ($request->payment_method == 'PIX') {
            return $this->asaasService->criarPagamentoComPix($request);
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
