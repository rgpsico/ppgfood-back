<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class MakeEmailAndInstagranOptionalInClients extends Migration
{
    public function up()
    {
        // Cadastro do cliente so com nome, celular e senha. O indice unico continua (o MySQL aceita varios NULL).
        // SQL direto: ->change() exige doctrine/dbal, que nao esta instalado em producao.
        DB::statement('ALTER TABLE clients MODIFY email VARCHAR(255) NULL');
        DB::statement('ALTER TABLE clients MODIFY instagran VARCHAR(255) NULL');
    }

    public function down()
    {
        DB::statement("UPDATE clients SET email = CONCAT('cliente', id, '@sem-email.local') WHERE email IS NULL");
        DB::statement("UPDATE clients SET instagran = CONCAT('@cliente', id) WHERE instagran IS NULL");
        DB::statement('ALTER TABLE clients MODIFY email VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE clients MODIFY instagran VARCHAR(255) NOT NULL');
    }
}
