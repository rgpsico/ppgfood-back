<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Tenant;
use App\Services\AtendimentoService;
use App\Services\Push\PushService;
use Illuminate\Console\Command;

/**
 * Pedido que ninguem atendeu (status "open") volta a avisar no celular a cada minuto, por ate 10 minutos.
 * Atender = marcar o pedido como "em atendimento" no painel: sai de "open" e para de avisar.
 */
class LembrarPedidosPendentes extends Command
{
    protected $signature = 'pedidos:lembrar
        {--minutos=10 : por quanto tempo insistir depois do pedido}
        {--rodadas=1 : quantas vezes conferir por execucao (o agendador roda 1x por minuto)}
        {--a-cada=30 : segundos entre as rodadas}';

    protected $description = 'Repete o aviso no celular dos pedidos que ainda ninguem atendeu';

    public function handle(PushService $push, AtendimentoService $atendimento)
    {
        $rodadas = max(1, (int) $this->option('rodadas'));

        for ($i = 0; $i < $rodadas; $i++) {
            // Cada rodada consulta o banco de novo: pedido atendido entre uma e outra para de avisar na hora
            $this->lembrar($push, $atendimento);

            if ($i < $rodadas - 1) {
                sleep(max(0, (int) $this->option('a-cada')));
            }
        }

        return 0;
    }

    private function lembrar(PushService $push, AtendimentoService $atendimento): void
    {
        $limite = (int) $this->option('minutos');

        // 25s: o pedido recem-chegado ja foi avisado, o primeiro lembrete sai com ~30s de espera
        $pendentes = Order::withoutGlobalScopes()
            ->where('status', 'open')
            ->where('created_at', '<=', now()->subSeconds(25))
            ->where('created_at', '>=', now()->subMinutes($limite))
            ->with('table', 'products', 'attendant')
            ->get();

        $enviados = 0;

        foreach ($pendentes as $pedido) {
            $atendenteUserId = null;
            if ($pedido->attendant && ($tenant = Tenant::find($pedido->tenant_id)) && $atendimento->deveNotificar($tenant, $pedido->attendant)) {
                $atendenteUserId = $pedido->attendant->user_id;
            }

            $minutos = $pedido->created_at->diffInMinutes(now());
            $enviados += $push->notificarPedido($pedido, $atendenteUserId, $push->payloadDeLembrete($pedido, $minutos));
        }

        $this->info("Pedidos pendentes: {$pendentes->count()} · avisos enviados: {$enviados}");
    }
}
