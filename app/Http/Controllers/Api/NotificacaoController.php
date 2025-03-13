<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notificacao;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Request;

class NotificacaoController extends Controller
{
    // Listar todas as notificações
    public function index()
    {
        $notificacoes = Notificacao::with(['usuario', 'pedido'])->get();
        return response()->json($notificacoes);
    }

    // Criar uma nova notificação
    public function store(Request $request)
    {
        $validated = $request->validate([
            'usuario_id' => 'required|exists:usuarios,id',
            'pedido_id' => 'required|exists:pedidos,id',
            'status' => 'required|in:nova,visualizada',
        ]);

        $notificacao = Notificacao::create($validated);

        return response()->json([
            'message' => 'Notificação criada com sucesso!',
            'notificacao' => $notificacao,
        ], 201);
    }

    // Mostrar uma notificação específica
    public function show($id)
    {
        $notificacao = Notificacao::with(['usuario', 'pedido'])->find($id);

        if (!$notificacao) {
            return response()->json(['message' => 'Notificação não encontrada'], 404);
        }

        return response()->json($notificacao);
    }

    // Atualizar o status de uma notificação
    public function update(Request $request, $id)
    {
        $notificacao = Notificacao::find($id);

        if (!$notificacao) {
            return response()->json(['message' => 'Notificação não encontrada'], 404);
        }

        $validated = $request->validate([
            'status' => 'required|in:nova,visualizada',
        ]);

        $notificacao->update($validated);

        return response()->json([
            'message' => 'Notificação atualizada com sucesso!',
            'notificacao' => $notificacao,
        ]);
    }

    // Deletar uma notificação
    public function destroy($id)
    {
        $notificacao = Notificacao::find($id);

        if (!$notificacao) {
            return response()->json(['message' => 'Notificação não encontrada'], 404);
        }

        $notificacao->delete();

        return response()->json(['message' => 'Notificação deletada com sucesso!']);
    }

    public function enviarnotificacao(Request $request)
    {

        $url = 'http://www.comunidadeppg.com.br:3000/enviarpedidoparaentregadores';

        $client = new Client();

        try {
            $response = $client->post($url, [
                'json' => $request->all() // Envia os dados no formato JSON
            ]);

            // Retorna a resposta como um objeto JSON
            return json_decode($response->getBody()->getContents());
        } catch (RequestException $e) {
            // Em caso de erro, retorna a mensagem
            return [
                'error' => true,
                'message' => $e->getMessage()
            ];
        }
    }
}
