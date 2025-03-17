<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // Registro de Usuário
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:entregadores.usuarios,email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = Usuario::create([
            'nome' => $validated['name'],
            'email' => $validated['email'],
            'senha' => Hash::make($validated['password']),
        ]);

        return response()->json(['message' => 'Usuário registrado com sucesso!'], 201);
    }


    // Login de Usuário
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = Usuario::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->senha)) {
            return response()->json(['message' => 'As credenciais estão incorretas.'], 401);
        }

        // Criar token para o usuário
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'id' => $user->id, // Adicionando o ID do usuário na resposta
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }



    // Logout de Usuário
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Logout realizado com sucesso!']);
    }
}
