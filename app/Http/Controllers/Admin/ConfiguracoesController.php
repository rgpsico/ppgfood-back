<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ConfigService;
use Illuminate\Http\Request;

class ConfiguracoesController extends Controller
{
    protected $configService;

    public function __construct(ConfigService $configService)
    {
        $this->configService = $configService;
    }

    public function index()
    {
        $tenantId = auth()->user()->tenant_id;

        $configs = $this->configService->getTenantConfigs(auth()->user()->tenant->uuid);

        $configuracoes_empresa = $configs->mapWithKeys(function ($config) {
            return [$config->modelo->chave => $config->valor];
        })->toArray();

        return view('admin.pages.configuracoes.index', [
            'configuracoes_empresa' => $configuracoes_empresa,
            'configuracoes_modelo' => \App\Models\ConfiguracaoModelo::all()
        ]);
    }

    public function update(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $this->configService->updateTenantConfigs($tenantId, $request->input('config', []));

        return redirect()->back()->with('success', 'Configurações salvas com sucesso!');
    }

    public function getConfig($uuid)
    {

        $valor = $this->configService->getConfigValue($uuid, 'habilitar_codigo_entregador');

        return response()->json([
            'habilitar_codigo_entregador' => (bool)$valor
        ]);
    }
}
