<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Configuracao extends Model
{
    public $table = 'configuracoes';

    protected $fillable = ['tenant_id', 'configuracoes_modelo_id', 'valor'];


    public function modelo()
    {
        return $this->belongsTo(ConfiguracaoModelo::class, 'configuracoes_modelo_id');
    }


    public function empresa()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
