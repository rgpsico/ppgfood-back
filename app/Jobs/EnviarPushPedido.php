<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\Push\PushService;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

/**
 * Despachado com ->afterResponse(): o cliente ja recebeu a resposta do pedido quando o push sai.
 */
class EnviarPushPedido
{
    use Dispatchable, Queueable;

    private $pedidoId;
    private $atendenteUserId;

    public function __construct(int $pedidoId, ?int $atendenteUserId = null)
    {
        $this->pedidoId = $pedidoId;
        $this->atendenteUserId = $atendenteUserId;
    }

    public function handle(PushService $push)
    {
        try {
            if ($pedido = Order::withoutGlobalScopes()->find($this->pedidoId)) {
                $push->notificarPedido($pedido, $this->atendenteUserId);
            }
        } catch (\Throwable $e) {
            // Aviso nunca pode derrubar nem atrasar o pedido
            Log::warning('Push: erro ao notificar pedido ' . $this->pedidoId, ['erro' => $e->getMessage()]);
        }
    }
}
