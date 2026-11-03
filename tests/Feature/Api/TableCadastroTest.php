<?php

namespace Tests\Feature\Api;

use App\Models\Atendente;
use App\Models\Order;
use App\Models\Table;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Cadastro de guarda-sol / cadeira pelo painel: so o gerente mexe, sempre dentro da propria barraca.
 */
class TableCadastroTest extends TestCase
{
    private function gerente(?Tenant $tenant = null): User
    {
        $tenant = $tenant ?: factory(Tenant::class)->create();

        return factory(User::class)->create(['tenant_id' => $tenant->id]);
    }

    private function ponto(Tenant $tenant, string $nome, string $tipo = 'guarda_sol'): Table
    {
        return factory(Table::class)->create(['tenant_id' => $tenant->id, 'identify' => $nome, 'tipo' => $tipo]);
    }

    public function testCadastraGuardaSolEFicaNaBarracaDoGerente()
    {
        $gerente = $this->gerente();
        Sanctum::actingAs($gerente);

        $this->postJson('/api/v1/staff/tables', ['tipo' => 'guarda_sol', 'nome' => 'Guarda-sol VIP'])
            ->assertStatus(201)
            ->assertJsonPath('data.0.name', 'Guarda-sol VIP')
            ->assertJsonPath('data.0.tipo', 'guarda_sol');

        $this->assertDatabaseHas('tables', ['identify' => 'Guarda-sol VIP', 'tipo' => 'guarda_sol', 'tenant_id' => $gerente->tenant_id]);
    }

    public function testCadastraSoCadeiraComNumeracaoAutomatica()
    {
        $gerente = $this->gerente();
        $this->ponto($gerente->tenant, 'Cadeira 4', 'cadeira');
        Sanctum::actingAs($gerente);

        $this->postJson('/api/v1/staff/tables', ['tipo' => 'cadeira', 'quantidade' => 3])
            ->assertStatus(201)
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.name', 'Cadeira 5')
            ->assertJsonPath('data.2.name', 'Cadeira 7')
            ->assertJsonPath('data.0.tipo', 'cadeira');
    }

    public function testNaoAceitaTipoInvalidoNemNomeRepetido()
    {
        $gerente = $this->gerente();
        $this->ponto($gerente->tenant, 'Guarda-sol 1');
        Sanctum::actingAs($gerente);

        $this->postJson('/api/v1/staff/tables', ['tipo' => 'barraca', 'nome' => 'X'])->assertStatus(422);
        $this->postJson('/api/v1/staff/tables', ['tipo' => 'guarda_sol', 'nome' => 'Guarda-sol 1'])->assertStatus(422);
        $this->postJson('/api/v1/staff/tables', ['nome' => 'Sem tipo'])->assertStatus(422);
    }

    public function testMesmoNomeEmOutraBarracaPode()
    {
        $outra = factory(Tenant::class)->create();
        $this->ponto($outra, 'Guarda-sol 1');
        Sanctum::actingAs($this->gerente());

        $this->postJson('/api/v1/staff/tables', ['tipo' => 'guarda_sol', 'nome' => 'Guarda-sol 1'])->assertStatus(201);
    }

    public function testTrocaTipoERenomeia()
    {
        $gerente = $this->gerente();
        $ponto = $this->ponto($gerente->tenant, 'Guarda-sol 1');
        Sanctum::actingAs($gerente);

        $this->putJson("/api/v1/staff/tables/{$ponto->uuid}", ['tipo' => 'cadeira', 'nome' => 'Cadeira 1'])
            ->assertStatus(200)
            ->assertJsonPath('data.tipo', 'cadeira')
            ->assertJsonPath('data.name', 'Cadeira 1');

        $this->assertDatabaseHas('tables', ['id' => $ponto->id, 'tipo' => 'cadeira', 'identify' => 'Cadeira 1']);
    }

    public function testRenomearParaNomeDeOutroPontoDaMesmaBarracaFalha()
    {
        $gerente = $this->gerente();
        $this->ponto($gerente->tenant, 'Guarda-sol 1');
        $dois = $this->ponto($gerente->tenant, 'Guarda-sol 2');
        Sanctum::actingAs($gerente);

        $this->putJson("/api/v1/staff/tables/{$dois->uuid}", ['nome' => 'Guarda-sol 1'])->assertStatus(422);
        // manter o proprio nome ao trocar so o tipo nao conta como repetido
        $this->putJson("/api/v1/staff/tables/{$dois->uuid}", ['nome' => 'Guarda-sol 2', 'tipo' => 'cadeira'])->assertStatus(200);
    }

    public function testApagaPontoSemPedidosMasNaoOQueTemHistorico()
    {
        $gerente = $this->gerente();
        $livre = $this->ponto($gerente->tenant, 'Guarda-sol 1');
        $usado = $this->ponto($gerente->tenant, 'Guarda-sol 2');
        Order::create([
            'tenant_id' => $gerente->tenant_id, 'identify' => uniqid('p'), 'total' => 10,
            'status' => 'done', 'table_id' => $usado->id,
        ]);
        Sanctum::actingAs($gerente);

        $this->deleteJson("/api/v1/staff/tables/{$livre->uuid}")->assertStatus(200);
        $this->assertDatabaseMissing('tables', ['id' => $livre->id]);

        $this->deleteJson("/api/v1/staff/tables/{$usado->uuid}")->assertStatus(422);
        $this->assertDatabaseHas('tables', ['id' => $usado->id]);
    }

    public function testNaoMexeEmPontoDeOutraBarraca()
    {
        $outra = factory(Tenant::class)->create();
        $dela = $this->ponto($outra, 'Guarda-sol 1');
        Sanctum::actingAs($this->gerente());

        $this->putJson("/api/v1/staff/tables/{$dela->uuid}", ['nome' => 'Invadido'])->assertStatus(404);
        $this->deleteJson("/api/v1/staff/tables/{$dela->uuid}")->assertStatus(404);
        $this->assertDatabaseHas('tables', ['id' => $dela->id, 'identify' => 'Guarda-sol 1']);
    }

    public function testAtendenteNaoCadastraNemApaga()
    {
        $gerente = $this->gerente();
        $ponto = $this->ponto($gerente->tenant, 'Guarda-sol 1');
        $userAtendente = $this->gerente($gerente->tenant);
        Atendente::create(['tenant_id' => $gerente->tenant_id, 'nome' => 'João', 'ativo' => true, 'user_id' => $userAtendente->id]);
        Sanctum::actingAs($userAtendente);

        $this->postJson('/api/v1/staff/tables', ['tipo' => 'cadeira', 'nome' => 'Nova'])->assertStatus(403);
        $this->putJson("/api/v1/staff/tables/{$ponto->uuid}", ['nome' => 'Outro'])->assertStatus(403);
        $this->deleteJson("/api/v1/staff/tables/{$ponto->uuid}")->assertStatus(403);
    }

    public function testSemLoginNaoCadastra()
    {
        $this->postJson('/api/v1/staff/tables', ['tipo' => 'cadeira', 'nome' => 'X'])->assertStatus(401);
    }

    public function testPainelEClienteVeemOTipoDoPonto()
    {
        $gerente = $this->gerente();
        $this->ponto($gerente->tenant, 'Cadeira 1', 'cadeira');
        Sanctum::actingAs($gerente);

        $this->getJson('/api/v1/staff/orders-board')->assertJsonPath('tables.0.tipo', 'cadeira');
        $this->getJson('/api/v1/tables?token_company=' . $gerente->tenant->uuid)->assertJsonPath('data.0.tipo', 'cadeira');
    }
}
