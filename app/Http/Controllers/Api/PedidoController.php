<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use Illuminate\Http\Request;

class PedidoController extends Controller
{
    // Listar todos os pedidos
    public function index()
    {
        $pedidos = Pedido::all();
        return response()->json($pedidos);
    }

    // Criar um novo pedido
    public function store(Request $request)
    {
        $validated = $request->validate([
            'cliente_nome' => 'required|string|max:100',
            'cliente_foto' => 'nullable|string',
            'endereco_entrega' => 'required|string|max:255',
            'valor' => 'required|numeric|min:0',
            'detalhes' => 'nullable|string',
            'status' => 'required|in:pendente,aceito,recusado,entregue,cancelado',
        ]);

        $pedido = Pedido::create($validated);

        return response()->json([
            'message' => 'Pedido criado com sucesso!',
            'pedido' => $pedido
        ], 201);
    }

    // Mostrar um pedido específico
    public function show($id)
    {
        $pedido = Pedido::find($id);

        if (!$pedido) {
            return response()->json(['message' => 'Pedido não encontrado'], 404);
        }

        return response()->json($pedido);
    }

    // Atualizar um pedido
    public function update(Request $request, $id)
    {
        $pedido = Pedido::find($id);

        if (!$pedido) {
            return response()->json(['message' => 'Pedido não encontrado'], 404);
        }

        $validated = $request->validate([
            'cliente_nome' => 'sometimes|required|string|max:100',
            'cliente_foto' => 'nullable|string',
            'endereco_entrega' => 'sometimes|required|string|max:255',
            'valor' => 'sometimes|required|numeric|min:0',
            'detalhes' => 'nullable|string',
            'status' => 'sometimes|required|in:pendente,aceito,recusado,entregue,cancelado',
        ]);

        $pedido->update($validated);

        return response()->json([
            'message' => 'Pedido atualizado com sucesso!',
            'pedido' => $pedido
        ]);
    }

    // Deletar um pedido
    public function destroy($id)
    {
        $pedido = Pedido::find($id);

        if (!$pedido) {
            return response()->json(['message' => 'Pedido não encontrado'], 404);
        }

        $pedido->delete();

        return response()->json(['message' => 'Pedido deletado com sucesso']);
    }
}
