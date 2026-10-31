<?php

namespace App\Services;

use App\Models\Atendente;
use App\Models\Order;
use Carbon\Carbon;

/**
 * Vendas por atendente. So pedido concluido (done) conta como venda: pendentes e cancelados
 * aparecem separados para nao inflar o valor vendido.
 */
class AtendenteReportService
{
    const CONCLUIDOS = [Order::DELIVERED_STATUS];
    const PENDENTES = Order::ACTIVE_STATUSES;          // open, working, delivering
    const CANCELADOS = ['canceled', 'rejected'];

    public function resumo(int $tenantId, Carbon $de, Carbon $ate)
    {
        $linhas = Order::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('attendant_id')
            ->whereBetween('created_at', [$de->copy()->startOfDay(), $ate->copy()->endOfDay()])
            ->selectRaw('attendant_id, status, COUNT(*) as qtd, COALESCE(SUM(total), 0) as valor')
            ->groupBy('attendant_id', 'status')
            ->get()
            ->groupBy('attendant_id');

        // "Atendendo agora" = tem pedido pendente, independente do periodo filtrado
        $atendendoAgora = Order::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('attendant_id')
            ->whereIn('status', self::PENDENTES)
            ->distinct()
            ->pluck('attendant_id')
            ->flip();

        return Atendente::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('ativo')
            ->orderBy('nome')
            ->get()
            ->map(function (Atendente $a) use ($linhas, $atendendoAgora) {
                $por = $linhas->get($a->id, collect());
                $soma = fn (array $status, string $campo) => (float) $por->whereIn('status', $status)->sum($campo);

                return (object) [
                    'atendente' => $a,
                    'atendendo_agora' => $atendendoAgora->has($a->id),
                    'pedidos' => (int) $por->sum('qtd'),
                    'concluidos' => (int) $soma(self::CONCLUIDOS, 'qtd'),
                    'vendido' => $soma(self::CONCLUIDOS, 'valor'),
                    'pendentes' => (int) $soma(self::PENDENTES, 'qtd'),
                    'valor_pendente' => $soma(self::PENDENTES, 'valor'),
                    'cancelados' => (int) $soma(self::CANCELADOS, 'qtd'),
                    'valor_cancelado' => $soma(self::CANCELADOS, 'valor'),
                ];
            });
    }
}
