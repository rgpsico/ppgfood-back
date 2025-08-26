<?php

namespace App\Http\Controllers\Admin;

use App\Models\Category;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUpdateProduct;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    private $repository;

    public function __construct(Product $product)
    {
        $this->repository = $product;

        $this->middleware(['can:products']);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $products = $this->repository->latest()->paginate();

        return view('admin.pages.products.index', compact('products'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    { 
        $tenant = auth()->user()->tenant;

        $categories = Category::where('tenant_id', $tenant->id)->get();

        return view('admin.pages.products.create', compact('categories'));
       
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreUpdateProduct  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreUpdateProduct $request)
    {
        $data = $request->all();

        $tenant = auth()->user()->tenant;

        // Salvar imagem se existir
        if ($request->hasFile('image') && $request->image->isValid()) {
            $data['image'] = $request->image->store("tenants/{$tenant->uuid}/products");
        }

        // Criar produto
        $product = $this->repository->create($data);

        // Vincular categorias selecionadas
        if ($request->has('categories')) {
            $product->categories()->sync($request->categories);
        }

        return redirect()->route('products.index');
    }


    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        if (!$product = $this->repository->find($id)) {
            return redirect()->back();
        }

        return view('admin.pages.products.show', compact('product'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
   public function edit($id)
    {
        // Buscar o produto
        if (!$product = $this->repository->find($id)) {
            return redirect()->back();
        }

        // Buscar todas as categorias
       $tenant = auth()->user()->tenant;

        $categories = Category::where('tenant_id', $tenant->id)->get();

        // IDs das categorias que o produto já possui
        $productCategories = $product->categories->pluck('id')->toArray();

        // Enviar dados para a view
        return view('admin.pages.products.edit', compact('product', 'categories', 'productCategories'));
    }


    /**
     * Update register by id
     *
     * @param  \App\Http\Requests\StoreUpdateProduct  $request
     * @param  int  $id
     *
     * @return \Illuminate\Http\Response
     */
    public function update(StoreUpdateProduct $request, $id)
    {
        if (!$product = $this->repository->find($id)) {
            return redirect()->back();
        }

        $data = $request->all();
        $tenant = auth()->user()->tenant;

        // Atualizar imagem se houver
        if ($request->hasFile('image') && $request->image->isValid()) {
            if (Storage::exists($product->image)) {
                Storage::delete($product->image);
            }
            $data['image'] = $request->image->store("tenants/{$tenant->uuid}/products");
        }

        // Atualizar produto
        $product->update($data);

        // Atualizar categorias vinculadas
        if ($request->has('categories')) {
            $product->categories()->sync($request->categories);
        } else {
            $product->categories()->sync([]); // remove todas se nenhuma marcada
        }

        return redirect()->route('products.index')->with('success', 'Produto atualizado com sucesso!');
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (!$product = $this->repository->find($id)) {
            return redirect()->back();
        }

        if (Storage::exists($product->image)) {
            Storage::delete($product->image);
        }

        $product->delete();

        return redirect()->route('products.index');
    }


    /**
     * Search results
     *
     * @param  Request $request
     * @return \Illuminate\Http\Response
     */
    public function search(Request $request)
    {
        $filters = $request->only('filter');

        $products = $this->repository
                            ->where(function($query) use ($request) {
                                if ($request->filter) {
                                    $query->orWhere('description', 'LIKE', "%{$request->filter}%");
                                    $query->orWhere('title', $request->filter);
                                }
                            })
                            ->latest()
                            ->paginate();

        return view('admin.pages.products.index', compact('products', 'filters'));
    }
}
