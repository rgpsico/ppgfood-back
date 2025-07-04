<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\TenantFormRequest;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ProductResourceFront;
use App\Models\Tenant;
use App\Services\ProductService;
use Illuminate\Http\Request;

class ProductApiController extends Controller
{
    protected $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    public function productsByTenant(TenantFormRequest $request)
    {
        $products = $this->productService->getProductsByTenantUuid(
            $request->token_company,
            $request->get('categories', [])
        );

        return ProductResourceFront::collection($products);
    }


    public function show(TenantFormRequest $request, $identify)
    {
        if (!$product = $this->productService->getProductByUuid($identify)) {
            return response()->json(['message' => 'Product Not Found'], 404);
        }

        return new ProductResource($product);
    }

    public function getUuidByCompanyUrl($url)
    {
        $tenant = Tenant::where('url', $url)->first();

        if (!$tenant) {
            return response()->json(['message' => 'Empresa não encontrada'], 404);
        }

        return response()->json(['uuid' => $tenant->uuid]);
    }
}
