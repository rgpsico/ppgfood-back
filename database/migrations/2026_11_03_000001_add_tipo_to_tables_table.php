<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTipoToTablesTable extends Migration
{
    public function up()
    {
        Schema::table('tables', function (Blueprint $table) {
            // guarda_sol (com cadeiras) ou cadeira (cliente pega so a cadeira).
            // Os pontos que ja existem sao guarda-sois.
            $table->string('tipo', 20)->default('guarda_sol')->after('identify');
        });
    }

    public function down()
    {
        Schema::table('tables', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });
    }
}
