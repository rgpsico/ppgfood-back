<?php

namespace App\Services;

use App\Models\Atendente;
use App\Models\Tenant;
use Illuminate\Validation\ValidationException;

/**
 * Atendimento por funcionario, 100% parametrizado por empresa (config_empresa).
 * Desligado por padrao: sem a chave "atendimento_funcionario_habilitado" nada muda no fluxo de pedidos.
 */
class AtendimentoService
{
    private function ligada(string $chave, int $tenantId): bool
    {
        return config_empresa($chave, $tenantId) === '1';
    }

    public function habilitado(int $tenantId): bool
    {
        return $this->ligada('atendimento_funcionario_habilitado', $tenantId);
    }

    /** Flags que o cardapio precisa para montar (ou esconder) a escolha do atendente. */
    public function opcoes(int $tenantId): array
    {
        $habilitado = $this->habilitado($tenantId);
        $obrigatorio = $habilitado && $this->ligada('atendimento_funcionario_obrigatorio', $tenantId);

        return [
            'habilitado' => $habilitado,
            'obrigatorio' => $obrigatorio,
            // obrigatorio vence "permitir sem atendente" quando as duas estiverem ligadas
            'permitir_sem_atendente' => $habilitado && !$obrigatorio && $this->ligada('atendimento_permitir_sem_funcionario', $tenantId),
            'memorizar' => $habilitado && $this->ligada('atendimento_memorizar_funcionario', $tenantId),
            'permitir_troca' => $habilitado && $this->ligada('atendimento_permitir_troca_funcionario', $tenantId),
            'exibir_no_pedido' => $habilitado && $this->ligada('atendimento_exibir_funcionario_pedido', $tenantId),
            'exigir_localizacao' => $habilitado && $this->ligada('atendimento_exigir_localizacao', $tenantId),
        ];
    }

    /** Atendentes ativos da empresa (nunca de outra), so o que o cliente precisa ver. */
    public function atendentesAtivos(int $tenantId)
    {
        if (!$this->habilitado($tenantId)) {
            return collect();
        }

        return Atendente::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->ativos()
            ->orderBy('nome')
            ->get(['uuid', 'nome']);
    }

    /**
     * Valida no backend o atendente informado no pedido (o front nao e confiavel).
     * Devolve o atendente a gravar no pedido, ou null quando nao ha atendente.
     *
     * @throws ValidationException
     */
    public function resolverParaPedido(Tenant $tenant, ?string $uuid, bool $temLocal): ?Atendente
    {
        $opcoes = $this->opcoes($tenant->id);

        // Modulo desligado: ignora o que vier no pedido, fluxo identico ao de antes
        if (!$opcoes['habilitado']) {
            return null;
        }

        if ($opcoes['exigir_localizacao'] && !$temLocal) {
            throw ValidationException::withMessages([
                'table' => ['Escaneie o QR Code do guarda-sol ou local para fazer o pedido.'],
            ]);
        }

        if (!$uuid) {
            if ($opcoes['obrigatorio'] || !$opcoes['permitir_sem_atendente']) {
                throw ValidationException::withMessages([
                    'attendant' => ['Escolha quem está atendendo você.'],
                ]);
            }

            return null;
        }

        $atendente = Atendente::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('uuid', $uuid)
            ->ativos()
            ->first();

        if (!$atendente) {
            throw ValidationException::withMessages([
                'attendant' => ['Atendente indisponível. Escolha outro.'],
            ]);
        }

        return $atendente;
    }

    public function deveNotificar(Tenant $tenant, ?Atendente $atendente): bool
    {
        return $atendente
            && $atendente->user_id
            && $this->ligada('atendimento_notificar_funcionario', $tenant->id);
    }
}
