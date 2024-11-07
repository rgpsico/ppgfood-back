<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Model;

class Client extends Authenticatable
{
    use HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'cpfcnpj',
        'password',
        'tenant_id',
        'endereco',
        'instagran',
        'telefone'
    ];


    public function orders()
    {
        return $this->hasMany(Order::class);
    }


    public function evaluations()
    {
        return $this->hasMany(Evaluation::class);
    }
}
