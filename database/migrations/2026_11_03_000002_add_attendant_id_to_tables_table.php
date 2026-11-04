<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAttendantIdToTablesTable extends Migration
{
    public function up()
    {
        Schema::table('tables', function (Blueprint $table) {
            // Atendente dono do ponto: quem cadastrou pelo painel. Pontos antigos e os do gerente ficam null
            $table->unsignedBigInteger('attendant_id')->nullable()->after('tipo');
            $table->index('attendant_id');
            $table->foreign('attendant_id')->references('id')->on('atendentes')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('tables', function (Blueprint $table) {
            $table->dropForeign(['attendant_id']);
            $table->dropIndex(['attendant_id']);
            $table->dropColumn('attendant_id');
        });
    }
}
