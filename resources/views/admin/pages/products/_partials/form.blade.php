@include('admin.includes.alerts')

<div class="form-group">
    <label>* Título:</label>
    <input type="text" name="title" class="form-control" placeholder="Título:" value="{{ $product->title ?? old('title') }}">
</div>

<div class="form-group">
    <label>* Preço:</label>
    <input type="text" name="price" class="form-control" placeholder="Preço:" value="{{ $product->price ?? old('price') }}">
</div>

<div class="form-group">
    <label>* Imagem:</label>
    <input type="file" name="image" class="form-control">
</div>

<div class="form-group">
    <label>* Descrição:</label>
    <textarea name="description" cols="30" rows="5" class="form-control">{{ $product->description ?? old('description') }}</textarea>
</div>

{{-- Quantidade / Estoque --}}
<div class="form-group">
    <label>* Quantidade em estoque:</label>
    <input type="number" name="stock" class="form-control" placeholder="Quantidade disponível" value="{{ $product->stock ?? old('stock') }}" min="0">
</div>


{{-- Ativar / Desativar Produto --}}
<div class="form-group">
    <label>
        {{-- Campo hidden para enviar 0 se o checkbox não estiver marcado --}}
        <input type="hidden" name="active" value="0">
        <input type="checkbox" name="active" value="1"
            {{ isset($product) && $product->active ? 'checked' : '' }}>
        Produto ativo
    </label>
</div>


{{-- Categorias --}}


<div class="form-group">
    <label>Categorias:</label>
    <div class="row">
        @foreach ($categories as $category)
            <div class="col-md-3">
                <label>
                    <input type="checkbox" name="categories[]" value="{{ $category->id }}"
                        {{ isset($productCategories) && in_array($category->id, $productCategories) ? 'checked' : '' }}>
                    {{ $category->name }}
                </label>
            </div>
        @endforeach
    </div>
</div>

<div class="form-group">
    <button type="submit" class="btn btn-dark">Enviar</button>
</div>
