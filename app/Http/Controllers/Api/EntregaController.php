<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Entrega;
use Carbon\Carbon;
use Illuminate\Http\Request;

class EntregaController extends Controller
{
    // Listar todas as entregas


    // Criar uma nova entrega
    public function store(Request $request)
    {
        $validated = $request->validate([
            'usuario_id' => 'required|exists:usuarios,id',
            'pedido_id' => 'required|exists:pedidos,id',
            'status' => 'required|in:pendente,finalizada,cancelada',
            'data_entrega' => 'nullable|date',
            'valor_da_entrega' => 'nullable|numeric|min:0', // Validando o campo
        ]);

        $entrega = Entrega::create($validated);

        return response()->json([
            'message' => 'Entrega criada com sucesso!',
            'entrega' => $entrega,
        ], 201);
    }

    // Mostrar uma entrega específica
    public function show($id)
    {
        $entrega = Entrega::with(['usuario', 'pedido'])->find($id);

        if (!$entrega) {
            return response()->json(['message' => 'Entrega não encontradaaa'], 404);
        }

        return response()->json($entrega);
    }

    public function index(Request $request)
    {
        // Obtém o usuário autenticado
        $usuario = auth()->user()->id;



        if (!$usuario) {
            return response()->json(['message' => 'Usuário não autenticado'], 401);
        }

        // Recupera todas as entregas do usuário autenticado
        $entregas = Entrega::with(['pedido']) // Inclui os relacionamentos necessários
            ->where('usuario_id', $usuario) // Filtra pelo ID do usuário autenticado
            ->get();

        if ($entregas->isEmpty()) {
            return response()->json(['message' => 'Nenhuma entrega encontrada para este usuário'], 404);
        }

        return response()->json($entregas);
    }


    // Atualizar uma entrega
    public function update(Request $request, $id)
    {
        $entrega = Entrega::find($id);

        if (!$entrega) {
            return response()->json(['message' => 'Entrega não encontrada'], 404);
        }

        $validated = $request->validate([
            'status' => 'required|in:pendente,finalizada,cancelada',
            'data_entrega' => 'nullable|date',
        ]);

        $entrega->update($validated);

        return response()->json([
            'message' => 'Entrega atualizada com sucesso!',
            'entrega' => $entrega,
        ]);
    }

    // Deletar uma entrega
    public function destroy($id)
    {
        $entrega = Entrega::find($id);

        if (!$entrega) {
            return response()->json(['message' => 'Entrega não encontrada'], 404);
        }

        $entrega->delete();

        return response()->json(['message' => 'Entrega deletada com sucesso!']);
    }

    public function dashboard(Request $request)
    {
        $usuario = $request->user_id;

        dd($usuario);
        $request->validate([
            'dataInicio' => 'nullable|date',
            'dataFim' => 'nullable|date',
        ]);

        $dataInicio = $request->input('dataInicio')
            ? Carbon::parse($request->input('dataInicio'))->startOfDay()
            : Carbon::today()->startOfDay();

        $dataFim = $request->input('dataFim')
            ? Carbon::parse($request->input('dataFim'))->endOfDay()
            : Carbon::today()->endOfDay();

        $entregasDoDia = Entrega::where('usuario_id', $usuario->id)
            ->whereBetween('data_entrega', [$dataInicio, $dataFim]);

        $cancelamentos = (clone $entregasDoDia)->where('status', 'cancelada')->count();
        $ganhos = (clone $entregasDoDia)->where('status', 'finalizada')->sum('valor_da_entrega');

        return response()->json([
            'dataInicio' => $dataInicio->toDateString(),
            'dataFim' => $dataFim->toDateString(),
            'entregas_do_dia' => $entregasDoDia->count(),
            'cancelamentos' => $cancelamentos,
            'ganhos' => number_format($ganhos, 2, '.', ''),
        ]);
    }
}
