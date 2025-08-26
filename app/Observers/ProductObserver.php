<?php

namespace App\Observers;

use Illuminate\Support\Str;
use App\Models\Product;

class ProductObserver
{
    /**
     * Handle the product "creating" event.
     *
     * @param  \App\Models\Product  $product
     * @return void
     */
    public function creating(Product $product)
    {
        $product->flag = Str::kebab($product->title);
        $product->uuid = Str::uuid();
    }

    /**
     * Handle the product "updated" event.
     *
     * @param  \App\Models\Product  $product
     * @return void
     */
    public function updating(Product $product)
    {
        $product->flag = Str::kebab($product->title);
    }

  public function saving(Product $product)
{
    // Se o estoque for 0, força desativar
    if ($product->stock <= 0) {
        $product->active = false;
    }
    // Se o estoque > 0, não sobrescreve o valor manual de active
    // Ou seja, o admin pode desativar manualmente mesmo com estoque
}

}
