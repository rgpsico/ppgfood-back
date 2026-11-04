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
     * Todos os pontos da barraca (o quadro do atendente so traz os que ele esta atendendo).
     */
    public function index()
    {
        return TableResource::collection(Table::orderBy('id')->get());
    }

    /**
     * Cadastra guarda-sol ou cadeira no painel (gerente e atendente). Sem nome, numera sozinho
     * ("Guarda-sol 7", "Cadeira 3"); com quantidade > 1 cria varios de uma vez.
     */
    public function store(Request $request)
    {
        $dados = $request->validate([
            'tipo' => 'required|in:' . implode(',', Table::TIPOS),
            'nome' => 'nullable|string|min:1|max:60',
            'quantidade' => 'nullable|integer|min:1|max:30',
            'description' => 'nullable|string|max:1000',
        ]);

        $tipo = $dados['tipo'];
        $nome = trim($dados['nome'] ?? '');
        $quantidade = (int) ($dados['quantidade'] ?? 1);

        // Atendente que cadastra ja fica como o atendente desse ponto; gerente cadastra sem dono
        $atendenteId = $request->user()->atendente ? $request->user()->atendente->id : null;

        $criados = collect();

        if ($nome !== '' && $quantidade === 1) {
            if ($this->nomeEmUso($nome)) {
                return response()->json(['message' => "Já existe um ponto chamado \"{$nome}\"."], 422);
            }

            $criados->push($this->criar($nome, $tipo, $dados['description'] ?? null, $atendenteId));
        } else {
            $prefixo = $nome !== '' ? $nome : $this->rotuloDoTipo($tipo);
            $proximo = $this->proximoNumero($prefixo);

            for ($i = 0; $i < $quantidade; $i++) {
                $criados->push($this->criar($prefixo . ' ' . ($proximo + $i), $tipo, $dados['description'] ?? null, $atendenteId));
            }
        }

        return TableResource::collection($criados)->response()->setStatusCode(201);
    }

    /**
     * Renomeia ou troca o tipo (guarda-sol <-> cadeira) de um ponto.
     */
    public function update(Request $request, $identify)
    {
        if (!$table = Table::where('uuid', $identify)->first()) {
            return response()->json(['message' => 'Table Not Found'], 404);
        }

        $dados = $request->validate([
            'tipo' => 'sometimes|in:' . implode(',', Table::TIPOS),
            'nome' => 'sometimes|string|min:1|max:60',
            'description' => 'sometimes|nullable|string|max:1000',
        ]);

        if (isset($dados['nome'])) {
            $dados['nome'] = trim($dados['nome']);

            if ($dados['nome'] === '') {
                return response()->json(['message' => 'Informe o nome.'], 422);
            }

            if ($this->nomeEmUso($dados['nome'], $table->id)) {
                return response()->json(['message' => "Já existe um ponto chamado \"{$dados['nome']}\"."], 422);
            }

            $table->identify = $dados['nome'];
        }

        if (isset($dados['tipo'])) {
            $table->tipo = $dados['tipo'];
        }

        if (array_key_exists('description', $dados)) {
            $table->description = $dados['description'];
        }

        $table->save();

        return new TableResource($table);
    }

    /**
     * Remove um ponto sem pedidos. Com pedidos no historico nao apaga
     * (perderia o "onde foi entregue"): o gerente renomeia ou deixa quieto.
     */
    public function destroy(Request $request, $identify)
    {
        if ($erro = $this->soGerente($request)) {
            return $erro;
        }

        if (!$table = Table::where('uuid', $identify)->first()) {
            return response()->json(['message' => 'Table Not Found'], 404);
        }

        if ($table->orders()->exists()) {
            return response()->json([
                'message' => 'Este ponto já tem pedidos no histórico e não pode ser apagado. Renomeie se quiser reaproveitar.',
            ], 422);
        }

        $table->delete();

        return response()->json(['message' => 'Removido']);
    }

    private function soGerente(Request $request)
    {
        // Atendente so consulta: quem cadastra e mexe no mapa e o gerente da barraca
        if ($request->user()->atendente) {
            return response()->json(['message' => 'Acesso restrito ao gerente.'], 403);
        }

        return null;
    }

    private function rotuloDoTipo(string $tipo): string
    {
        return $tipo === Table::TIPO_CADEIRA ? 'Cadeira' : 'Guarda-sol';
    }

    private function nomeEmUso(string $nome, ?int $ignorarId = null): bool
    {
        return Table::where('identify', $nome)
            ->when($ignorarId, fn ($q) => $q->where('id', '!=', $ignorarId))
            ->exists();
    }

    /** Proximo numero livre depois do maior "<prefixo> N" ja cadastrado. */
    private function proximoNumero(string $prefixo): int
    {
        $maior = 0;
        $padrao = '/^' . preg_quote($prefixo, '/') . ' (\d+)$/u';

        Table::where('identify', 'like', $prefixo . ' %')->pluck('identify')->each(function ($nome) use ($padrao, &$maior) {
            if (preg_match($padrao, $nome, $m)) {
                $maior = max($maior, (int) $m[1]);
            }
        });

        return $maior + 1;
    }

    private function criar(string $nome, string $tipo, ?string $descricao, ?int $atendenteId = null): Table
    {
        return Table::create([
            'identify' => $nome,
            'tipo' => $tipo,
            'description' => $descricao,
            'attendant_id' => $atendenteId,
        ]);
    }

    /**
     * Salva a posicao (x/y em porcentagem) do guarda-sol/cadeira no mapa
     * da praia do painel - usado ao arrastar e soltar.
     */
    public function updatePosition(Request $request, $identify)
    {
        // Mapa e compartilhado: atendente nao reorganiza os guarda-sois da barraca
        if ($request->user()->atendente) {
            return response()->json(['message' => 'Acesso restrito ao gerente.'], 403);
        }

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
        // Mapa e compartilhado: atendente nao reorganiza os guarda-sois da barraca
        if ($request->user()->atendente) {
            return response()->json(['message' => 'Acesso restrito ao gerente.'], 403);
        }

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
