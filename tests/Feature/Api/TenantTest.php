<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class TenantTest extends TestCase
{
    /**
     * Test Get All Tenants
     *
     * @return void
     */
    public function testGetAllTenants()
    {
        // a lista publica traz so barracas com assinatura ativa
        factory(Tenant::class, 10)->create(['subscription_active' => 1]);
        factory(Tenant::class)->create(['subscription_active' => 0]);

        $response = $this->getJson('/api/v1/tenants');

        $response->assertStatus(200)
                    ->assertJsonCount(10, 'data');
    }

    /**
     * Test Get Error Single Tenant
     *
     * @return void
     */
    public function testErrorGetTenant()
    {
        $tenant = 'fake_value';

        $response = $this->getJson("/api/v1/tenants/{$tenant}");

        $response->assertStatus(404);
    }

    /**
     * Test Get Error Single Tenant
     *
     * @return void
     */
    public function testGetTenantByIdentify()
    {
        $tenant = factory(Tenant::class)->create();

        $response = $this->getJson("/api/v1/tenants/{$tenant->uuid}");

        $response->assertStatus(200);
    }
}
