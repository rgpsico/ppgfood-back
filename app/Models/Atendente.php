<?php

namespace App\Models;

use App\Tenant\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Atendente extends Model
{
    use TenantTrait;

    protected $table = 'atendentes';

    protected $fillable = ['tenant_id', 'user_id', 'nome', 'ativo'];

    protected $casts = ['ativo' => 'boolean'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (Atendente $atendente) {
            $atendente->uuid = $atendente->uuid ?: (string) Str::uuid();
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'attendant_id');
    }

    public function scopeAtivos($query)
    {
        return $query->where('ativo', true);
    }
}
