<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAtendentesTable extends Migration
{
    public function up()
    {
        Schema::create('atendentes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('tenant_id');
            // Login do painel (opcional): quem tem usuario entra no painel e ve so os proprios pedidos
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('nome');
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'ativo']);
            $table->unique('user_id');

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('atendentes');
    }
}
