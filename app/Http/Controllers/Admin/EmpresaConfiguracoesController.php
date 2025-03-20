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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class EmpresaConfiguracoesController  extends Controller
{

    public function index()
    {
        $empresaId = Auth::user()->empresa_id;
        $settings = Configuracao::where('tenant_id', $empresaId)
            ->pluck('valor', 'chave')
            ->toArray();

        return view('admin.pages.empresaconfiguracao.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $empresaId = Auth::user()->empresa_id;

        $configuracoes = [
            'payment_methods' => json_encode($request->input('payment_methods', [])),
            'delivery_mode' => $request->input('delivery_mode'),
        ];

        foreach ($configuracoes as $chave => $valor) {
            Configuracao::updateOrCreate(
                ['tenant_id' => $empresaId, 'chave' => $chave],
                ['valor' => $valor, 'tipo' => is_array($valor) ? 'json' : 'string']
            );
        }

        return redirect()->back()->with('success', 'Configurações salvas com sucesso.');
    }
}
