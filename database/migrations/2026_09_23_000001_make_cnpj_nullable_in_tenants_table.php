<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class MakeCnpjNullableInTenantsTable extends Migration
{
    public function up()
    {
        // Cadastro rapido de barraca de praia nao exige CNPJ na hora -
        // o dono pode nao ter um em maos, ou o negocio ser informal
        DB::statement('ALTER TABLE tenants MODIFY cnpj VARCHAR(255) NULL');
    }

    public function down()
    {
        DB::statement("ALTER TABLE tenants MODIFY cnpj VARCHAR(255) NOT NULL DEFAULT ''");
    }
}
