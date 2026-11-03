<?php

namespace App\Models;

use App\Tenant\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

class Table extends Model
{
    use TenantTrait;

    const TIPO_GUARDA_SOL = 'guarda_sol';
    const TIPO_CADEIRA = 'cadeira';
    const TIPOS = [self::TIPO_GUARDA_SOL, self::TIPO_CADEIRA];

    protected $fillable = ['identify', 'tipo', 'description', 'position_x', 'position_y', 'beach_row', 'beach_col'];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
