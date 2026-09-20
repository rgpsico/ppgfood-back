<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class MakeTelefoneNullableInClientsTable extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE clients MODIFY telefone VARCHAR(255) NULL');
    }

    public function down()
    {
        DB::statement("ALTER TABLE clients MODIFY telefone VARCHAR(255) NOT NULL DEFAULT ''");
    }
}
