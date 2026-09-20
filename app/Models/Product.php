<?php

namespace App\Models;

use App\Tenant\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use TenantTrait;

   protected $fillable = ['title', 'flag', 'price', 'description', 'image', 'stock', 'active'];


    public function scopeAvailable($query)
    {
        return $query->where('stock', '>', 0)->where('active', true);
    }

    /**
     * Resolve a URL para a imagem do produto, aceitando tanto um arquivo
     * salvo em storage/ quanto um link externo direto.
     */
    public function getImageUrlAttribute(): string
    {
        if (! $this->image) {
            return '';
        }

        if (preg_match('/^https?:\/\//i', $this->image)) {
            return $this->image;
        }

        return url("storage/{$this->image}");
    }


    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }


    /**
     * Cateroies not linked with this product
     */
    public function categoriesAvailable($filter = null)
    {
        $categories = Category::whereNotIn('categories.id', function($query) {
            $query->select('category_product.category_id');
            $query->from('category_product');
            $query->whereRaw("category_product.product_id={$this->id}");
        })
        ->where(function ($queryFilter) use ($filter) {
            if ($filter)
                $queryFilter->where('categories.name', 'LIKE', "%{$filter}%");
        })
        ->paginate();

        return $categories;
    }
}
