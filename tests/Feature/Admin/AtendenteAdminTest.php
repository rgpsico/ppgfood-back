<?php

namespace Tests\Feature\Admin;

use App\Models\Atendente;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AtendenteReportService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AtendenteAdminTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // As permissoes reais vem da tabela permissions (vazia no teste): libera o gate usado pelo modulo
        Gate::define('users', fn () => true);
    }

    private function dono(Tenant $tenant): User
    {
        return factory(User::class)->create(['tenant_id' => $tenant->id]);
    }

    private function atendente(Tenant $tenant, string $nome = 'João', bool $ativo = true): Atendente
    {
        return Atendente::create(['tenant_id' => $tenant->id, 'nome' => $nome, 'ativo' => $ativo]);
    }

    private function pedido(Tenant $tenant, ?Atendente $a, string $status, float $total): Order
    {
        return Order::create([
            'tenant_id' => $tenant->id, 'identify' => uniqid('o'), 'total' => $total,
            'status' => $status, 'attendant_id' => $a ? $a->id : null,
        ]);
    }

    public function testRelatorioSeparaConcluidosPendentesECancelados()
    {
        $tenant = factory(Tenant::class)->create();
        $joao = $this->atendente($tenant);
        $this->pedido($tenant, $joao, 'done', 30);
        $this->pedido($tenant, $joao, 'done', 20);
        $this->pedido($tenant, $joao, 'open', 15);
        $this->pedido($tenant, $joao, 'working', 5);
        $this->pedido($tenant, $joao, 'canceled', 100);
        $this->pedido($tenant, $joao, 'rejected', 50);
        $this->pedido($tenant, null, 'done', 999); // sem atendente nao entra

        $linha = app(AtendenteReportService::class)->resumo($tenant->id, now()->subDay(), now())->first();

        $this->assertSame(6, $linha->pedidos);
        $this->assertSame(2, $linha->concluidos);
        $this->assertEquals(50.0, $linha->vendido);       // so concluidos
        $this->assertSame(2, $linha->pendentes);
        $this->assertEquals(20.0, $linha->valor_pendente);
        $this->assertSame(2, $linha->cancelados);
        $this->assertEquals(150.0, $linha->valor_cancelado);
        $this->assertTrue($linha->atendendo_agora);
    }

    public function testRelatorioRespeitaOPeriodoEOIsolamentoPorEmpresa()
    {
        $a = factory(Tenant::class)->create();
        $b = factory(Tenant::class)->create();
        $joao = $this->atendente($a);
        $pedro = $this->atendente($b, 'Pedro');
        $antigo = $this->pedido($a, $joao, 'done', 40);
        $antigo->forceFill(['created_at' => now()->subDays(60)])->save();
        $this->pedido($a, $joao, 'done', 10);
        $this->pedido($b, $pedro, 'done', 500);

        $linhas = app(AtendenteReportService::class)->resumo($a->id, now()->subDays(30), now());

        $this->assertCount(1, $linhas);                       // Pedro (outra empresa) nao aparece
        $this->assertEquals(10.0, $linhas->first()->vendido); // pedido de 60 dias atras fica de fora
    }

    public function testListaDoAdminMostraSoAtendentesDaPropriaEmpresa()
    {
        $a = factory(Tenant::class)->create();
        $b = factory(Tenant::class)->create();
        $this->atendente($a, 'João');
        $this->atendente($b, 'Pedro');

        $this->actingAs($this->dono($a))
            ->get('/admin/atendentes')
            ->assertStatus(200)
            ->assertSee('João')
            ->assertDontSee('Pedro');
    }

    public function testCadastrarAtendenteComLoginCriaUsuarioDaMesmaEmpresa()
    {
        $tenant = factory(Tenant::class)->create();

        $this->actingAs($this->dono($tenant))
            ->post('/admin/atendentes', ['nome' => 'Maria', 'ativo' => 1, 'email' => 'maria@barraca.com', 'password' => 'segredo1'])
            ->assertRedirect('/admin/atendentes');

        $user = User::where('email', 'maria@barraca.com')->firstOrFail();
        $this->assertSame($tenant->id, $user->tenant_id);
        $this->assertTrue(Hash::check('segredo1', $user->password));

        $atendente = Atendente::withoutGlobalScopes()->where('nome', 'Maria')->firstOrFail();
        $this->assertSame($tenant->id, $atendente->tenant_id);
        $this->assertSame($user->id, $atendente->user_id);
    }

    public function testCadastrarSemLoginFuncionaEExigeEmailEsenhaJuntos()
    {
        $tenant = factory(Tenant::class)->create();
        $dono = $this->dono($tenant);

        $this->actingAs($dono)->post('/admin/atendentes', ['nome' => 'Ana'])->assertRedirect('/admin/atendentes');
        $this->assertNull(Atendente::withoutGlobalScopes()->where('nome', 'Ana')->first()->user_id);

        $this->actingAs($dono)->post('/admin/atendentes', ['nome' => 'Bia', 'email' => 'bia@barraca.com'])
            ->assertSessionHasErrors('password');
        $this->assertNull(Atendente::withoutGlobalScopes()->where('nome', 'Bia')->first());
    }

    public function testDesativarNaoApagaEOAtendentePassaAFicarInvisivelParaOCliente()
    {
        $tenant = factory(Tenant::class)->create();
        $joao = $this->atendente($tenant);
        $this->pedido($tenant, $joao, 'done', 10);

        $this->actingAs($this->dono($tenant))->patch("/admin/atendentes/{$joao->id}/toggle")->assertRedirect('/admin/atendentes');

        $joao->refresh();
        $this->assertFalse($joao->ativo);
        $this->assertSame(1, $joao->orders()->count()); // historico preservado
    }

    public function testNaoEditaNemDesativaAtendenteDeOutraEmpresa()
    {
        $a = factory(Tenant::class)->create();
        $b = factory(Tenant::class)->create();
        $pedro = $this->atendente($b, 'Pedro');
        $dono = $this->dono($a);

        $this->actingAs($dono)->patch("/admin/atendentes/{$pedro->id}/toggle")->assertStatus(404);
        $this->actingAs($dono)->put("/admin/atendentes/{$pedro->id}", ['nome' => 'Hackeado', 'ativo' => 1])->assertStatus(404);

        $this->assertSame('Pedro', $pedro->fresh()->nome);
        $this->assertTrue($pedro->fresh()->ativo);
    }

    public function testCorrigirAtribuicaoDoPedido()
    {
        $tenant = factory(Tenant::class)->create();
        $joao = $this->atendente($tenant, 'João');
        $maria = $this->atendente($tenant, 'Maria');
        $pedido = $this->pedido($tenant, $joao, 'done', 10);

        $this->actingAs($this->dono($tenant))
            ->patch("/admin/atendentes/pedidos/{$pedido->id}/reatribuir", ['attendant_id' => $maria->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($maria->id, $pedido->fresh()->attendant_id);
    }

    public function testNaoReatribuiParaAtendenteNemPedidoDeOutraEmpresa()
    {
        $a = factory(Tenant::class)->create();
        $b = factory(Tenant::class)->create();
        $joao = $this->atendente($a, 'João');
        $pedro = $this->atendente($b, 'Pedro');
        $meu = $this->pedido($a, $joao, 'done', 10);
        $alheio = $this->pedido($b, $pedro, 'done', 10);
        $dono = $this->dono($a);

        $this->actingAs($dono)->patch("/admin/atendentes/pedidos/{$meu->id}/reatribuir", ['attendant_id' => $pedro->id])->assertStatus(404);
        $this->actingAs($dono)->patch("/admin/atendentes/pedidos/{$alheio->id}/reatribuir", ['attendant_id' => $joao->id])->assertStatus(404);

        $this->assertSame($joao->id, $meu->fresh()->attendant_id);
        $this->assertSame($pedro->id, $alheio->fresh()->attendant_id);
    }

    public function testPaginaDePedidosDoAtendenteMostraSoOsDele()
    {
        $tenant = factory(Tenant::class)->create();
        $joao = $this->atendente($tenant, 'João');
        $maria = $this->atendente($tenant, 'Maria');
        $doJoao = $this->pedido($tenant, $joao, 'done', 11);
        $daMaria = $this->pedido($tenant, $maria, 'done', 22);

        $this->actingAs($this->dono($tenant))
            ->get("/admin/atendentes/{$joao->id}")
            ->assertStatus(200)
            ->assertSee($doJoao->identify)
            ->assertDontSee($daMaria->identify);
    }

    public function testSemPermissaoNaoAcessa()
    {
        Gate::define('users', fn () => false);
        $tenant = factory(Tenant::class)->create();

        $this->actingAs($this->dono($tenant))->get('/admin/atendentes')->assertStatus(403);
    }
}
