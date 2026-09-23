<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBeachGridPositionToTablesTable extends Migration
{
    public function up()
    {
        Schema::table('tables', function (Blueprint $table) {
            // Posicao em linha/coluna pra visao "Praia" do painel - separada
            // de position_x/position_y (que sao percentuais, do mapa livre).
            // Linha/coluna funciona igual em qualquer tamanho de tela, ja
            // que o numero de colunas e fixo (configuravel), nao responsivo.
            $table->unsignedInteger('beach_row')->nullable()->after('position_y');
            $table->unsignedInteger('beach_col')->nullable()->after('beach_row');
        });
    }

    public function down()
    {
        Schema::table('tables', function (Blueprint $table) {
            $table->dropColumn(['beach_row', 'beach_col']);
        });
    }
}
