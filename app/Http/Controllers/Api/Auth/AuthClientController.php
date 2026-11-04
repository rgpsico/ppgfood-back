<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use App\Support\Telefone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthClientController extends Controller
{
    public function auth(Request $request)
    {
        // "login" e o celular (ou o e-mail das contas antigas). "email" continua valendo para apps ja abertos.
        $request->validate([
            'login' => 'required_without:email',
            'email' => 'required_without:login',
            'password' => 'required',
            'device_name' => 'required',
        ]);

        $identificador = trim((string) ($request->input('login') ?: $request->input('email')));

        if (strpos($identificador, '@') !== false) {
            $candidatos = Client::where('email', $identificador)->get();
        } else {
            // o mesmo celular pode ter mais de uma conta antiga: vale a que bater com a senha
            $candidatos = Client::where('telefone', Telefone::normalizar($identificador))->get();
        }

        $client = $candidatos->first(fn ($c) => Hash::check($request->password, $c->password));

        if (!$client) {
            return response()->json(['message' => trans('messages.invalid_credentials')], 404);
        }

        $token = $client->createToken($request->device_name)->plainTextToken;

        return response()->json(['token' => $token]);
    }

    public function me(Request $request)
    {
        $client = $request->user();

        return new ClientResource($client);
    }

    public function logout(Request $request)
    {
        $client = $request->user();

        // Revoke all tokens client...
        $client->tokens()->delete();

        return response()->json([], 204);
    }
}
