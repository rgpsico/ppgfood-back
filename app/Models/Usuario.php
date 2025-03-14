<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Usuario extends Authenticatable
{
    use HasApiTokens, HasFactory;
    protected $table = 'usuarios';
    protected $connection = 'entregadores';
    protected $fillable = [
        'nome',
        'email',
        'senha',
        'telefone',
        'endereco'
    ];

    public function notificacoes()
    {
        return $this->hasMany(Notificacao::class, 'usuario_id');
    }

    public function entregas()
    {
        return $this->hasMany(Entrega::class, 'usuario_id');
    }
}
