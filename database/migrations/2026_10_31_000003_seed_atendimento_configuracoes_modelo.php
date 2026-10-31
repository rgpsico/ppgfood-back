<?php

use App\Models\ConfiguracaoModelo;
use Illuminate\Database\Migrations\Migration;

/**
 * Chaves da tela "Minhas Configuracoes" para o atendimento por funcionario.
 * Tudo desligado por padrao nas que mudam o fluxo: quem nao ativa o modulo nao percebe nada.
 */
class SeedAtendimentoConfiguracoesModelo extends Migration
{
    private $chaves = [
        'atendimento_funcionario_habilitado' => ['0', 'Atendimento por funcionário: o cliente escolhe quem está atendendo'],
        'atendimento_funcionario_obrigatorio' => ['0', 'Atendimento por funcionário: escolher o atendente é obrigatório para pedir'],
        'atendimento_permitir_sem_funcionario' => ['1', 'Atendimento por funcionário: mostrar a opção "Ainda não tenho atendente"'],
        'atendimento_memorizar_funcionario' => ['1', 'Atendimento por funcionário: lembrar o atendente escolhido neste aparelho'],
        'atendimento_permitir_troca_funcionario' => ['1', 'Atendimento por funcionário: o cliente pode trocar de atendente'],
        'atendimento_exibir_funcionario_pedido' => ['1', 'Atendimento por funcionário: mostrar o atendente no pedido do cliente'],
        'atendimento_notificar_funcionario' => ['1', 'Atendimento por funcionário: avisar o atendente quando chegar um pedido dele'],
        'atendimento_exigir_localizacao' => ['0', 'Atendimento por funcionário: exigir guarda-sol/local (QR) para pedir'],
    ];

    public function up()
    {
        foreach ($this->chaves as $chave => [$padrao, $descricao]) {
            ConfiguracaoModelo::firstOrCreate(
                ['chave' => $chave],
                ['tipo' => 'boolean', 'valor_padrao' => $padrao, 'editavel' => 1, 'obrigatoria' => 0, 'descricao' => $descricao]
            );
        }
    }

    public function down()
    {
        ConfiguracaoModelo::whereIn('chave', array_keys($this->chaves))->delete();
    }
}
