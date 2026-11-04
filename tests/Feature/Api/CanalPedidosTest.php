<?php

namespace Tests\Feature\Api;

use App\Events\OrderCreated;
use App\Models\Atendente;
use App\Models\Client;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\TestCase;

/**
 * O aviso de pedido novo da barraca e um canal privado: so o painel do gerente da propria barraca escuta.
 */
class CanalPedidosTest extends TestCase
{
    private function podeEntrar($usuario, string $canal): bool
    {
        $request = Request::create('/broadcasting/auth', 'POST', ['channel_name' => $canal]);
        $request->setUserResolver(fn () => $usuario);

        $broadcaster = Broadcast::driver();
        $metodo = new \ReflectionMethod($broadcaster, 'verifyUserCanAccessChannel');
        $metodo->setAccessible(true);

        try {
            $metodo->invoke($broadcaster, $request, $canal);

            return true;
        } catch (AccessDeniedHttpException $e) {
            return false;
        }
    }

    private function gerente(Tenant $tenant): User
    {
        return factory(User::class)->create(['tenant_id' => $tenant->id]);
    }

    public function testSoOGerenteDaPropriaBarracaEntraNoCanal()
    {
        $a = factory(Tenant::class)->create();
        $b = factory(Tenant::class)->create();
        $gerenteA = $this->gerente($a);

        $this->assertTrue($this->podeEntrar($gerenteA, 'order-created.' . $a->id));
        $this->assertFalse($this->podeEntrar($gerenteA, 'order-created.' . $b->id));
        $this->assertFalse($this->podeEntrar(null, 'order-created.' . $a->id));
    }

    public function testAtendenteNaoEscutaOsPedidosDaBarracaInteira()
    {
        $tenant = factory(Tenant::class)->create();
        $userAtendente = $this->gerente($tenant);
        Atendente::create(['tenant_id' => $tenant->id, 'nome' => 'João', 'ativo' => true, 'user_id' => $userAtendente->id]);

        $this->assertFalse($this->podeEntrar($userAtendente, 'order-created.' . $tenant->id));
    }

    public function testClienteLogadoNaoEntraNoCanalDeNenhumaBarraca()
    {
        $tenant = factory(Tenant::class)->create();
        $cliente = factory(Client::class)->create();

        $this->assertFalse($this->podeEntrar($cliente, 'order-created.' . $tenant->id));
    }

    public function testPedidoNovoEhTransmitidoNoCanalPrivadoDaBarraca()
    {
        $tenant = factory(Tenant::class)->create();
        $pedido = Order::create(['tenant_id' => $tenant->id, 'identify' => uniqid('p'), 'total' => 10, 'status' => 'open']);

        $canais = (new OrderCreated($pedido))->broadcastOn();
        $canais = is_array($canais) ? $canais : [$canais];

        // so o canal privado: nenhum canal publico com os dados do pedido
        $this->assertCount(1, $canais);
        $this->assertInstanceOf(PrivateChannel::class, $canais[0]);
        $this->assertSame('private-order-created.' . $tenant->id, $canais[0]->name);
    }
}
