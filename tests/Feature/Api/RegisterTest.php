<?php

namespace Tests\Feature\Api;

use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    /**
     * Error create new client
     *
     * @return void
     */
    public function testErrorCreateNewClient()
    {
        $payload = [
            'name' => 'Carlos Client',
            'email' => 'carlos@client.com.br',
        ];

        $response = $this->postJson('/api/auth/register', $payload);

        $response->assertStatus(422);
                    // ->assertExactJson([
                    //     'message' => 'The given data was invalid.',
                    //     'errors' => [
                    //         'password' => [trans('validation.required', ['attribute' => 'password'])]
                    //     ]
                    // ]);
    }


    /**
     * Success create new client
     *
     * @return void
     */
    public function testSuccessCreateNewClient()
    {
        $payload = [
            'name' => 'Carlos Client',
            'email' => 'carlos@client.com.br',
            'password' => '123456',
            'cpf' => '12345678900',
            'instagran' => '@carlosclient',
        ];

        $response = $this->postJson('/api/auth/register', $payload);

        $response->assertStatus(201)
                    ->assertJsonPath('data.name', $payload['name'])
                    ->assertJsonPath('data.email', $payload['email']);
    }
}
