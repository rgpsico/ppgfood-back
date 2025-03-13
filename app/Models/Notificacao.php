<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notificacao extends Model
{
    use HasFactory;

    protected $table = 'notificacoes';
    protected $connection = 'entregadores';
    protected $fillable = [
        'usuario_id',
        'pedido_id',
        'status',
    ];

    // Relação com Usuario
    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }

    // Relação com Pedido
    public function pedido()
    {
        return $this->belongsTo(Pedido::class);
    }
}
