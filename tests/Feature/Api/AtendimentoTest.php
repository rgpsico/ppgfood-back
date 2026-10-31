<?php

namespace Tests\Feature\Api;

use App\Events\AtendenteOrderCreated;
use App\Events\OrderCreated;
use App\Models\Atendente;
use App\Models\Client;
use App\Models\Configuracao;
use App\Models\ConfiguracaoModelo;
use App\Models\Order;
use App\Models\Product;
use App\Models\Table;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Atendimento por funcionario: parametrizado por empresa, validado no backend e isolado por tenant.
 */
class AtendimentoTest extends TestCase
{
    private function tenant(array $config = []): Tenant
    {
        $tenant = factory(Tenant::class)->create();

        foreach ($config as $chave => $valor) {
            $this->config($tenant, $chave, $valor);
        }

        return $tenant;
    }

    private function config(Tenant $tenant, string $chave, string $valor): void
    {
        $modelo = ConfiguracaoModelo::where('chave', $chave)->firstOrFail();

        Configuracao::updateOrCreate(
            ['tenant_id' => $tenant->id, 'configuracoes_modelo_id' => $modelo->id],
            ['valor' => $valor]
        );
    }

    private function ligado(array $extra = []): array
    {
        return array_merge(['atendimento_funcionario_habilitado' => '1'], $extra);
    }

    private function atendente(Tenant $tenant, string $nome = 'João', bool $ativo = true, ?User $user = null): Atendente
    {
        return Atendente::create([
            'tenant_id' => $tenant->id,
            'nome' => $nome,
            'ativo' => $ativo,
            'user_id' => $user ? $user->id : null,
        ]);
    }

    private function usuario(Tenant $tenant): User
    {
        return factory(User::class)->create(['tenant_id' => $tenant->id]);
    }

    private function payload(Tenant $tenant, array $extra = []): array
    {
        $product = factory(Product::class)->create(['tenant_id' => $tenant->id, 'stock' => 100]);

        return array_merge([
            'token_company' => $tenant->uuid,
            'products' => [['identify' => $product->uuid, 'qty' => 1]],
        ], $extra);
    }

    private function pedir(Tenant $tenant, array $extra = [])
    {
        return $this->postJson('/api/v1/orders', $this->payload($tenant, $extra));
    }

    private function pedidoCriado(): Order
    {
        return Order::withoutGlobalScopes()->latest('id')->firstOrFail();
    }

    // ---- modulo desligado: nada muda ----------------------------------------------------------

    public function testModuloDesligadoContinuaCriandoPedidoSemAtendente()
    {
        $tenant = $this->tenant();

        $this->pedir($tenant)->assertStatus(201);

        $this->assertNull($this->pedidoCriado()->attendant_id);
    }

    public function testModuloDesligadoIgnoraAtendenteEnviadoPeloCliente()
    {
        $tenant = $this->tenant();
        $atendente = $this->atendente($tenant);

        $this->pedir($tenant, ['attendant' => $atendente->uuid, 'attendant_id' => $atendente->id])->assertStatus(201);

        $this->assertNull($this->pedidoCriado()->attendant_id);
    }

    public function testModuloDesligadoNaoMostraAtendentesNemFlags()
    {
        $tenant = $this->tenant();
        $this->atendente($tenant);

        $this->getJson('/api/v1/atendentes?token_company=' . $tenant->uuid)
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/v1/tenants/' . $tenant->uuid)
            ->assertJsonPath('data.atendimento.habilitado', false);
    }

    // ---- modulo ligado ---------------------------------------------------------------------------

    public function testModuloLigadoListaSoAtendentesAtivosDaEmpresa()
    {
        $tenant = $this->tenant($this->ligado());
        $outra = $this->tenant($this->ligado());
        $joao = $this->atendente($tenant, 'João');
        $this->atendente($tenant, 'Maria', false);
        $this->atendente($outra, 'Pedro');

        $response = $this->getJson('/api/v1/atendentes?token_company=' . $tenant->uuid)->assertStatus(200);

        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'João')
            ->assertJsonPath('data.0.identify', $joao->uuid);
        // so o que o cliente precisa: nada de id interno, tenant ou usuario
        $this->assertSame(['identify', 'name'], array_keys($response->json('data.0')));
    }

    public function testPedidoEhSalvoComOAtendenteCorreto()
    {
        $tenant = $this->tenant($this->ligado());
        $joao = $this->atendente($tenant, 'João');
        $this->atendente($tenant, 'Maria');

        $this->pedir($tenant, ['attendant' => $joao->uuid])
            ->assertStatus(201)
            ->assertJsonPath('data.attendant.name', 'João');

        $this->assertSame($joao->id, $this->pedidoCriado()->attendant_id);
    }

    public function testSelecaoObrigatoriaImpedePedidoSemAtendente()
    {
        $tenant = $this->tenant($this->ligado(['atendimento_funcionario_obrigatorio' => '1']));

        $this->pedir($tenant)->assertStatus(422)->assertJsonValidationErrors('attendant');

        $this->assertSame(0, Order::withoutGlobalScopes()->count());
    }

    public function testSelecaoObrigatoriaVenceOpcaoSemAtendente()
    {
        $tenant = $this->tenant($this->ligado([
            'atendimento_funcionario_obrigatorio' => '1',
            'atendimento_permitir_sem_funcionario' => '1',
        ]));

        $this->pedir($tenant)->assertStatus(422)->assertJsonValidationErrors('attendant');
    }

    public function testSelecaoOpcionalPermitePedidoSemAtendente()
    {
        $tenant = $this->tenant($this->ligado());

        $this->pedir($tenant)->assertStatus(201);

        $this->assertNull($this->pedidoCriado()->attendant_id);
    }

    public function testSemAtendentePermitidoDesligadoExigeEscolha()
    {
        $tenant = $this->tenant($this->ligado(['atendimento_permitir_sem_funcionario' => '0']));

        $this->pedir($tenant)->assertStatus(422)->assertJsonValidationErrors('attendant');
    }

    public function testAtendenteDeOutraEmpresaEhRejeitado()
    {
        $tenant = $this->tenant($this->ligado());
        $outra = $this->tenant($this->ligado());
        $deOutra = $this->atendente($outra, 'Pedro');

        $this->pedir($tenant, ['attendant' => $deOutra->uuid])->assertStatus(422)->assertJsonValidationErrors('attendant');

        $this->assertSame(0, Order::withoutGlobalScopes()->count());
    }

    public function testAtendenteInativoNaoRecebeNovaAtribuicao()
    {
        $tenant = $this->tenant($this->ligado());
        $inativo = $this->atendente($tenant, 'Ana', false);

        $this->pedir($tenant, ['attendant' => $inativo->uuid])->assertStatus(422)->assertJsonValidationErrors('attendant');
    }

    public function testAtendenteInexistenteEhRejeitado()
    {
        $tenant = $this->tenant($this->ligado());

        $this->pedir($tenant, ['attendant' => '00000000-0000-0000-0000-000000000000'])
            ->assertStatus(422)->assertJsonValidationErrors('attendant');
    }

    public function testClienteNaoConsegueForcarAttendantIdPeloCorpoDoPedido()
    {
        $tenant = $this->tenant($this->ligado());
        $outra = $this->tenant($this->ligado());
        $deOutra = $this->atendente($outra, 'Pedro');

        $this->pedir($tenant, ['attendant_id' => $deOutra->id])->assertStatus(201);

        $this->assertNull($this->pedidoCriado()->attendant_id);
    }

    // ---- localizacao / QR ---------------------------------------------------------------------------

    public function testQrGeralEQrComLocalizacaoConvivem()
    {
        $tenant = $this->tenant($this->ligado());
        $mesa = factory(Table::class)->create(['tenant_id' => $tenant->id]);
        $joao = $this->atendente($tenant);

        // QR geral da loja: pedido sem mesa
        $this->pedir($tenant, ['attendant' => $joao->uuid])->assertStatus(201);
        $this->assertNull($this->pedidoCriado()->table_id);

        // QR do guarda-sol: pedido com mesa, mesmo atendente
        $this->pedir($tenant, ['attendant' => $joao->uuid, 'table' => $mesa->uuid])->assertStatus(201);
        $comMesa = $this->pedidoCriado();
        $this->assertSame($mesa->id, $comMesa->table_id);
        $this->assertSame($joao->id, $comMesa->attendant_id);
    }

    public function testExigirLocalizacaoBarraPedidoSemGuardaSol()
    {
        $tenant = $this->tenant($this->ligado(['atendimento_exigir_localizacao' => '1']));
        $mesa = factory(Table::class)->create(['tenant_id' => $tenant->id]);

        $this->pedir($tenant)->assertStatus(422)->assertJsonValidationErrors('table');
        $this->pedir($tenant, ['table' => $mesa->uuid])->assertStatus(201);
    }

    // ---- configuracao ------------------------------------------------------------------------------------

    public function testAlteracoesDeConfiguracaoSaoAplicadasNoProximoPedido()
    {
        $tenant = $this->tenant();
        $joao = $this->atendente($tenant);

        $this->pedir($tenant, ['attendant' => $joao->uuid])->assertStatus(201);
        $this->assertNull($this->pedidoCriado()->attendant_id);

        $this->config($tenant, 'atendimento_funcionario_habilitado', '1');
        $this->pedir($tenant, ['attendant' => $joao->uuid])->assertStatus(201);
        $this->assertSame($joao->id, $this->pedidoCriado()->attendant_id);

        $this->config($tenant, 'atendimento_funcionario_obrigatorio', '1');
        $this->pedir($tenant)->assertStatus(422);

        $this->config($tenant, 'atendimento_funcionario_habilitado', '0');
        $this->pedir($tenant)->assertStatus(201);
    }

    public function testFlagsDoModuloChegamNoCardapio()
    {
        $tenant = $this->tenant($this->ligado([
            'atendimento_permitir_troca_funcionario' => '0',
            'atendimento_exibir_funcionario_pedido' => '1',
        ]));

        $this->getJson('/api/v1/tenants/' . $tenant->uuid)
            ->assertJsonPath('data.atendimento.habilitado', true)
            ->assertJsonPath('data.atendimento.obrigatorio', false)
            ->assertJsonPath('data.atendimento.permitir_sem_atendente', true)
            ->assertJsonPath('data.atendimento.permitir_troca', false)
            ->assertJsonPath('data.atendimento.exibir_no_pedido', true);
    }

    // ---- painel da barraca e do atendente ---------------------------------------------------------------

    private function pedidoDe(Tenant $tenant, ?Atendente $atendente): Order
    {
        return Order::create([
            'tenant_id' => $tenant->id,
            'identify' => uniqid('p'),
            'total' => 10,
            'status' => 'open',
            'attendant_id' => $atendente ? $atendente->id : null,
        ]);
    }

    public function testPainelDaBarracaRecebeTodosOsPedidosComOuSemAtendente()
    {
        $tenant = $this->tenant($this->ligado());
        $dono = $this->usuario($tenant);
        $joao = $this->atendente($tenant);
        $this->pedidoDe($tenant, $joao);
        $this->pedidoDe($tenant, null);

        Sanctum::actingAs($dono);

        $this->getJson('/api/v1/staff/orders-board')
            ->assertStatus(200)
            ->assertJsonCount(2, 'orders_without_table');
    }

    public function testEventoDePedidoNovoContinuaIndoParaOPainelDaBarraca()
    {
        Event::fake([OrderCreated::class, AtendenteOrderCreated::class]);
        $tenant = $this->tenant($this->ligado());
        $joao = $this->atendente($tenant);

        $this->pedir($tenant, ['attendant' => $joao->uuid])->assertStatus(201);

        Event::assertDispatchedTimes(OrderCreated::class, 1);
    }

    public function testAtendenteVeSomenteOsPedidosDele()
    {
        $tenant = $this->tenant($this->ligado());
        $userJoao = $this->usuario($tenant);
        $joao = $this->atendente($tenant, 'João', true, $userJoao);
        $maria = $this->atendente($tenant, 'Maria');
        $meu = $this->pedidoDe($tenant, $joao);
        $this->pedidoDe($tenant, $maria);
        $this->pedidoDe($tenant, null);

        Sanctum::actingAs($userJoao);

        $this->getJson('/api/v1/staff/orders-board')
            ->assertJsonCount(1, 'orders_without_table')
            ->assertJsonPath('orders_without_table.0.identify', $meu->identify);

        $this->getJson('/api/v1/staff/orders')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.identify', $meu->identify);

        // tentar ver os de outro pela query nao funciona: o filtro do atendente e sempre o dele
        $this->getJson('/api/v1/staff/orders?attendant=' . $maria->uuid)->assertJsonCount(1, 'data');
    }

    public function testAtendenteNaoMudaStatusDePedidoDeOutroAtendenteNemSemAtendente()
    {
        $tenant = $this->tenant($this->ligado());
        $userJoao = $this->usuario($tenant);
        $joao = $this->atendente($tenant, 'João', true, $userJoao);
        $maria = $this->atendente($tenant, 'Maria');
        $meu = $this->pedidoDe($tenant, $joao);
        $deMaria = $this->pedidoDe($tenant, $maria);
        $semAtendente = $this->pedidoDe($tenant, null);

        Sanctum::actingAs($userJoao);

        $this->patchJson("/api/v1/staff/orders/{$deMaria->identify}/status", ['status' => 'done'])->assertStatus(404);
        $this->patchJson("/api/v1/staff/orders/{$semAtendente->identify}/status", ['status' => 'done'])->assertStatus(404);
        $this->patchJson("/api/v1/staff/orders/{$meu->identify}/status", ['status' => 'done'])->assertStatus(200);

        $this->assertSame('open', Order::withoutGlobalScopes()->find($deMaria->id)->status);
        $this->assertSame('done', Order::withoutGlobalScopes()->find($meu->id)->status);
    }

    public function testDonoPodeFiltrarPedidosPorAtendente()
    {
        $tenant = $this->tenant($this->ligado());
        $dono = $this->usuario($tenant);
        $joao = $this->atendente($tenant, 'João');
        $maria = $this->atendente($tenant, 'Maria');
        $this->pedidoDe($tenant, $joao);
        $this->pedidoDe($tenant, $maria);
        $this->pedidoDe($tenant, $maria);

        Sanctum::actingAs($dono);

        $this->getJson('/api/v1/staff/orders')->assertJsonCount(3, 'data');
        $this->getJson('/api/v1/staff/orders?attendant=' . $maria->uuid)->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/staff/orders?attendant=nao-existe')->assertJsonCount(0, 'data');
    }

    public function testAtendenteDeUmaEmpresaNaoVePedidosDeOutra()
    {
        $a = $this->tenant($this->ligado());
        $b = $this->tenant($this->ligado());
        $userA = $this->usuario($a);
        $this->atendente($a, 'João', true, $userA);
        $atendenteB = $this->atendente($b, 'Pedro');
        $pedidoB = $this->pedidoDe($b, $atendenteB);

        Sanctum::actingAs($userA);

        $this->getJson('/api/v1/staff/orders')->assertJsonCount(0, 'data');
        $this->patchJson("/api/v1/staff/orders/{$pedidoB->identify}/status", ['status' => 'done'])->assertStatus(404);
    }

    public function testDonoDeOutraEmpresaNaoVePedidosNemFiltraPorAtendenteAlheio()
    {
        $a = $this->tenant($this->ligado());
        $b = $this->tenant($this->ligado());
        $donoA = $this->usuario($a);
        $atendenteB = $this->atendente($b, 'Pedro');
        $this->pedidoDe($b, $atendenteB);

        Sanctum::actingAs($donoA);

        $this->getJson('/api/v1/staff/orders?attendant=' . $atendenteB->uuid)->assertJsonCount(0, 'data');
    }

    public function testTokenDeClienteNaoAbrePainelDaEquipe()
    {
        $tenant = $this->tenant($this->ligado());
        $cliente = Client::create([
            'name' => 'Cliente', 'email' => 'c@teste.com', 'password' => bcrypt('x'),
            'endereco' => 'x', 'instagran' => 'x', 'telefone' => '1', 'cpfcnpj' => '1',
        ]);

        Sanctum::actingAs($cliente);

        $this->getJson('/api/v1/staff/orders')->assertStatus(403);
        $this->getJson('/api/v1/staff/orders-board')->assertStatus(403);
    }

    public function testStaffMeInformaOAtendenteDoUsuario()
    {
        $tenant = $this->tenant($this->ligado());
        $user = $this->usuario($tenant);
        $dono = $this->usuario($tenant);
        $this->atendente($tenant, 'João', true, $user);

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/staff/me')->assertJsonPath('atendente.name', 'João');

        Sanctum::actingAs($dono);
        $this->getJson('/api/v1/staff/me')->assertJsonPath('atendente', null);
    }

    // ---- notificacao -----------------------------------------------------------------------------------------

    public function testAtendenteComLoginRecebeAvisoDoPedidoDele()
    {
        Event::fake([OrderCreated::class, AtendenteOrderCreated::class]);
        $tenant = $this->tenant($this->ligado());
        $user = $this->usuario($tenant);
        $joao = $this->atendente($tenant, 'João', true, $user);

        $this->pedir($tenant, ['attendant' => $joao->uuid])->assertStatus(201);

        Event::assertDispatchedTimes(AtendenteOrderCreated::class, 1);
        Event::assertDispatched(AtendenteOrderCreated::class, function ($event) use ($user) {
            return $event->broadcastOn()->name === 'private-atendente.' . $user->id;
        });
    }

    public function testSemNotificarOuSemLoginNaoHaAvisoDeAtendente()
    {
        Event::fake([OrderCreated::class, AtendenteOrderCreated::class]);
        $semNotificar = $this->tenant($this->ligado(['atendimento_notificar_funcionario' => '0']));
        $semLogin = $this->tenant($this->ligado());

        $a = $this->atendente($semNotificar, 'João', true, $this->usuario($semNotificar));
        $b = $this->atendente($semLogin, 'Maria');

        $this->pedir($semNotificar, ['attendant' => $a->uuid])->assertStatus(201);
        $this->pedir($semLogin, ['attendant' => $b->uuid])->assertStatus(201);

        Event::assertNotDispatched(AtendenteOrderCreated::class);
        Event::assertDispatchedTimes(OrderCreated::class, 2);
    }

    // ---- compatibilidade --------------------------------------------------------------------------------------

    public function testPedidoAntigoSemAtendenteContinuaAparecendoNoPainel()
    {
        $tenant = $this->tenant();
        $dono = $this->usuario($tenant);
        $antigo = $this->pedidoDe($tenant, null);

        Sanctum::actingAs($dono);

        $this->getJson('/api/v1/staff/orders')
            ->assertStatus(200)
            ->assertJsonPath('data.0.identify', $antigo->identify)
            ->assertJsonPath('data.0.attendant', null);

        $this->getJson('/api/v1/orders/' . $antigo->identify)->assertStatus(200);
    }

    // ---- canal privado ---------------------------------------------------------------------------------------

    private function podeEntrarNoCanal(?User $user, string $canal): bool
    {
        $request = Request::create('/broadcasting/auth', 'POST', ['channel_name' => $canal]);
        $request->setUserResolver(fn () => $user);

        $broadcaster = Broadcast::driver();
        $metodo = new \ReflectionMethod($broadcaster, 'verifyUserCanAccessChannel');
        $metodo->setAccessible(true);

        // O driver "log" devolve null quando autoriza; quem nega lanca AccessDeniedHttpException
        try {
            $metodo->invoke($broadcaster, $request, $canal);

            return true;
        } catch (AccessDeniedHttpException $e) {
            return false;
        }
    }

    public function testCanalPrivadoDoAtendenteSoAbreParaOProprioAtendente()
    {
        $tenant = $this->tenant($this->ligado());
        $joaoUser = $this->usuario($tenant);
        $mariaUser = $this->usuario($tenant);
        $donoSemCadastro = $this->usuario($tenant);
        $this->atendente($tenant, 'João', true, $joaoUser);
        $this->atendente($tenant, 'Maria', true, $mariaUser);

        $this->assertTrue($this->podeEntrarNoCanal($joaoUser, 'atendente.' . $joaoUser->id));
        // colega da mesma empresa nao escuta o canal do outro
        $this->assertFalse($this->podeEntrarNoCanal($mariaUser, 'atendente.' . $joaoUser->id));
        // quem nao e atendente nao entra nem no proprio canal
        $this->assertFalse($this->podeEntrarNoCanal($donoSemCadastro, 'atendente.' . $donoSemCadastro->id));
        // sem login nao entra
        $this->assertFalse($this->podeEntrarNoCanal(null, 'atendente.' . $joaoUser->id));
    }

    public function testCanalDaBarracaContinuaRestritoAEmpresa()
    {
        $a = $this->tenant();
        $b = $this->tenant();
        $donoA = $this->usuario($a);

        $this->assertTrue($this->podeEntrarNoCanal($donoA, 'order-created.' . $a->id));
        $this->assertFalse($this->podeEntrarNoCanal($donoA, 'order-created.' . $b->id));
    }

    // ---- guarda-sois do atendente ------------------------------------------------------------------------------

    public function testAtendenteVeNoMapaSoOsGuardaSoisQueEstaAtendendo()
    {
        $tenant = $this->tenant($this->ligado());
        $userJoao = $this->usuario($tenant);
        $dono = $this->usuario($tenant);
        $joao = $this->atendente($tenant, 'João', true, $userJoao);
        $maria = $this->atendente($tenant, 'Maria');
        $mesa1 = factory(Table::class)->create(['tenant_id' => $tenant->id]);
        $mesa2 = factory(Table::class)->create(['tenant_id' => $tenant->id]);
        $mesa3 = factory(Table::class)->create(['tenant_id' => $tenant->id]);
        $mesaLivre = factory(Table::class)->create(['tenant_id' => $tenant->id]);

        $this->pedidoNaMesa($tenant, $joao, $mesa1, 'open');
        $this->pedidoNaMesa($tenant, $maria, $mesa2, 'open');
        $this->pedidoNaMesa($tenant, $joao, $mesa3, 'done'); // entregue: ja nao esta atendendo

        Sanctum::actingAs($userJoao);
        $mapa = $this->getJson('/api/v1/staff/orders-board')->assertStatus(200);
        $mapa->assertJsonCount(1, 'tables')
            ->assertJsonPath('tables.0.identify', (string) $mesa1->uuid)
            ->assertJsonPath('only_serving', true);

        // dono continua vendo todos os guarda-sois
        Sanctum::actingAs($dono);
        $this->getJson('/api/v1/staff/orders-board')
            ->assertJsonCount(4, 'tables')
            ->assertJsonPath('only_serving', false);
    }

    public function testAtendenteNaoReorganizaOMapaDaBarraca()
    {
        $tenant = $this->tenant($this->ligado());
        $userJoao = $this->usuario($tenant);
        $dono = $this->usuario($tenant);
        $this->atendente($tenant, 'João', true, $userJoao);
        $mesa = factory(Table::class)->create(['tenant_id' => $tenant->id]);

        Sanctum::actingAs($userJoao);
        $this->patchJson("/api/v1/staff/tables/{$mesa->uuid}/position", ['position_x' => 10, 'position_y' => 10])->assertStatus(403);
        $this->patchJson("/api/v1/staff/tables/{$mesa->uuid}/beach-position", ['beach_row' => 1, 'beach_col' => 1])->assertStatus(403);

        Sanctum::actingAs($dono);
        $this->patchJson("/api/v1/staff/tables/{$mesa->uuid}/position", ['position_x' => 10, 'position_y' => 10])->assertStatus(200);
    }

    private function pedidoNaMesa(Tenant $tenant, Atendente $atendente, Table $mesa, string $status): Order
    {
        return Order::create([
            'tenant_id' => $tenant->id, 'identify' => uniqid('m'), 'total' => 10, 'status' => $status,
            'attendant_id' => $atendente->id, 'table_id' => $mesa->id,
        ]);
    }
}
