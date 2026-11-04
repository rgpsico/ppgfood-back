<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Atendente;
use App\Models\Order;
use App\Models\Table;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Relatorios do dono da barraca (painel, aba "Relatorios"). Sempre da propria barraca (o escopo por
 * tenant do Order vem do usuario logado) e so para o gerente: atendente nao ve o faturamento da casa.
 *
 * "Vendido" = pedidos ENTREGUES (status done): o pagamento e na entrega. O que ainda esta em andamento
 * aparece a parte, como "a receber". Datas na hora de Sao Paulo (timezone do app, igual ao banco).
 */
class ReportController extends Controller
{
    const ANDAMENTO = ['open', 'working', 'delivering'];
    const CANCELADOS = ['canceled', 'rejected'];

    public function show(Request $request)
    {
        if ($request->user()->atendente) {
            return response()->json(['message' => 'Acesso restrito ao gerente.'], 403);
        }

        $request->validate([
            'period' => 'nullable|in:today,yesterday,7d,month,custom',
            'from' => 'required_if:period,custom|nullable|date_format:Y-m-d',
            'to' => 'required_if:period,custom|nullable|date_format:Y-m-d|after_or_equal:from',
        ]);

        [$inicio, $fim, $periodo] = $this->intervalo($request);

        if ($inicio->diffInDays($fim) > 92) {
            return response()->json(['message' => 'Escolha um periodo de ate 3 meses.'], 422);
        }

        $tenantId = $request->user()->tenant_id;
        $pedidos = Order::whereBetween('created_at', [$inicio, $fim])
            ->get(['id', 'status', 'total', 'created_at', 'updated_at', 'attendant_id', 'table_id']);

        $entregues = $pedidos->where('status', Order::DELIVERED_STATUS);
        $andamento = $pedidos->whereIn('status', self::ANDAMENTO);

        $vendido = round((float) $entregues->sum('total'), 2);

        // periodo anterior com o mesmo tamanho: mostra se o dia/semana esta melhor ou pior
        $dias = $inicio->copy()->startOfDay()->diffInDays($fim->copy()->startOfDay()) + 1;
        $vendidoAnterior = round((float) Order::where('status', Order::DELIVERED_STATUS)
            ->whereBetween('created_at', [$inicio->copy()->subDays($dias), $fim->copy()->subDays($dias)])
            ->sum('total'), 2);

        return response()->json([
            'periodo' => [
                'tipo' => $periodo,
                'de' => $inicio->toDateString(),
                'ate' => $fim->toDateString(),
                'dias' => $dias,
            ],
            'atualizado_em' => now()->toIso8601String(),
            'resumo' => [
                'vendido' => $vendido,
                'vendido_periodo_anterior' => $vendidoAnterior,
                'pedidos_entregues' => $entregues->count(),
                'pedidos_em_andamento' => $andamento->count(),
                'pedidos_cancelados' => $pedidos->whereIn('status', self::CANCELADOS)->count(),
                'a_receber' => round((float) $andamento->sum('total'), 2),
                'ticket_medio' => $entregues->count() ? round($vendido / $entregues->count(), 2) : 0,
                'tempo_medio_entrega_min' => $this->tempoMedio($entregues),
            ],
            'por_hora' => $this->porHora($entregues),
            'por_dia' => $dias > 1 ? $this->porDia($entregues, $inicio, $dias) : [],
            'produtos' => $this->produtos($tenantId, $inicio, $fim),
            'por_atendente' => $this->porAtendente($entregues),
            'por_ponto' => $this->porPonto($entregues),
        ]);
    }

    private function intervalo(Request $request): array
    {
        $periodo = $request->input('period', 'today');
        $hoje = now()->startOfDay();

        switch ($periodo) {
            case 'yesterday':
                return [$hoje->copy()->subDay(), $hoje->copy()->subDay()->endOfDay(), $periodo];
            case '7d':
                return [$hoje->copy()->subDays(6), now()->endOfDay(), $periodo];
            case 'month':
                return [now()->startOfMonth(), now()->endOfDay(), $periodo];
            case 'custom':
                return [Carbon::createFromFormat('Y-m-d', $request->from)->startOfDay(), Carbon::createFromFormat('Y-m-d', $request->to)->endOfDay(), $periodo];
            default:
                return [$hoje, now()->endOfDay(), 'today'];
        }
    }

    /** Minutos entre fazer o pedido e marcar como entregue (ignora marcacao tardia de mais de 6 h). */
    private function tempoMedio($entregues): ?int
    {
        $minutos = $entregues
            ->map(fn ($o) => $o->created_at->diffInMinutes($o->updated_at))
            ->filter(fn ($m) => $m <= 360);

        return $minutos->isEmpty() ? null : (int) round($minutos->avg());
    }

    private function porHora($entregues): array
    {
        $horas = [];
        for ($h = 0; $h < 24; $h++) {
            $horas[$h] = ['hora' => $h, 'vendido' => 0.0, 'pedidos' => 0];
        }

        foreach ($entregues as $pedido) {
            $h = (int) $pedido->created_at->format('G');
            $horas[$h]['vendido'] += (float) $pedido->total;
            $horas[$h]['pedidos']++;
        }

        return array_values(array_map(function ($linha) {
            $linha['vendido'] = round($linha['vendido'], 2);

            return $linha;
        }, $horas));
    }

    private function porDia($entregues, Carbon $inicio, int $dias): array
    {
        $porData = $entregues->groupBy(fn ($o) => $o->created_at->toDateString());
        $linhas = [];

        for ($i = 0; $i < $dias; $i++) {
            $dia = $inicio->copy()->startOfDay()->addDays($i)->toDateString();
            $grupo = $porData->get($dia, collect());
            $linhas[] = ['data' => $dia, 'vendido' => round((float) $grupo->sum('total'), 2), 'pedidos' => $grupo->count()];
        }

        return $linhas;
    }

    private function produtos(int $tenantId, Carbon $inicio, Carbon $fim): array
    {
        return DB::table('order_product as op')
            ->join('orders as o', 'o.id', '=', 'op.order_id')
            ->join('products as p', 'p.id', '=', 'op.product_id')
            ->where('o.tenant_id', $tenantId)
            ->where('o.status', Order::DELIVERED_STATUS)
            ->whereBetween('o.created_at', [$inicio, $fim])
            ->groupBy('p.id', 'p.title')
            ->selectRaw('p.title as produto, SUM(op.qty) as quantidade, ROUND(SUM(op.qty * op.price), 2) as vendido')
            ->orderByDesc('quantidade')
            ->orderBy('p.title')
            ->limit(10)
            ->get()
            ->map(fn ($l) => ['produto' => $l->produto, 'quantidade' => (int) $l->quantidade, 'vendido' => (float) $l->vendido])
            ->all();
    }

    private function porAtendente($entregues): array
    {
        $nomes = Atendente::pluck('nome', 'id');

        return $entregues->groupBy('attendant_id')
            ->map(fn ($grupo, $id) => [
                'atendente' => $id ? ($nomes[$id] ?? 'Atendente removido') : 'Sem atendente',
                'pedidos' => $grupo->count(),
                'vendido' => round((float) $grupo->sum('total'), 2),
            ])
            ->sortByDesc('vendido')->values()->all();
    }

    private function porPonto($entregues): array
    {
        $nomes = Table::pluck('identify', 'id');

        return $entregues->groupBy('table_id')
            ->map(fn ($grupo, $id) => [
                'ponto' => $id ? ($nomes[$id] ?? 'Ponto removido') : 'Sem guarda-sol (retirada/delivery)',
                'pedidos' => $grupo->count(),
                'vendido' => round((float) $grupo->sum('total'), 2),
            ])
            ->sortByDesc('vendido')->values()->take(10)->all();
    }
}
