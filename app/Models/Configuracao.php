<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Configuracao extends Model
{
    public $table = 'configuracoes';

    protected $fillable = ['tenant_id', 'configuracoes_modelo_id', 'valor'];

    public function empresa()
    {
        return $this->belongsTo(Tenant::class);
    }
}
