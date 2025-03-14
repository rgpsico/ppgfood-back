<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Entrega extends Model
{


    protected $connection = 'entregadores';

    protected $table = 'entregas';

    protected $fillable = [
        'usuario_id',
        'pedido_id',
        'status',
        'data_entrega',
        'valor_da_entrega',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function pedido()
    {
        return $this->belongsTo(Pedido::class, 'pedido_id');
    }
}
