<?php

namespace App\Events;

use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso de pedido novo so para o atendente dono do pedido (canal privado, autorizado em routes/channels.php).
 * O painel da barraca continua recebendo tudo pelo OrderCreated.
 */
class AtendenteOrderCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $order;

    private $userId;

    public function __construct(Order $order, int $userId)
    {
        $this->order = $order;
        $this->userId = $userId;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('atendente.' . $this->userId);
    }

    public function broadcastWith()
    {
        return ['order' => (new OrderResource($this->order))->resolve()];
    }
}
