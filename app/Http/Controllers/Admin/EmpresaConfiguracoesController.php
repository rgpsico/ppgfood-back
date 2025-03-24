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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class EmpresaConfiguracoesController  extends Controller
{


    public function index(Request $request)
    {

        $filtro = $request->get('filtro');
        $query = ConfiguracaoModelo::query();

        if ($filtro) {
            $query->where('chave', 'like', "%{$filtro}%");
        }

        $configuracoes = $query->paginate(10);

        return view('admin.pages.empresaconfiguracao.index', compact('configuracoes'));
    }

    public function create()
    {


        return view('admin.pages.empresaconfiguracao.create');
    }

    // public function update(Request $request)
    // {
    //     $empresaId = Auth::user()->empresa_id;

    //     $configuracoes = [
    //         'payment_methods' => json_encode($request->input('payment_methods', [])),
    //         'delivery_mode' => $request->input('delivery_mode'),
    //     ];

    //     foreach ($configuracoes as $chave => $valor) {
    //         Configuracao::updateOrCreate(
    //             ['tenant_id' => $empresaId, 'chave' => $chave],
    //             ['valor' => $valor, 'tipo' => is_array($valor) ? 'json' : 'string']
    //         );
    //     }

    //     return redirect()->back()->with('success', 'Configurações salvas com sucesso.');
    // }

    public function store(Request $request)
    {

        $request->validate([
            'chave' => 'required|string|unique:configuracoes_modelo,chave',
            'tipo' => 'required|in:boolean,string,json,select',
            'valor' => 'nullable|string',
            'descricao' => 'nullable|string',
        ]);

        ConfiguracaoModelo::create([
            'tenant_id' => null, // configuração global
            'chave' => $request->chave,
            'valor_padrao' => $request->valor,
            'tipo' => $request->tipo,
            'descricao' => $request->descricao,
        ]);


        return redirect()->route('empresa.config.index')->with('success', 'Configuração criada com sucesso!');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'valor_padrao' => 'nullable|string',
            'descricao' => 'nullable|string',
        ]);

        $config = ConfiguracaoModelo::findOrFail($id);
        $config->update([
            'valor_padrao' => $request->valor_padrao,
            'descricao' => $request->descricao,
        ]);

        return redirect()->back()->with('success', 'Configuração atualizada com sucesso!');
    }

    public function destroy($id)
    {
        $config = ConfiguracaoModelo::findOrFail($id);
        $config->delete();

        return response()->json(['success' => true]);
    }
}
