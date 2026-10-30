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
    <input type="file" name="image" class="form-control" id="product-image-file">

    @if (config('services.mediahub.url') && config('services.mediahub.key'))
        <div class="mt-2 d-flex align-items-center" style="gap:12px">
            <button type="button" class="btn btn-outline-secondary" id="bank-open">
                <i class="fas fa-images"></i> Escolher do banco de imagens
            </button>
            <span class="text-muted small">ou envie um arquivo acima</span>
        </div>
        <input type="hidden" name="bank_media_id" id="bank-media-id" value="">
        <div id="bank-picked" class="mt-2" style="display:none">
            <img id="bank-picked-img" alt="" style="height:90px;border-radius:6px;border:1px solid #ddd">
            <span id="bank-picked-name" class="ml-2"></span>
            <button type="button" class="btn btn-link btn-sm text-danger" id="bank-clear">remover</button>
        </div>
    @endif

    @if (isset($product) && $product->image)
        <div class="mt-2" id="product-current-image">
            <small class="text-muted d-block">Imagem atual:</small>
            <img src="{{ $product->image_url }}" alt="" style="height:90px;border-radius:6px;border:1px solid #ddd">
        </div>
    @endif
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


@if (config('services.mediahub.url') && config('services.mediahub.key'))
<div id="bank-modal" style="display:none;position:fixed;inset:0;z-index:2000;background:rgba(0,0,0,.55);padding:16px;overflow:auto" role="dialog" aria-modal="true" aria-label="Banco de imagens">
    <div style="background:#fff;max-width:960px;margin:0 auto;border-radius:10px;padding:16px">
        <div class="d-flex align-items-center mb-3" style="gap:8px">
            <h5 class="mb-0 mr-auto">Banco de imagens</h5>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="bank-close">Fechar</button>
        </div>
        <div class="d-flex mb-2" style="gap:8px">
            <input type="search" id="bank-q" class="form-control" placeholder="Buscar: hambúrguer, caipirinha, pizza...">
        </div>
        <div id="bank-chips" class="mb-1" style="display:flex;flex-wrap:wrap;gap:6px"></div>
        <div id="bank-subchips" class="mb-2" style="display:flex;flex-wrap:wrap;gap:6px"></div>
        <div id="bank-msg" class="text-muted small mb-2"></div>
        <div id="bank-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px"></div>
        <div class="text-center mt-3"><button type="button" class="btn btn-outline-secondary btn-sm" id="bank-more" style="display:none">Carregar mais</button></div>
    </div>
</div>

<script>
(function () {
    var URL_ = @json(route('image-bank.index'));
    var $ = function (id) { return document.getElementById(id); };
    var modal = $('bank-modal'), grid = $('bank-grid'), msg = $('bank-msg'), more = $('bank-more'), q = $('bank-q');
    var page = 1, last = 1, timer = null, loadedCols = false;
    var cols = [], root = '', sub = '';   // pasta raiz e subpasta escolhidas (ids)

    function load(reset) {
        if (reset) { page = 1; grid.innerHTML = ''; }
        msg.textContent = 'Carregando...';
        var qs = new URLSearchParams({ q: q.value.trim(), collection: sub || root, page: page });
        fetch(URL_ + '?' + qs, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.message || 'Erro'); return j; }); })
            .then(function (j) {
                if (!loadedCols) { cols = j.collections || []; loadedCols = true; drawChips(); }
                last = j.last_page || 1;
                j.data.forEach(function (m) {
                    var b = document.createElement('button');
                    b.type = 'button'; b.title = m.name;
                    b.style.cssText = 'border:1px solid #ddd;border-radius:8px;padding:0;background:#fff;overflow:hidden;text-align:left;cursor:pointer';
                    var img = document.createElement('img'); img.src = m.thumb; img.alt = m.name; img.loading = 'lazy';
                    img.style.cssText = 'width:100%;height:110px;object-fit:cover;display:block';
                    var cap = document.createElement('div'); cap.textContent = m.name; cap.style.cssText = 'padding:6px 8px;font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis';
                    b.appendChild(img); b.appendChild(cap);
                    b.addEventListener('click', function () { pick(m); });
                    grid.appendChild(b);
                });
                msg.textContent = grid.children.length ? '' : 'Nenhuma imagem encontrada.';
                more.style.display = page < last ? '' : 'none';
            })
            .catch(function (e) { msg.textContent = e.message || 'Não foi possível carregar o banco de imagens.'; });
    }

    // Total de fotos de uma pasta contando as subpastas.
    function total(c) { return (c.media_count || 0) + cols.filter(function (x) { return x.parent_id === c.id; }).reduce(function (n, x) { return n + total(x); }, 0); }

    function chip(label, active, onClick) {
        var b = document.createElement('button'); b.type = 'button'; b.textContent = label;
        b.className = 'btn btn-sm ' + (active ? 'btn-dark' : 'btn-outline-secondary'); b.style.borderRadius = '999px';
        b.addEventListener('click', onClick); return b;
    }
    function drawChips() {
        var top = $('bank-chips'), low = $('bank-subchips'); top.innerHTML = ''; low.innerHTML = '';
        top.appendChild(chip('Todas', !root, function () { root = ''; sub = ''; drawChips(); load(true); }));
        cols.filter(function (c) { return !c.parent_id && total(c) > 0; }).forEach(function (c) {
            top.appendChild(chip(c.name + ' (' + total(c) + ')', root === c.id, function () { root = c.id; sub = ''; drawChips(); load(true); }));
        });
        if (!root) return;
        var kids = cols.filter(function (c) { return c.parent_id === root && total(c) > 0; });
        if (!kids.length) return;
        low.appendChild(chip('Tudo', !sub, function () { sub = ''; drawChips(); load(true); }));
        kids.forEach(function (c) { low.appendChild(chip(c.name + ' (' + total(c) + ')', sub === c.id, function () { sub = c.id; drawChips(); load(true); })); });
    }

    function pick(m) {
        $('bank-media-id').value = m.id;
        $('bank-picked-img').src = m.thumb; $('bank-picked-name').textContent = m.name;
        $('bank-picked').style.display = '';
        var f = $('product-image-file'); if (f) f.value = '';
        modal.style.display = 'none';
    }
    function clearPick() { $('bank-media-id').value = ''; $('bank-picked').style.display = 'none'; }

    $('bank-open').addEventListener('click', function () { modal.style.display = ''; if (!grid.children.length) load(true); q.focus(); });
    $('bank-close').addEventListener('click', function () { modal.style.display = 'none'; });
    modal.addEventListener('click', function (e) { if (e.target === modal) modal.style.display = 'none'; });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') modal.style.display = 'none'; });
    $('bank-clear').addEventListener('click', clearPick);
    q.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(function () { load(true); }, 350); });
    more.addEventListener('click', function () { page++; load(false); });
    // Escolher um arquivo manual descarta a escolha do banco.
    var f = $('product-image-file'); if (f) f.addEventListener('change', function () { if (f.value) clearPick(); });
})();
</script>
@endif
