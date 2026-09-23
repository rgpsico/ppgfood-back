<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\TenantFormRequest;
use App\Http\Resources\TableResource;
use App\Models\Table;
use App\Services\TableService;
use Illuminate\Http\Request;

class TableApiController extends Controller
{
    protected $tableService;

    public function __construct(TableService $tableService)
    {
        $this->tableService = $tableService;
    }

    public function tablesByTenant(TenantFormRequest $request)
    {
        // if (!$request->token_company) {
        //     return response()->json(['message' => 'Token Not Found'], 404);
        // }

        $categories = $this->tableService->getTablesByUuid($request->token_company);

        return TableResource::collection($categories);
    }


    public function show(TenantFormRequest $request, $identify)
    {
        if (!$table = $this->tableService->getTableByUuid($identify)) {
            return response()->json(['message' => 'Table Not Found'], 404);
        }

        return new TableResource($table);
    }

    /**
     * Salva a posicao (x/y em porcentagem) do guarda-sol/cadeira no mapa
     * da praia do painel - usado ao arrastar e soltar.
     */
    public function updatePosition(Request $request, $identify)
    {
        $request->validate([
            'position_x' => 'required|numeric|min:0|max:100',
            'position_y' => 'required|numeric|min:0|max:100',
        ]);

        if (!$table = Table::where('uuid', $identify)->first()) {
            return response()->json(['message' => 'Table Not Found'], 404);
        }

        $table->update([
            'position_x' => $request->position_x,
            'position_y' => $request->position_y,
        ]);

        return new TableResource($table);
    }

    /**
     * Salva a posicao em linha/coluna (grade fixa) da visao "Praia" -
     * diferente do mapa livre, funciona igual em qualquer tamanho de tela
     * porque o numero de colunas nao muda por breakpoint.
     */
    public function updateBeachPosition(Request $request, $identify)
    {
        $request->validate([
            'beach_row' => 'required|integer|min:0',
            'beach_col' => 'required|integer|min:0',
        ]);

        if (!$table = Table::where('uuid', $identify)->first()) {
            return response()->json(['message' => 'Table Not Found'], 404);
        }

        $table->update([
            'beach_row' => $request->beach_row,
            'beach_col' => $request->beach_col,
        ]);

        return new TableResource($table);
    }
}
