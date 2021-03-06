<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFiledClientTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('endereco');
            $table->string('telefone')->unique();
            $table->string('instagran')->unique();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('clients', function (Blueprint $table) {

            // 1. Drop foreign key constraints
            $table->dropColumn(['endereco']);

            // 2. Drop the column
            $table->dropColumn('telefone');
            $table->dropColumn('instagran');
        });
    }
}
