<?php

namespace Tests\Feature\Api;

use App\Events\OrderStatusChanged;
use App\Models\Atendente;
use App\Models\Order;
use App\Models\Product;
use App\Models\Table;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Relatorios do dono: so o gerente, so da propria barraca, "vendido" = entregue.
 */
class RelatorioTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // quarta-feira 15h (hora de Sao Paulo, o fuso do app)
        Carbon::setTestNow(Carbon::parse('2026-11-04 15:00:00', 'America/Sao_Paulo'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function gerente(Tenant $tenant): User
    {
        return factory(User::class)->create(['tenant_id' => $tenant->id]);
    }

    private function pedido(Tenant $tenant, string $quando, string $status = 'done', float $total = 20, array $extra = []): Order
    {
        $pedido = Order::create(array_merge([
            'tenant_id' => $tenant->id, 'identify' => uniqid('p'), 'total' => $total, 'status' => $status,
        ], $extra));
        $criado = Carbon::parse($quando, 'America/Sao_Paulo');
        $pedido->forceFill(['created_at' => $criado, 'updated_at' => $criado->copy()->addMinutes(6)])->save();

        return $pedido;
    }

    private function relatorio(User $user, string $query = '')
    {
        Sanctum::actingAs($user);

        return $this->getJson('/api/v1/staff/reports' . $query);
    }

    public function testSemLoginOuComoAtendenteNaoVeRelatorio()
    {
        $tenant = factory(Tenant::class)->create();
        $this->getJson('/api/v1/staff/reports')->assertStatus(401);

        $userAtendente = $this->gerente($tenant);
        Atendente::create(['tenant_id' => $tenant->id, 'nome' => 'João', 'ativo' => true, 'user_id' => $userAtendente->id]);

        $this->relatorio($userAtendente)->assertStatus(403);
    }

    public function testResumoDeHojeContaSoEntreguesComoVendido()
    {
        $tenant = factory(Tenant::class)->create();
        $this->pedido($tenant, '2026-11-04 10:00', 'done', 30);
        $this->pedido($tenant, '2026-11-04 11:30', 'done', 50);
        $this->pedido($tenant, '2026-11-04 12:00', 'open', 20);
        $this->pedido($tenant, '2026-11-04 12:10', 'working', 15);
        $this->pedido($tenant, '2026-11-04 13:00', 'canceled', 99);

        $r = $this->relatorio($this->gerente($tenant))->assertStatus(200)->json();

        $this->assertSame(80.0, (float) $r['resumo']['vendido']);
        $this->assertSame(2, $r['resumo']['pedidos_entregues']);
        $this->assertSame(2, $r['resumo']['pedidos_em_andamento']);
        $this->assertSame(1, $r['resumo']['pedidos_cancelados']);
        $this->assertSame(35.0, (float) $r['resumo']['a_receber']);
        $this->assertSame(40.0, (float) $r['resumo']['ticket_medio']);
        $this->assertSame(6, $r['resumo']['tempo_medio_entrega_min']);
        $this->assertSame([], $r['por_dia']);
    }

    public function testNaoMisturaDadosDeOutraBarraca()
    {
        $a = factory(Tenant::class)->create();
        $b = factory(Tenant::class)->create();
        $this->pedido($a, '2026-11-04 10:00', 'done', 30);
        $this->pedido($b, '2026-11-04 10:00', 'done', 1000);

        $r = $this->relatorio($this->gerente($a))->json();

        $this->assertSame(30.0, (float) $r['resumo']['vendido']);
        $this->assertSame(1, $r['resumo']['pedidos_entregues']);
    }

    public function testPeriodosHojeOntemSemanaEMes()
    {
        $tenant = factory(Tenant::class)->create();
        $this->pedido($tenant, '2026-11-04 09:00', 'done', 10);   // hoje
        $this->pedido($tenant, '2026-11-03 23:50', 'done', 20);   // ontem (fim do dia, nao vira hoje)
        $this->pedido($tenant, '2026-10-31 12:00', 'done', 40);   // 4 dias atras (mes passado)
        $this->pedido($tenant, '2026-10-20 12:00', 'done', 80);   // fora da semana
        $gerente = $this->gerente($tenant);

        $this->assertSame(10.0, (float) $this->relatorio($gerente, '?period=today')->json('resumo.vendido'));
        $this->assertSame(20.0, (float) $this->relatorio($gerente, '?period=yesterday')->json('resumo.vendido'));
        $this->assertSame(70.0, (float) $this->relatorio($gerente, '?period=7d')->json('resumo.vendido'));
        $this->assertSame(10.0 + 20.0, (float) $this->relatorio($gerente, '?period=month')->json('resumo.vendido'));
    }

    public function testPeriodoPersonalizadoTemVendasPorDia()
    {
        $tenant = factory(Tenant::class)->create();
        $this->pedido($tenant, '2026-11-02 10:00', 'done', 10);
        $this->pedido($tenant, '2026-11-02 11:00', 'done', 15);
        $this->pedido($tenant, '2026-11-04 10:00', 'done', 5);

        $r = $this->relatorio($this->gerente($tenant), '?period=custom&from=2026-11-01&to=2026-11-04')->assertStatus(200)->json();

        $this->assertSame(4, $r['periodo']['dias']);
        $this->assertSame(['2026-11-01', '2026-11-02', '2026-11-03', '2026-11-04'], array_column($r['por_dia'], 'data'));
        $this->assertSame([0.0, 25.0, 0.0, 5.0], array_map('floatval', array_column($r['por_dia'], 'vendido')));
    }

    public function testValidaPeriodo()
    {
        $gerente = $this->gerente(factory(Tenant::class)->create());

        $this->relatorio($gerente, '?period=custom')->assertStatus(422);
        $this->relatorio($gerente, '?period=custom&from=2026-11-05&to=2026-11-01')->assertStatus(422);
        $this->relatorio($gerente, '?period=custom&from=2026-01-01&to=2026-11-01')->assertStatus(422);
        $this->relatorio($gerente, '?period=ano')->assertStatus(422);
    }

    public function testVendasPorHoraProdutosAtendentesEPontos()
    {
        $tenant = factory(Tenant::class)->create();
        $joao = Atendente::create(['tenant_id' => $tenant->id, 'nome' => 'João', 'ativo' => true]);
        $mesa = factory(Table::class)->create(['tenant_id' => $tenant->id, 'identify' => 'Guarda-sol 4']);
        $caipi = factory(Product::class)->create(['tenant_id' => $tenant->id, 'title' => 'Caipirinha']);
        $coco = factory(Product::class)->create(['tenant_id' => $tenant->id, 'title' => 'Coco']);

        $p1 = $this->pedido($tenant, '2026-11-04 10:15', 'done', 36, ['attendant_id' => $joao->id, 'table_id' => $mesa->id]);
        $p1->products()->attach($caipi->id, ['qty' => 2, 'price' => 18]);
        $p2 = $this->pedido($tenant, '2026-11-04 10:45', 'done', 12);
        $p2->products()->attach($coco->id, ['qty' => 1, 'price' => 12]);
        $p3 = $this->pedido($tenant, '2026-11-04 14:00', 'done', 18, ['attendant_id' => $joao->id, 'table_id' => $mesa->id]);
        $p3->products()->attach($caipi->id, ['qty' => 1, 'price' => 18]);
        // pedido em andamento nao entra em produto vendido
        $p4 = $this->pedido($tenant, '2026-11-04 14:10', 'open', 18);
        $p4->products()->attach($coco->id, ['qty' => 5, 'price' => 12]);

        $r = $this->relatorio($this->gerente($tenant))->json();

        $this->assertCount(24, $r['por_hora']);
        $this->assertSame(48.0, (float) $r['por_hora'][10]['vendido']);
        $this->assertSame(2, $r['por_hora'][10]['pedidos']);
        $this->assertSame(18.0, (float) $r['por_hora'][14]['vendido']);

        $this->assertSame('Caipirinha', $r['produtos'][0]['produto']);
        $this->assertSame(3, $r['produtos'][0]['quantidade']);
        $this->assertSame(54.0, (float) $r['produtos'][0]['vendido']);
        $this->assertSame(1, $r['produtos'][1]['quantidade']);

        $this->assertSame('João', $r['por_atendente'][0]['atendente']);
        $this->assertSame(54.0, (float) $r['por_atendente'][0]['vendido']);
        $this->assertSame('Sem atendente', $r['por_atendente'][1]['atendente']);

        $this->assertSame('Guarda-sol 4', $r['por_ponto'][0]['ponto']);
        $this->assertSame('Sem guarda-sol (retirada/delivery)', $r['por_ponto'][1]['ponto']);
    }

    public function testCompararComPeriodoAnterior()
    {
        $tenant = factory(Tenant::class)->create();
        $this->pedido($tenant, '2026-11-04 10:00', 'done', 100);
        $this->pedido($tenant, '2026-11-03 10:00', 'done', 40);

        $r = $this->relatorio($this->gerente($tenant))->json('resumo');

        $this->assertSame(100.0, (float) $r['vendido']);
        $this->assertSame(40.0, (float) $r['vendido_periodo_anterior']);
    }

    public function testMudarOStatusAvisaOsOutrosAparelhos()
    {
        Event::fake([OrderStatusChanged::class]);
        $tenant = factory(Tenant::class)->create();
        $gerente = $this->gerente($tenant);
        $pedido = $this->pedido($tenant, '2026-11-04 14:00', 'open', 20);
        Sanctum::actingAs($gerente);

        $this->patchJson("/api/v1/staff/orders/{$pedido->identify}/status", ['status' => 'working'])->assertStatus(200);

        Event::assertDispatched(OrderStatusChanged::class, function ($e) use ($pedido, $tenant) {
            $canais = array_map(fn ($c) => $c->name, $e->broadcastOn());

            return $e->order['identify'] === $pedido->identify
                && $e->order['status'] === 'working'
                && $canais === ['private-order-created.' . $tenant->id];
        });
    }

    public function testStatusDoPedidoDeAtendenteTambemVaiParaOCanalDele()
    {
        $tenant = factory(Tenant::class)->create();
        $userAtendente = $this->gerente($tenant);
        $atendente = Atendente::create(['tenant_id' => $tenant->id, 'nome' => 'João', 'ativo' => true, 'user_id' => $userAtendente->id]);
        $pedido = $this->pedido($tenant, '2026-11-04 14:00', 'open', 20, ['attendant_id' => $atendente->id]);

        $canais = array_map(fn ($c) => $c->name, (new OrderStatusChanged($pedido->fresh()))->broadcastOn());

        $this->assertSame(['private-order-created.' . $tenant->id, 'private-atendente.' . $userAtendente->id], $canais);
    }
}
