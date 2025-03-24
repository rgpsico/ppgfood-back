<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTableModeloConfiguracaoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('configuracoes_modelo', function (Blueprint $table) {
            $table->id();
            $table->string('chave')->unique(); // Ex: 'pagamento_pix'
            $table->string('tipo'); // Ex: 'boolean', 'select', 'json', etc
            $table->text('valor_padrao')->nullable(); // Ex: '1', '["pix", "cartao"]'
            $table->boolean('editavel')->default(true);
            $table->boolean('obrigatoria')->default(false);
            $table->text('descricao')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('configuracoes_modelo');
    }
}
