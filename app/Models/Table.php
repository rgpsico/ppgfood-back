<?php

namespace App\Models;

use App\Tenant\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

class Table extends Model
{
    use TenantTrait;

    protected $fillable = ['identify', 'description', 'position_x', 'position_y', 'beach_row', 'beach_col'];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
