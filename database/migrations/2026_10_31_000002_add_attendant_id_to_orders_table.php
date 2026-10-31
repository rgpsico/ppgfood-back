<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAttendantIdToOrdersTable extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            // Pedidos antigos e lojas sem atendimento por funcionario ficam com null
            $table->unsignedBigInteger('attendant_id')->nullable()->after('table_id');
            $table->index('attendant_id');
            $table->foreign('attendant_id')->references('id')->on('atendentes')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['attendant_id']);
            $table->dropIndex(['attendant_id']);
            $table->dropColumn('attendant_id');
        });
    }
}
