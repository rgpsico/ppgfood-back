<?php

namespace App\Repositories;

use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Support\Facades\DB;

class ProductRepository implements ProductRepositoryInterface
{
    protected $table;

    public function __construct()
    {
        $this->table = 'products';
    }

    public function getproductsByTenantId(int $idTenant, array $categories)
    {
           
        return DB::table($this->table)
       ->where('products.tenant_id', $idTenant)
        ->where(function ($query) use ($categories) {
            if ($categories != [])
                $query->whereIn('categories.uuid', $categories);
        })
        ->select('products.*')
        ->get();
    }

    public function getProductByUuid(string $uuid)
    {
         DB::table($this->table)
                    ->where('uuid', $uuid)
                    ->first();
    }

    public function getProductByid(string $uuid)
    {
        return DB::table($this->table)
                    ->where('uuid', $uuid)
                    ->get();
    }
}
