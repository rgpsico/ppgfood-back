<?php

namespace Tests\Feature\Api;

use App\Models\Atendente;
use App\Models\Order;
use App\Models\PushSubscription;
use App\Models\Table;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Push\PushSenderInterface;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Pedido que ninguem atendeu continua avisando; atender (status "working") para o aviso.
 */
class LembretePedidoTest extends TestCase
{
    private $enviados = [];

    protected function setUp(): void
    {
        parent::setUp();

        $teste = $this;
        $this->app->instance(PushSenderInterface::class, new class($teste) implements PushSenderInterface {
            private $t;

            public function __construct($t)
            {
                $this->t = $t;
            }

            public function enviar(PushSubscription $inscricao, array $payload): bool
            {
                $this->t->registrar($inscricao->user_id, $payload);

                return true;
            }
        });
    }

    public function registrar($userId, $payload)
    {
        $this->enviados[] = ['user_id' => $userId, 'payload' => $payload];
    }

    private function gerenteComCelular(Tenant $tenant): User
    {
        $user = factory(User::class)->create(['tenant_id' => $tenant->id]);
        $endpoint = 'https://push.exemplo.com/' . $user->id;
        PushSubscription::create([
            'user_id' => $user->id, 'endpoint_hash' => PushSubscription::hashDoEndpoint($endpoint),
            'endpoint' => $endpoint, 'p256dh' => 'k', 'auth' => 'a',
        ]);

        return $user;
    }

    private function pedidoHa(int $segundos, string $status = 'open', ?Tenant $tenant = null): Order
    {
        $tenant = $tenant ?: factory(Tenant::class)->create();
        $pedido = Order::create(['tenant_id' => $tenant->id, 'identify' => uniqid('p'), 'total' => 20, 'status' => $status]);
        $pedido->forceFill(['created_at' => now()->subSeconds($segundos)])->save();

        return $pedido;
    }

    public function testPedidoSemAtendimentoRecebeLembrete()
    {
        $tenant = factory(Tenant::class)->create();
        $gerente = $this->gerenteComCelular($tenant);
        $pedido = $this->pedidoHa(120, 'open', $tenant);

        $this->artisan('pedidos:lembrar')->assertExitCode(0);

        $this->assertSame([$gerente->id], array_column($this->enviados, 'user_id'));
        $this->assertStringContainsString('Pedido esperando', $this->enviados[0]['payload']['title']);
        $this->assertStringContainsString('2 min', $this->enviados[0]['payload']['body']);
        $this->assertSame($pedido->identify, $this->enviados[0]['payload']['tag']);
    }

    public function testComDuasRodadasAvisaDuasVezesPorMinuto()
    {
        $tenant = factory(Tenant::class)->create();
        $this->gerenteComCelular($tenant);
        $this->pedidoHa(40, 'open', $tenant);

        $this->artisan('pedidos:lembrar --rodadas=2 --a-cada=0')->assertExitCode(0);

        $this->assertCount(2, $this->enviados);
    }

    public function testPedidoRecemChegadoAindaNaoRecebeLembrete()
    {
        $tenant = factory(Tenant::class)->create();
        $this->gerenteComCelular($tenant);
        $this->pedidoHa(10, 'open', $tenant);

        $this->artisan('pedidos:lembrar')->assertExitCode(0);

        $this->assertSame([], $this->enviados);
    }

    public function testPedidoAtendidoOuEntregueNaoRecebeLembrete()
    {
        $tenant = factory(Tenant::class)->create();
        $this->gerenteComCelular($tenant);
        $this->pedidoHa(180, 'working', $tenant);
        $this->pedidoHa(180, 'done', $tenant);
        $this->pedidoHa(180, 'canceled', $tenant);

        $this->artisan('pedidos:lembrar')->assertExitCode(0);

        $this->assertSame([], $this->enviados);
    }

    public function testPedidoMuitoAntigoParaDeInsistir()
    {
        $tenant = factory(Tenant::class)->create();
        $this->gerenteComCelular($tenant);
        $this->pedidoHa(11 * 60, 'open', $tenant);

        $this->artisan('pedidos:lembrar')->assertExitCode(0);

        $this->assertSame([], $this->enviados);
    }

    public function testLembreteVaiSoParaABarracaDoPedido()
    {
        $a = factory(Tenant::class)->create();
        $b = factory(Tenant::class)->create();
        $gerenteA = $this->gerenteComCelular($a);
        $this->gerenteComCelular($b);
        $this->pedidoHa(120, 'open', $a);

        $this->artisan('pedidos:lembrar')->assertExitCode(0);

        $this->assertSame([$gerenteA->id], array_column($this->enviados, 'user_id'));
    }

    public function testAtenderOPedidoNoPainelParaOsLembretes()
    {
        $tenant = factory(Tenant::class)->create();
        $gerente = $this->gerenteComCelular($tenant);
        $pedido = $this->pedidoHa(120, 'open', $tenant);
        Sanctum::actingAs($gerente);

        $this->patchJson("/api/v1/staff/orders/{$pedido->identify}/status", ['status' => 'working'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'working');

        $this->artisan('pedidos:lembrar')->assertExitCode(0);

        $this->assertSame([], $this->enviados);
    }

    public function testQuadroTraOHorarioDoPedidoComFusoParaOAlarme()
    {
        $tenant = factory(Tenant::class)->create();
        $gerente = $this->gerenteComCelular($tenant);
        $mesa = factory(Table::class)->create(['tenant_id' => $tenant->id]);
        $pedido = $this->pedidoHa(30, 'open', $tenant);
        $pedido->update(['table_id' => $mesa->id]);
        Sanctum::actingAs($gerente);

        $criado = $this->getJson('/api/v1/staff/orders-board')->assertStatus(200)->json('tables.0.order.created_at');

        $this->assertNotNull($criado);
        $this->assertEqualsWithDelta(30, now()->diffInSeconds(\Carbon\Carbon::parse($criado)), 5);
    }

    public function testComandoEstaNoAgendador()
    {
        $comandos = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->pluck('command')->implode(' ');

        $this->assertStringContainsString('pedidos:lembrar', $comandos);
    }
}
