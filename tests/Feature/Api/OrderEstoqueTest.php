<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use Tests\TestCase;

/**
 * Pedido e baixa de estoque andam juntos: sem estoque, nenhum pedido e criado.
 */
class OrderEstoqueTest extends TestCase
{
    private function pedir(Tenant $tenant, array $itens)
    {
        return $this->postJson('/api/v1/orders', [
            'token_company' => $tenant->uuid,
            'products' => $itens,
        ]);
    }

    public function testPedidoComEstoqueBaixaOEstoque()
    {
        $tenant = factory(Tenant::class)->create();
        $produto = factory(Product::class)->create(['tenant_id' => $tenant->id, 'stock' => 10]);

        $this->pedir($tenant, [['identify' => $produto->uuid, 'qty' => 3]])->assertStatus(201);

        $this->assertSame(7, (int) Product::withoutGlobalScopes()->find($produto->id)->stock);
    }

    public function testSemEstoqueDa422ENaoGravaPedido()
    {
        $tenant = factory(Tenant::class)->create();
        $produto = factory(Product::class)->create(['tenant_id' => $tenant->id, 'stock' => 2]);

        $this->pedir($tenant, [['identify' => $produto->uuid, 'qty' => 3]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('products');

        $this->assertSame(0, Order::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        $this->assertSame(2, (int) Product::withoutGlobalScopes()->find($produto->id)->stock);
    }

    public function testFaltaDeUmItemDesfazTudoInclusiveOsOutros()
    {
        $tenant = factory(Tenant::class)->create();
        $tem = factory(Product::class)->create(['tenant_id' => $tenant->id, 'stock' => 5]);
        $acabou = factory(Product::class)->create(['tenant_id' => $tenant->id, 'stock' => 0]);

        $this->pedir($tenant, [
            ['identify' => $tem->uuid, 'qty' => 2],
            ['identify' => $acabou->uuid, 'qty' => 1],
        ])->assertStatus(422);

        $this->assertSame(0, Order::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        $this->assertSame(5, (int) Product::withoutGlobalScopes()->find($tem->id)->stock);
    }

    public function testPedirExatamenteOQueTemZeraOEstoque()
    {
        $tenant = factory(Tenant::class)->create();
        $produto = factory(Product::class)->create(['tenant_id' => $tenant->id, 'stock' => 4]);

        $this->pedir($tenant, [['identify' => $produto->uuid, 'qty' => 4]])->assertStatus(201);

        $this->assertSame(0, (int) Product::withoutGlobalScopes()->find($produto->id)->stock);
    }

    public function testNaoAceitaProdutoDeOutraBarraca()
    {
        $tenant = factory(Tenant::class)->create();
        $deOutra = factory(Product::class)->create(['stock' => 10]);

        $this->pedir($tenant, [['identify' => $deOutra->uuid, 'qty' => 1]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('products.0.identify');

        $this->assertSame(10, (int) Product::withoutGlobalScopes()->find($deOutra->id)->stock);
    }

    public function testNaoAceitaQuantidadeZeroOuNegativa()
    {
        $tenant = factory(Tenant::class)->create();
        $produto = factory(Product::class)->create(['tenant_id' => $tenant->id, 'stock' => 10]);

        $this->pedir($tenant, [['identify' => $produto->uuid, 'qty' => -5]])->assertStatus(422);
        $this->pedir($tenant, [['identify' => $produto->uuid, 'qty' => 0]])->assertStatus(422);

        $this->assertSame(10, (int) Product::withoutGlobalScopes()->find($produto->id)->stock);
        $this->assertSame(0, Order::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }
}
