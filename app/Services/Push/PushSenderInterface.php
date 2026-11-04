<?php

namespace App\Services\Push;

use App\Models\PushSubscription;

interface PushSenderInterface
{
    /**
     * Envia o aviso. Devolve false quando o celular nao existe mais (404/410) e a inscricao deve ser apagada.
     */
    public function enviar(PushSubscription $inscricao, array $payload): bool;
}
