<?php

namespace App\Services\Push;

use App\Models\Atendente;
use App\Models\Order;
use App\Models\PushSubscription;
use App\Models\User;

/**
 * Aviso de pedido novo no celular (notificacao do sistema, funciona com o painel fechado).
 * Gerente/dono recebe os pedidos da barraca toda; atendente so os dele (mesma regra do canal privado).
 */
class PushService
{
    private $sender;

    public function __construct(PushSenderInterface $sender)
    {
        $this->sender = $sender;
    }

    /**
     * @param int|null $atendenteUserId usuario do atendente do pedido (ja validado pelo AtendimentoService::deveNotificar)
     */
    public function notificarPedido(Order $pedido, ?int $atendenteUserId = null): int
    {
        $pedido->loadMissing('products', 'table', 'attendant');

        $destinatarios = User::query()
            ->where('tenant_id', $pedido->tenant_id)
            ->whereNotIn('id', Atendente::withoutGlobalScopes()->whereNotNull('user_id')->select('user_id'))
            ->pluck('id');

        if ($atendenteUserId) {
            $destinatarios = $destinatarios->push($atendenteUserId)->unique();
        }

        $enviados = 0;
        $payload = $this->payloadDoPedido($pedido);

        PushSubscription::whereIn('user_id', $destinatarios)->get()->each(function ($inscricao) use ($payload, &$enviados) {
            if ($this->sender->enviar($inscricao, $payload)) {
                $enviados++;
            } else {
                $inscricao->delete();
            }
        });

        return $enviados;
    }

    /** Aviso de teste so para os celulares do proprio usuario (botao "Testar aviso" do painel). */
    public function enviarTeste(User $usuario): int
    {
        $enviados = 0;
        $payload = [
            'title' => 'Teste de aviso',
            'body' => 'Se você viu isto, os avisos de pedido estão funcionando neste celular.',
            'tag' => 'teste-aviso',
            'url' => '/',
        ];

        PushSubscription::where('user_id', $usuario->id)->get()->each(function ($inscricao) use ($payload, &$enviados) {
            if ($this->sender->enviar($inscricao, $payload)) {
                $enviados++;
            } else {
                $inscricao->delete();
            }
        });

        return $enviados;
    }

    public function payloadDoPedido(Order $pedido): array
    {
        $itens = (int) $pedido->products->sum(fn ($p) => $p->pivot->qty);
        $local = $pedido->table ? $pedido->table->identify : 'Sem guarda-sol';

        $corpo = $itens . ($itens === 1 ? ' item' : ' itens') . ' · R$ ' . number_format((float) $pedido->total, 2, ',', '.');
        if ($pedido->attendant) {
            $corpo .= ' · ' . $pedido->attendant->nome;
        }

        return [
            'title' => 'Novo pedido · ' . $local,
            'body' => $corpo,
            'tag' => $pedido->identify,
            'url' => '/',
        ];
    }
}
