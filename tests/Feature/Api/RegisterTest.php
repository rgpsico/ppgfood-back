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


    public function testCadastroSoComNomeCelularESenha()
    {
        \Illuminate\Support\Facades\Http::fake();

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Carlos Praia',
            'telefone' => '(21) 99999-1234',
            'password' => '1234',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Carlos Praia')
            ->assertJsonPath('data.telefone', '21999991234');

        $this->assertDatabaseHas('clients', ['name' => 'Carlos Praia', 'telefone' => '21999991234', 'email' => null, 'instagran' => null]);
        // sem CPF nao ha cliente no Asaas para criar: nenhuma chamada externa
        \Illuminate\Support\Facades\Http::assertNothingSent();
    }

    public function testCelularEhGuardadoSoComDigitosESemOMais55()
    {
        foreach (['+55 21 99999-1234', '5521999991234', '21 99999 1234'] as $i => $digitado) {
            \App\Models\Client::query()->delete();

            $this->postJson('/api/auth/register', ['name' => 'Ana', 'telefone' => $digitado, 'password' => '1234'])
                ->assertStatus(201)
                ->assertJsonPath('data.telefone', '21999991234');
        }
    }

    public function testCelularJaCadastradoEmQualquerFormatoDa422ComMensagemClara()
    {
        $this->postJson('/api/auth/register', ['name' => 'Ana', 'telefone' => '21999991234', 'password' => '1234'])->assertStatus(201);

        $this->postJson('/api/auth/register', ['name' => 'Outra', 'telefone' => '+55 (21) 99999-1234', 'password' => '9999'])
            ->assertStatus(422)
            ->assertJsonPath('errors.telefone.0', 'Este celular já tem cadastro. Toque em "Entrar" e use sua senha.');
    }

    public function testValidaCelularSenhaENome()
    {
        $this->postJson('/api/auth/register', ['name' => 'Ana', 'telefone' => '12345', 'password' => '1234'])
            ->assertStatus(422)->assertJsonValidationErrors('telefone');
        $this->postJson('/api/auth/register', ['name' => 'Ana', 'telefone' => '21999991234', 'password' => '123'])
            ->assertStatus(422)->assertJsonValidationErrors('password');
        $this->postJson('/api/auth/register', ['telefone' => '21999991234', 'password' => '1234'])
            ->assertStatus(422)->assertJsonValidationErrors('name');
    }

    public function testEmailCpfEInstagramSaoOpcionaisMasSeVieremValem()
    {
        \Illuminate\Support\Facades\Http::fake();

        $this->postJson('/api/auth/register', [
            'name' => 'Bia', 'telefone' => '21988887777', 'password' => '1234',
            'email' => 'bia@exemplo.com', 'instagran' => '@bia', 'cpf' => '12345678900',
        ])->assertStatus(201);

        // e-mail repetido continua barrado
        $this->postJson('/api/auth/register', [
            'name' => 'Bia 2', 'telefone' => '21977776666', 'password' => '1234', 'email' => 'bia@exemplo.com',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function testDoisClientesSemEmailNemInstagramConvivem()
    {
        $this->postJson('/api/auth/register', ['name' => 'Um', 'telefone' => '21911112222', 'password' => '1234'])->assertStatus(201);
        $this->postJson('/api/auth/register', ['name' => 'Dois', 'telefone' => '21933334444', 'password' => '1234'])->assertStatus(201);

        $this->assertSame(2, \App\Models\Client::count());
    }

    public function testEntraComOCelularEmQualquerFormato()
    {
        $this->postJson('/api/auth/register', ['name' => 'Ana', 'telefone' => '21999991234', 'password' => 'abcd'])->assertStatus(201);

        foreach (['21999991234', '(21) 99999-1234', '+55 21 99999-1234'] as $digitado) {
            $this->postJson('/api/auth/token', ['login' => $digitado, 'password' => 'abcd', 'device_name' => 'web'])
                ->assertStatus(200)->assertJsonStructure(['token']);
        }

        $this->postJson('/api/auth/token', ['login' => '21999991234', 'password' => 'errada', 'device_name' => 'web'])->assertStatus(404);
        $this->postJson('/api/auth/token', ['login' => '21000000000', 'password' => 'abcd', 'device_name' => 'web'])->assertStatus(404);
    }

    public function testContaAntigaContinuaEntrandoComEmailEPeloCampoEmail()
    {
        $cliente = factory(\App\Models\Client::class)->create(['email' => 'antigo@exemplo.com', 'password' => bcrypt('segredo')]);

        $this->postJson('/api/auth/token', ['login' => 'antigo@exemplo.com', 'password' => 'segredo', 'device_name' => 'web'])->assertStatus(200);
        // app ja aberto no celular ainda manda "email"
        $this->postJson('/api/auth/token', ['email' => 'antigo@exemplo.com', 'password' => 'segredo', 'device_name' => 'web'])->assertStatus(200);
    }

    public function testContasAntigasComOMesmoCelularEntramPelaSenhaCerta()
    {
        factory(\App\Models\Client::class)->create(['telefone' => '21990271287', 'password' => bcrypt('primeira')]);
        factory(\App\Models\Client::class)->create(['telefone' => '21990271287', 'password' => bcrypt('segunda')]);

        $this->postJson('/api/auth/token', ['login' => '21990271287', 'password' => 'segunda', 'device_name' => 'web'])->assertStatus(200);
        $this->postJson('/api/auth/token', ['login' => '21990271287', 'password' => 'terceira', 'device_name' => 'web'])->assertStatus(404);
    }

    public function testLoginSemIdentificadorDa422()
    {
        $this->postJson('/api/auth/token', ['password' => 'x', 'device_name' => 'web'])->assertStatus(422);
    }
}
