<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConfiguracoesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('configuracoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('chave')->index();
            $table->text('valor');
            $table->text('descricao')->nullable();
            $table->enum('tipo', ['string', 'integer', 'boolean', 'json'])->default('string');
            $table->timestamps();

            $table->unique(['tenant_id', 'chave']); // Garante que cada empresa tenha apenas uma configuração por chave
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('configuracoes');
    }
}
