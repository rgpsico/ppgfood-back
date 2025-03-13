<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pedido extends Model
{
    protected $table = 'pedidos';
    protected $connection = 'entregadores';
    protected $fillable = [
        'cliente_nome',
        'cliente_foto',
        'endereco_entrega',
        'valor',
        'detalhes',
        'status',
    ];

    public function notificacoes()
    {
        return $this->hasMany(Notificacao::class, 'pedido_id');
    }

    public function entrega()
    {
        return $this->hasOne(Entrega::class, 'pedido_id');
    }
}
