<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Configuracao extends Model
{
    public $table = 'configuracoes';

    protected $fillable = ['empresa_id', 'chave', 'valor', 'tipo', 'descricao'];

    public function empresa()
    {
        return $this->belongsTo(Tenant::class);
    }
}
