<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    /** Chave publica que o navegador precisa para criar a inscricao. */
    public function key()
    {
        $chave = config('services.vapid.public');

        if (!$chave) {
            return response()->json(['message' => 'Avisos no celular nao estao configurados.'], 503);
        }

        return response()->json(['public_key' => $chave]);
    }

    public function store(Request $request)
    {
        $dados = $request->validate([
            'endpoint' => 'required|url|max:2000',
            'keys.p256dh' => 'required|string|max:255',
            'keys.auth' => 'required|string|max:255',
        ]);

        // Mesmo celular de novo (ou celular que trocou de usuario): atualiza em vez de duplicar
        PushSubscription::updateOrCreate(
            ['endpoint_hash' => PushSubscription::hashDoEndpoint($dados['endpoint'])],
            [
                'user_id' => $request->user()->id,
                'endpoint' => $dados['endpoint'],
                'p256dh' => $dados['keys']['p256dh'],
                'auth' => $dados['keys']['auth'],
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ]
        );

        return response()->json(['message' => 'Avisos ativados.'], 201);
    }

    public function test(Request $request, \App\Services\Push\PushService $push)
    {
        $enviados = $push->enviarTeste($request->user());

        if ($enviados === 0) {
            return response()->json(['message' => 'Nenhum celular com avisos ativos neste login.'], 422);
        }

        return response()->json(['message' => 'Aviso de teste enviado.', 'enviados' => $enviados]);
    }

    public function destroy(Request $request)
    {
        $request->validate(['endpoint' => 'required|url|max:2000']);

        PushSubscription::where('user_id', $request->user()->id)
            ->where('endpoint_hash', PushSubscription::hashDoEndpoint($request->endpoint))
            ->delete();

        return response()->json(['message' => 'Avisos desativados.']);
    }
}
