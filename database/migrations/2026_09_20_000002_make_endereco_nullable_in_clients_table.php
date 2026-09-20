<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class MakeEnderecoNullableInClientsTable extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE clients MODIFY endereco VARCHAR(255) NULL');
    }

    public function down()
    {
        DB::statement("ALTER TABLE clients MODIFY endereco VARCHAR(255) NOT NULL DEFAULT ''");
    }
}
