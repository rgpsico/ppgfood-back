<?php

namespace Tests\Feature\Api;

use App\Models\Atendente;
use App\Models\Order;
use App\Models\Product;
use App\Models\PushSubscription;
use App\Models\Table;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Push\PushSenderInterface;
use App\Services\Push\PushService;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Aviso de pedido novo no celular: quem recebe, cadastro dos celulares e o pedido que nunca depende do push.
 */
class PushPedidoTest extends TestCase
{
    private $enviados = [];
    private $resposta = true;
    private $explodir = false;

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
                if ($this->t->explodir()) {
                    throw new \RuntimeException('servico de push fora do ar');
                }
                $this->t->registrar($inscricao->user_id, $payload);

                return $this->t->resposta();
            }
        });
    }

    public function registrar($userId, $payload)
    {
        $this->enviados[] = ['user_id' => $userId, 'payload' => $payload];
    }

    public function resposta()
    {
        return $this->resposta;
    }

    public function explodir()
    {
        return $this->explodir;
    }

    private function usuario(Tenant $tenant): User
    {
        return factory(User::class)->create(['tenant_id' => $tenant->id]);
    }

    private function celular(User $user, string $sufixo = 'a'): PushSubscription
    {
        $endpoint = 'https://push.exemplo.com/' . $user->id . $sufixo;

        return PushSubscription::create([
            'user_id' => $user->id,
            'endpoint_hash' => PushSubscription::hashDoEndpoint($endpoint),
            'endpoint' => $endpoint,
            'p256dh' => 'chave',
            'auth' => 'auth',
        ]);
    }

    private function pedido(Tenant $tenant, array $extra = []): Order
    {
        return Order::create(array_merge([
            'tenant_id' => $tenant->id, 'identify' => uniqid('p'), 'total' => 36, 'status' => 'open',
        ], $extra));
    }

    private function inscricaoJson(string $endpoint = 'https://push.exemplo.com/abc'): array
    {
        return ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'chave-publica', 'auth' => 'segredo']];
    }

    // ---- cadastro dos celulares -----------------------------------------------------------------------------

    public function testSemLoginNaoCadastraCelular()
    {
        $this->postJson('/api/v1/staff/push/subscribe', $this->inscricaoJson())->assertStatus(401);
        $this->getJson('/api/v1/staff/push/key')->assertStatus(401);
    }

    public function testCadastraCelularDoUsuarioLogado()
    {
        $user = $this->usuario(factory(Tenant::class)->create());
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/staff/push/subscribe', $this->inscricaoJson())->assertStatus(201);

        $this->assertDatabaseHas('push_subscriptions', ['user_id' => $user->id, 'endpoint' => 'https://push.exemplo.com/abc']);
    }

    public function testMesmoCelularNaoDuplicaEPassaParaQuemLogouPorUltimo()
    {
        $tenant = factory(Tenant::class)->create();
        $primeiro = $this->usuario($tenant);
        $segundo = $this->usuario($tenant);

        Sanctum::actingAs($primeiro);
        $this->postJson('/api/v1/staff/push/subscribe', $this->inscricaoJson())->assertStatus(201);
        Sanctum::actingAs($segundo);
        $this->postJson('/api/v1/staff/push/subscribe', $this->inscricaoJson())->assertStatus(201);

        $this->assertSame(1, PushSubscription::count());
        $this->assertSame($segundo->id, PushSubscription::first()->user_id);
    }

    public function testValidaOsDadosDoCelular()
    {
        Sanctum::actingAs($this->usuario(factory(Tenant::class)->create()));

        $this->postJson('/api/v1/staff/push/subscribe', ['endpoint' => 'isso-nao-e-url', 'keys' => []])->assertStatus(422);
        $this->assertSame(0, PushSubscription::count());
    }

    public function testDesativaSoOProprioCelular()
    {
        $tenant = factory(Tenant::class)->create();
        $eu = $this->usuario($tenant);
        $outro = $this->usuario($tenant);
        $meu = $this->celular($eu);
        $dele = $this->celular($outro);
        Sanctum::actingAs($eu);

        // tentar apagar o celular de outro usuario nao apaga nada
        $this->deleteJson('/api/v1/staff/push/subscribe', ['endpoint' => $dele->endpoint])->assertStatus(200);
        $this->assertDatabaseHas('push_subscriptions', ['id' => $dele->id]);

        $this->deleteJson('/api/v1/staff/push/subscribe', ['endpoint' => $meu->endpoint])->assertStatus(200);
        $this->assertDatabaseMissing('push_subscriptions', ['id' => $meu->id]);
    }

    public function testChavePublicaSoQuandoConfigurada()
    {
        Sanctum::actingAs($this->usuario(factory(Tenant::class)->create()));

        config(['services.vapid.public' => null]);
        $this->getJson('/api/v1/staff/push/key')->assertStatus(503);

        config(['services.vapid.public' => 'CHAVE-PUBLICA']);
        $this->getJson('/api/v1/staff/push/key')->assertStatus(200)->assertJsonPath('public_key', 'CHAVE-PUBLICA');
    }

    public function testAvisoDeTesteVaSoParaOsCelularesDoProprioUsuario()
    {
        $tenant = factory(Tenant::class)->create();
        $eu = $this->usuario($tenant);
        $colega = $this->usuario($tenant);
        $this->celular($eu);
        $this->celular($colega);
        Sanctum::actingAs($eu);

        $this->postJson('/api/v1/staff/push/test')->assertStatus(200)->assertJsonPath('enviados', 1);

        $this->assertSame([$eu->id], array_column($this->enviados, 'user_id'));
        $this->assertSame('Teste de aviso', $this->enviados[0]['payload']['title']);
    }

    public function testAvisoDeTesteSemCelularAtivoDa422()
    {
        Sanctum::actingAs($this->usuario(factory(Tenant::class)->create()));

        $this->postJson('/api/v1/staff/push/test')->assertStatus(422);
    }

    // ---- quem recebe o aviso --------------------------------------------------------------------------------

    public function testGerenteDaBarracaRecebeEOutraBarracaNao()
    {
        $a = factory(Tenant::class)->create();
        $b = factory(Tenant::class)->create();
        $gerenteA = $this->usuario($a);
        $gerenteB = $this->usuario($b);
        $this->celular($gerenteA);
        $this->celular($gerenteB);

        app(PushService::class)->notificarPedido($this->pedido($a));

        $this->assertSame([$gerenteA->id], array_column($this->enviados, 'user_id'));
    }

    public function testAtendenteSoRecebeOPedidoDele()
    {
        $tenant = factory(Tenant::class)->create();
        $joaoUser = $this->usuario($tenant);
        $mariaUser = $this->usuario($tenant);
        Atendente::create(['tenant_id' => $tenant->id, 'nome' => 'João', 'ativo' => true, 'user_id' => $joaoUser->id]);
        Atendente::create(['tenant_id' => $tenant->id, 'nome' => 'Maria', 'ativo' => true, 'user_id' => $mariaUser->id]);
        $this->celular($joaoUser);
        $this->celular($mariaUser);
        $pedido = $this->pedido($tenant);

        // pedido sem atendente: nenhum atendente recebe (so o gerente, que aqui nao tem celular)
        app(PushService::class)->notificarPedido($pedido);
        $this->assertSame([], $this->enviados);

        // pedido do Joao: so o Joao
        app(PushService::class)->notificarPedido($pedido, $joaoUser->id);
        $this->assertSame([$joaoUser->id], array_column($this->enviados, 'user_id'));
    }

    public function testCelularQueNaoExisteMaisEApagado()
    {
        $tenant = factory(Tenant::class)->create();
        $gerente = $this->usuario($tenant);
        $celular = $this->celular($gerente);
        $this->resposta = false;

        app(PushService::class)->notificarPedido($this->pedido($tenant));

        $this->assertDatabaseMissing('push_subscriptions', ['id' => $celular->id]);
    }

    public function testMensagemTraLocalItensTotalEAtendente()
    {
        $tenant = factory(Tenant::class)->create();
        $mesa = factory(Table::class)->create(['tenant_id' => $tenant->id, 'identify' => 'Guarda-sol 4']);
        $produto = factory(Product::class)->create(['tenant_id' => $tenant->id]);
        $atendente = Atendente::create(['tenant_id' => $tenant->id, 'nome' => 'João', 'ativo' => true]);
        $pedido = $this->pedido($tenant, ['table_id' => $mesa->id, 'total' => 36, 'attendant_id' => $atendente->id]);
        $pedido->products()->attach($produto->id, ['qty' => 2, 'price' => 18]);

        $payload = app(PushService::class)->payloadDoPedido($pedido->fresh());

        $this->assertSame('Novo pedido · Guarda-sol 4', $payload['title']);
        $this->assertSame('2 itens · R$ 36,00 · João', $payload['body']);
        $this->assertSame($pedido->identify, $payload['tag']);
    }

    // ---- no fluxo do pedido ---------------------------------------------------------------------------------

    private function pedirNaLoja(Tenant $tenant)
    {
        $produto = factory(Product::class)->create(['tenant_id' => $tenant->id, 'stock' => 50]);

        return $this->postJson('/api/v1/orders', [
            'token_company' => $tenant->uuid,
            'products' => [['identify' => $produto->uuid, 'qty' => 1]],
        ]);
    }

    public function testPedidoNovoDisparaOAvisoParaOGerente()
    {
        $tenant = factory(Tenant::class)->create();
        $gerente = $this->usuario($tenant);
        $this->celular($gerente);

        $this->pedirNaLoja($tenant)->assertStatus(201);

        $this->assertSame([$gerente->id], array_column($this->enviados, 'user_id'));
        $this->assertStringStartsWith('Novo pedido', $this->enviados[0]['payload']['title']);
    }

    public function testPushForaDoArNaoDerrubaOPedido()
    {
        $tenant = factory(Tenant::class)->create();
        $this->celular($this->usuario($tenant));
        $this->explodir = true;

        $this->pedirNaLoja($tenant)->assertStatus(201);

        $this->assertSame(1, Order::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }
}
