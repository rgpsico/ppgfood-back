<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AsaasService;
use App\Services\ClientService;
use App\Models\Client;
use App\Models\Configuracao;
use App\Models\ConfiguracaoModelo;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ConfiguracoesController extends Controller
{


    public function index()
    {

        $tenantId = auth()->user()->tenant_id;

        $configuracoes_modelo = ConfiguracaoModelo::all();

        // Busca as configurações salvas da empresa e agrupa por chave
        $configuracoes_empresa = Configuracao::with('modelo')
            ->where('tenant_id', $tenantId)
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->modelo->chave => $item->valor];
            })
            ->toArray();

        return view('admin.pages.configuracoes.index', compact('configuracoes_modelo', 'configuracoes_empresa'));
    }



    public function update(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;


        foreach ($request->input('config', []) as $chave => $valor) {
            $modelo = ConfiguracaoModelo::where('chave', $chave)->first();

            if (!$modelo) {
                continue;
            }

            Configuracao::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'configuracoes_modelo_id' => $modelo->id,
                ],
                [
                    'valor' => $valor,
                ]
            );
        }

        return redirect()->back()->with('success', 'Configurações salvas com sucesso!');
    }

    public function getConfig($uuid)
    {
        $tenantId = auth()->user()->tenant_id;

        $config = Configuracao::where('tenant_id', $tenantId);

        return response()->json([
            'habilitar_codigo_entregador' => $config->habilitar_codigo_entregador
        ]);
    }
}
