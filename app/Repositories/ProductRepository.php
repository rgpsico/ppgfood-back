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



    public function getProductsByTenantId(int $idTenant, array $categories)
    {
        return DB::table('products')
            ->join('category_product', 'products.id', '=', 'category_product.product_id')
            ->join('categories', 'categories.id', '=', 'category_product.category_id')
            ->where('products.tenant_id', $idTenant)
            ->where('products.active', true)      // Só produtos ativos
            ->where('products.stock', '>', 0)     // Só produtos com estoque
            ->when(!empty($categories), function ($query) use ($categories) {
                $query->whereIn('categories.uuid', $categories);
            })
            ->select('products.*')
            ->distinct()
            ->get();
    }




    public function getProductByUuid(string $uuid)
    {
        return DB::table($this->table)
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
