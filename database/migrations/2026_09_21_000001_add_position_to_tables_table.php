<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPositionToTablesTable extends Migration
{
    public function up()
    {
        Schema::table('tables', function (Blueprint $table) {
            // Posicao do guarda-sol/cadeira no mapa da praia, em porcentagem
            // (0 a 100) do container - assim funciona em qualquer tamanho de tela
            $table->float('position_x')->nullable()->after('description');
            $table->float('position_y')->nullable()->after('position_x');
        });
    }

    public function down()
    {
        Schema::table('tables', function (Blueprint $table) {
            $table->dropColumn(['position_x', 'position_y']);
        });
    }
}
