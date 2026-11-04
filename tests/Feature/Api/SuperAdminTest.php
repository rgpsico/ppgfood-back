<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\Product;
use App\Models\Table;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tela de super admin: so o dono da plataforma (config/acl.php), enxerga todas as barracas e a saude dos servicos.
 */
class SuperAdminTest extends TestCase
{
    private $logOriginal;
    private $hostOriginal;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-04 15:00:00', 'America/Sao_Paulo'));
        $this->logOriginal = File::exists(storage_path('logs/laravel.log')) ? File::get(storage_path('logs/laravel.log')) : null;
        $this->hostOriginal = File::exists(storage_path('app/host-status.json')) ? File::get(storage_path('app/host-status.json')) : null;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->logOriginal === null ? File::delete(storage_path('logs/laravel.log')) : File::put(storage_path('logs/laravel.log'), $this->logOriginal);
        $this->hostOriginal === null ? File::delete(storage_path('app/host-status.json')) : File::put(storage_path('app/host-status.json'), $this->hostOriginal);
        parent::tearDown();
    }

    private function usuario(?Tenant $tenant = null): User
    {
        return factory(User::class)->create(['tenant_id' => ($tenant ?: factory(Tenant::class)->create())->id]);
    }

    private function admin(): User
    {
        $admin = $this->usuario();
        config(['acl.admins' => [$admin->email]]);
        Sanctum::actingAs($admin);

        return $admin;
    }

    private function pedido(Tenant $tenant, string $quando, string $status, float $total): Order
    {
        $p = Order::create(['tenant_id' => $tenant->id, 'identify' => uniqid('p'), 'total' => $total, 'status' => $status]);
        $p->forceFill(['created_at' => Carbon::parse($quando, 'America/Sao_Paulo')])->save();

        return $p;
    }

    public function testSoOAdminDaPlataformaEntra()
    {
        $this->getJson('/api/v1/staff/admin/saude')->assertStatus(401);

        $gerente = $this->usuario();
        config(['acl.admins' => ['outro@exemplo.com']]);
        Sanctum::actingAs($gerente);
        $this->getJson('/api/v1/staff/admin/saude')->assertStatus(403);
        $this->getJson('/api/v1/staff/admin/barracas')->assertStatus(403);

        $this->admin();
        $this->getJson('/api/v1/staff/admin/saude')->assertStatus(200);
        $this->getJson('/api/v1/staff/admin/barracas')->assertStatus(200);
    }

    public function testMeInformaSeEhSuperAdmin()
    {
        $this->admin();
        $this->getJson('/api/v1/staff/me')->assertJsonPath('is_super_admin', true);

        Sanctum::actingAs($this->usuario());
        $this->getJson('/api/v1/staff/me')->assertJsonPath('is_super_admin', false);
    }

    public function testListaTodasAsBarracasComNumerosDeCadaUma()
    {
        $a = factory(Tenant::class)->create(['subscription_active' => 1]);
        $b = factory(Tenant::class)->create(['subscription_active' => 0]);
        factory(Table::class, 3)->create(['tenant_id' => $a->id]);
        factory(Product::class, 2)->create(['tenant_id' => $a->id]);
        $this->pedido($a, '2026-11-04 10:00', 'done', 30);
        $this->pedido($a, '2026-11-04 11:00', 'open', 20);
        $this->pedido($a, '2026-11-02 11:00', 'done', 50);
        $this->pedido($b, '2026-11-04 09:00', 'done', 100);
        // logado so depois: com usuario logado o TenantObserver carimba o tenant dele nos registros criados
        $admin = $this->admin();

        $r = $this->getJson('/api/v1/staff/admin/barracas')->assertStatus(200)->json();

        $barraca = collect($r['barracas'])->firstWhere('id', $a->id);
        $this->assertSame(3, $barraca['guarda_sois']);
        $this->assertSame(2, $barraca['produtos']);
        $this->assertSame(3, $barraca['pedidos_total']);
        $this->assertSame(2, $barraca['pedidos_hoje']);
        $this->assertSame(30.0, (float) $barraca['vendido_hoje']);
        $this->assertSame(80.0, (float) $barraca['vendido_mes']);
        $this->assertSame(1, $barraca['pedidos_em_andamento']);
        $this->assertStringContainsString('/loja/' . $a->url, $barraca['loja']);

        // o admin (que pertence a OUTRA barraca) enxerga todas: nada de escopo por tenant aqui
        $this->assertTrue(collect($r['barracas'])->pluck('id')->contains($b->id));
        $this->assertTrue(collect($r['barracas'])->pluck('id')->contains($admin->tenant_id));

        $this->assertSame(130.0, (float) $r['totais']['vendido_hoje']);
        $this->assertGreaterThanOrEqual(3, $r['totais']['barracas']);
    }

    public function testUltimoUsoDoPainelVemDoTokenMaisRecente()
    {
        $this->admin();
        $tenant = factory(Tenant::class)->create();
        $user = $this->usuario($tenant);
        $token = $user->createToken('painel')->accessToken;
        $token->forceFill(['last_used_at' => Carbon::parse('2026-11-04 14:30:00', 'America/Sao_Paulo')])->save();

        $r = $this->getJson('/api/v1/staff/admin/barracas')->json('barracas');

        $this->assertNotNull(collect($r)->firstWhere('id', $tenant->id)['ultimo_uso_painel']);
    }

    public function testSaudeNaoQuebraQuandoUmaPecaEstaForaDoAr()
    {
        $this->admin();
        Http::fake(['*' => Http::response('', 500)]);

        $r = $this->getJson('/api/v1/staff/admin/saude')->assertStatus(200)->json();

        $porNome = collect($r['servicos'])->keyBy('nome');
        $this->assertSame('ok', $porNome['Banco de dados (MySQL)']['status']);
        $this->assertSame('erro', $porNome['Socket (tempo real)']['status']);
        $this->assertSame('erro', $r['geral']);
        $this->assertCount(7, $r['servicos']);
    }

    public function testSocketOkQuandoRespondeEAceitaHandshake()
    {
        $this->admin();
        Http::fake(['*' => Http::response('OK', 200)]);

        $socket = collect($this->getJson('/api/v1/staff/admin/saude')->json('servicos'))->firstWhere('nome', 'Socket (tempo real)');

        $this->assertSame('ok', $socket['status']);
    }

    public function testAgendadorSemSinalOuParadoDaErro()
    {
        $this->admin();
        Http::fake(['*' => Http::response('OK', 200)]);
        $status = fn () => collect($this->getJson('/api/v1/staff/admin/saude')->json('servicos'))->firstWhere('nome', 'Agendador (lembretes de pedido)')['status'];

        Cache::forget('scheduler:heartbeat');
        $this->assertSame('erro', $status());

        Cache::put('scheduler:heartbeat', now()->subSeconds(30)->toIso8601String(), 600);
        $this->assertSame('ok', $status());

        Cache::put('scheduler:heartbeat', now()->subMinutes(10)->toIso8601String(), 600);
        $this->assertSame('erro', $status());
    }

    public function testErrosRecentesDoLogContamSoAsUltimas24Horas()
    {
        $this->admin();
        Http::fake(['*' => Http::response('OK', 200)]);
        File::put(storage_path('logs/laravel.log'), implode("\n", [
            '[2026-11-01 10:00:00] production.ERROR: erro antigo que nao conta',
            '[2026-11-04 09:00:00] production.ERROR: SQLSTATE falhou ao gravar',
            '[2026-11-04 12:00:00] production.WARNING: so aviso',
            '[2026-11-04 14:00:00] production.ERROR: Undefined variable $x',
            '#0 /var/www/linha-de-stack.php',
        ]) . "\n");

        $erros = $this->getJson('/api/v1/staff/admin/saude')->json('erros');

        $this->assertSame(2, $erros['ultimas_24h']);
        $this->assertSame('Undefined variable $x', $erros['recentes'][0]['mensagem']);
    }

    public function testFotoDoServidorGravadaPeloColetor()
    {
        $this->admin();
        Http::fake(['*' => Http::response('OK', 200)]);

        File::delete(storage_path('app/host-status.json'));
        $this->assertFalse($this->getJson('/api/v1/staff/admin/saude')->json('host.disponivel'));

        File::put(storage_path('app/host-status.json'), json_encode([
            'gerado_em' => now()->subSeconds(20)->toIso8601String(),
            'containers' => [['nome' => 'ppgfood_api', 'estado' => 'running', 'status' => 'Up 2 weeks']],
        ]));
        $host = $this->getJson('/api/v1/staff/admin/saude')->json('host');
        $this->assertTrue($host['disponivel']);
        $this->assertFalse($host['desatualizado']);
        $this->assertSame('ppgfood_api', $host['containers'][0]['nome']);

        File::put(storage_path('app/host-status.json'), json_encode(['gerado_em' => now()->subMinutes(10)->toIso8601String()]));
        $this->assertTrue($this->getJson('/api/v1/staff/admin/saude')->json('host.desatualizado'));
    }
}
