<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AsaasService;
use App\Services\ClientService;
use App\Models\Client;
use App\Models\Configuracao;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ConfiguracoesController extends Controller
{


    public function index()
    {

        return view('admin.pages.configuracoes.index');
    }

    public function update(Request $request)
    {
        $configuracoes = [
            'payment_methods' => json_encode($request->input('payment_methods', [])),
            'delivery_mode' => $request->input('delivery_mode'),
        ];

        foreach ($configuracoes as $chave => $valor) {
            Configuracao::updateOrCreate(
                ['tenant_id' => null, 'chave' => $chave],
                ['valor' => $valor, 'tipo' => is_array($valor) ? 'json' : 'string']
            );
        }

        return redirect()->back()->with('success', 'Configurações atualizadas com sucesso.');
    }
}
