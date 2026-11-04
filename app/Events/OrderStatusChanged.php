<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Pedido mudou de status no painel (atendido, entregue...). Os outros aparelhos da barraca param o alarme e
 * os relatorios se atualizam na hora. Sem dado pessoal: so o que o painel precisa para atualizar a tela.
 */
class OrderStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $order;

    private $tenantId;
    private $atendenteUserId;

    public function __construct(Order $order)
    {
        $this->order = ['identify' => $order->identify, 'status' => $order->status, 'total' => (float) $order->total];
        $this->tenantId = $order->tenant_id;
        $this->atendenteUserId = $order->attendant ? $order->attendant->user_id : null;
    }

    public function broadcastOn()
    {
        $canais = [new PrivateChannel('order-created.' . $this->tenantId)];

        // o atendente dono do pedido (canal privado dele) tambem precisa saber que o pedido foi atendido
        if ($this->atendenteUserId) {
            $canais[] = new PrivateChannel('atendente.' . $this->atendenteUserId);
        }

        return $canais;
    }
}
