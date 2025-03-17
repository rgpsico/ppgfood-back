<?php



namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UsuarioController extends Controller
{
    // Listar todos os usuários
    public function index()
    {
        return response()->json(Usuario::all());
    }


    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:usuarios,email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = Usuario::create([
            'nome' => $validated['name'],
            'email' => $validated['email'],
            'senha' => Hash::make($validated['password']),
        ]);

        return response()->json(['message' => 'Usuário registrado com sucesso!'], 201);
    }


    // Criar um novo usuário
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nome' => 'required|string|max:100',
            'email' => 'required|email|unique:usuarios,email',
            'senha' => 'required|string|min:6',
            'telefone' => 'nullable|string|max:15',
            'foto_perfil' => 'nullable|string',
        ]);

        $validated['senha'] = bcrypt($validated['senha']); // Encripta a senha

        $usuario = Usuario::create($validated);

        return response()->json($usuario, 201);
    }

    // Mostrar um usuário específico
    public function show($id)
    {
        $usuario = Usuario::find($id);

        if (!$usuario) {
            return response()->json(['message' => 'Usuário não encontrado'], 404);
        }

        return response()->json($usuario);
    }

    // Atualizar um usuário
    public function update(Request $request, $id)
    {
        $usuario = Usuario::find($id);

        if (!$usuario) {
            return response()->json(['message' => 'Usuário não encontrado'], 404);
        }

        $validated = $request->validate([
            'nome' => 'sometimes|required|string|max:100',
            'email' => 'sometimes|required|email|unique:usuarios,email,' . $id,
            'senha' => 'nullable|string|min:6',
            'telefone' => 'nullable|string|max:15',
            'foto_perfil' => 'nullable|string',
        ]);

        if (isset($validated['senha'])) {
            $validated['senha'] = bcrypt($validated['senha']);
        }

        $usuario->update($validated);

        return response()->json($usuario);
    }

    // Deletar um usuário
    public function destroy($id)
    {
        $usuario = Usuario::find($id);

        if (!$usuario) {
            return response()->json(['message' => 'Usuário não encontrado'], 404);
        }

        $usuario->delete();

        return response()->json(['message' => 'Usuário deletado com sucesso']);
    }
}
