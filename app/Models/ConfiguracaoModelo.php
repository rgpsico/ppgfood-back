<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfiguracaoModelo extends Model
{
    public $table = 'configuracoes_modelo';

    protected $fillable = ['chave', 'tipo', 'valor_padrao', 'editavel', 'obrigatoria', 'descricao'];

    public function empresa()
    {
        return $this->belongsTo(Tenant::class);
    }
}
