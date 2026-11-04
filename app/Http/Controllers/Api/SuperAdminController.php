<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Health\ServiceChecks;
use Illuminate\Support\Facades\DB;

/**
 * Tela de super admin (so e-mails de config/acl.php). Enxerga TODAS as barracas: por isso usa DB direto,
 * sem o escopo por tenant dos models.
 */
class SuperAdminController extends Controller
{
    public function saude(ServiceChecks $checks)
    {
        $servicos = $checks->todas();
        $pior = collect($servicos)->pluck('status')->contains('erro') ? 'erro' : (collect($servicos)->pluck('status')->contains('aviso') ? 'aviso' : 'ok');

        return response()->json([
            'gerado_em' => now()->toIso8601String(),
            'geral' => $pior,
            'servicos' => $servicos,
            'erros' => $checks->erros(),
            'host' => $checks->host(),
        ]);
    }

    public function barracas()
    {
        $hoje = now()->startOfDay();
        $mes = now()->startOfMonth();

        $contar = fn ($tabela, $coluna = 'tenant_id') => DB::table($tabela)->select($coluna . ' as t', DB::raw('count(*) as n'))->groupBy($coluna)->pluck('n', 't');

        $mesas = $contar('tables');
        $produtos = DB::table('products')->where('active', 1)->select('tenant_id as t', DB::raw('count(*) as n'))->groupBy('tenant_id')->pluck('n', 't');
        $usuarios = $contar('users');
        $atendentes = DB::table('atendentes')->where('ativo', 1)->select('tenant_id as t', DB::raw('count(*) as n'))->groupBy('tenant_id')->pluck('n', 't');

        $pedidos = DB::table('orders')
            ->select('tenant_id as t',
                DB::raw('count(*) as total'),
                DB::raw("sum(case when status = 'done' then 1 else 0 end) as entregues"),
                DB::raw("sum(case when status = 'done' then total else 0 end) as vendido_total"),
                DB::raw("sum(case when status in ('open','working','delivering') and created_at >= '" . now()->subHours(24)->toDateTimeString() . "' then 1 else 0 end) as em_andamento"),
                DB::raw("sum(case when status = 'done' and created_at >= '" . $hoje->toDateTimeString() . "' then total else 0 end) as vendido_hoje"),
                DB::raw("sum(case when created_at >= '" . $hoje->toDateTimeString() . "' then 1 else 0 end) as pedidos_hoje"),
                DB::raw("sum(case when status = 'done' and created_at >= '" . $mes->toDateTimeString() . "' then total else 0 end) as vendido_mes"),
                DB::raw('max(created_at) as ultimo_pedido'))
            ->groupBy('tenant_id')->get()->keyBy('t');

        // ultimo uso do painel: o token mais recente de qualquer usuario da barraca
        $atividade = DB::table('personal_access_tokens as k')
            ->join('users as u', function ($j) {
                $j->on('u.id', '=', 'k.tokenable_id')->where('k.tokenable_type', '=', \App\Models\User::class);
            })
            ->select('u.tenant_id as t', DB::raw('max(k.last_used_at) as ultimo_uso'))
            ->groupBy('u.tenant_id')->pluck('ultimo_uso', 't');

        $celulares = DB::table('push_subscriptions as p')->join('users as u', 'u.id', '=', 'p.user_id')
            ->select('u.tenant_id as t', DB::raw('count(*) as n'))->groupBy('u.tenant_id')->pluck('n', 't');

        $planos = DB::table('plans')->get(['id', 'name', 'price'])->keyBy('id');

        $barracas = DB::table('tenants')->orderBy('id')->get()->map(function ($t) use ($mesas, $produtos, $usuarios, $atendentes, $pedidos, $atividade, $celulares, $planos) {
            $p = $pedidos->get($t->id);
            $plano = $planos->get($t->plan_id);

            return [
                'id' => $t->id,
                'nome' => $t->name,
                'url' => $t->url,
                'loja' => rtrim(config('services.health.loja_url', 'https://delivery.comunidadeppg.com.br'), '/') . '/loja/' . $t->url,
                'local' => $t->location_label,
                'plano' => $plano ? ['nome' => $plano->name, 'preco' => (float) $plano->price] : null,
                'assinatura_ativa' => (bool) $t->subscription_active,
                'ativa' => $t->active === 'Y',
                'criada_em' => $t->created_at,
                'guarda_sois' => (int) ($mesas[$t->id] ?? 0),
                'produtos' => (int) ($produtos[$t->id] ?? 0),
                'usuarios' => (int) ($usuarios[$t->id] ?? 0),
                'atendentes' => (int) ($atendentes[$t->id] ?? 0),
                'celulares_com_aviso' => (int) ($celulares[$t->id] ?? 0),
                'pedidos_total' => (int) ($p->total ?? 0),
                'pedidos_hoje' => (int) ($p->pedidos_hoje ?? 0),
                'pedidos_em_andamento' => (int) ($p->em_andamento ?? 0),
                'vendido_hoje' => round((float) ($p->vendido_hoje ?? 0), 2),
                'vendido_mes' => round((float) ($p->vendido_mes ?? 0), 2),
                'vendido_total' => round((float) ($p->vendido_total ?? 0), 2),
                'ultimo_pedido' => $p->ultimo_pedido ?? null,
                'ultimo_uso_painel' => $atividade[$t->id] ?? null,
            ];
        })->values();

        return response()->json([
            'gerado_em' => now()->toIso8601String(),
            'totais' => [
                'barracas' => $barracas->count(),
                'ativas' => $barracas->where('assinatura_ativa', true)->count(),
                'pedidos_hoje' => $barracas->sum('pedidos_hoje'),
                'vendido_hoje' => round($barracas->sum('vendido_hoje'), 2),
                'vendido_mes' => round($barracas->sum('vendido_mes'), 2),
                'pedidos_em_andamento' => $barracas->sum('pedidos_em_andamento'),
                'mensalidades' => round($barracas->where('assinatura_ativa', true)->sum(fn ($b) => $b['plano']['preco'] ?? 0), 2),
                'clientes' => (int) DB::table('clients')->count(),
            ],
            'barracas' => $barracas,
        ]);
    }
}
