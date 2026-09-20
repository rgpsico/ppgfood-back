@include('admin.includes.alerts')

<div class="form-group">
    <label>* Nome:</label>
    <input type="text" name="name" class="form-control" placeholder="Nome:" value="{{ $tenant->name ?? old('name') }}">
</div>
<div class="form-group">
    <label>Logo (upload):</label>
    <input type="file" name="logo" class="form-control">
    <small class="form-text text-muted">Ou informe um link direto no campo abaixo (não preencha os dois).</small>
</div>
<div class="form-group">
    <label>Logo (link direto):</label>
    <input type="url" name="logo_url" class="form-control" placeholder="https://..." value="{{ (isset($tenant) && !str_starts_with($tenant->logo ?? '', 'tenants/')) ? $tenant->logo : old('logo_url') }}">
</div>
<div class="form-group">
    <label>Foto de capa (upload):</label>
    <input type="file" name="cover_image" class="form-control">
    <small class="form-text text-muted">Ou informe um link direto no campo abaixo (não preencha os dois).</small>
</div>
<div class="form-group">
    <label>Foto de capa (link direto):</label>
    <input type="url" name="cover_image_url" class="form-control" placeholder="https://..." value="{{ (isset($tenant) && !str_starts_with($tenant->cover_image ?? '', 'tenants/')) ? $tenant->cover_image : old('cover_image_url') }}">
</div>
<div class="form-group">
    <label>Localização / tagline:</label>
    <input type="text" name="location_label" class="form-control" placeholder="Ex: Rio de Janeiro • Pé na areia" value="{{ $tenant->location_label ?? old('location_label') }}">
</div>
<div class="form-group">
    <label>* E-mail:</label>
    <input type="email" name="email" class="form-control" placeholder="E-mail:" value="{{ $tenant->email ?? old('email') }}">
</div>
<div class="form-group">
    <label>* CNPJ:</label>
    <input type="number" name="cnpj" class="form-control" placeholder="CNPJ:" value="{{ $tenant->cnpj ?? old('cnpj') }}">
</div>
<div class="form-group">
    <label>* Ativo?</label>
    <select name="active" class="form-control">
        <option value="Y" @if(isset($tenant) && $tenant->active == 'Y') selected @endif >SIM</option>
        <option value="N" @if(isset($tenant) && $tenant->active == 'N') selected @endif>Não</option>
    </select>
</div>
<hr>
<h3>Assinatura</h3>
<div class="form-group">
    <label>Data Assinatura (início):</label>
    <input type="date" name="subscription" class="form-control" placeholder="Data Assinatura (início):" value="{{ $tenant->subscription ?? old('subscription') }}">
</div>
<div class="form-group">
    <label>Expira (final):</label>
    <input type="date" name="expires_at" class="form-control" placeholder="Expira:" value="{{ $tenant->expires_at ?? old('expires_at') }}">
</div>
<div class="form-group">
    <label>Identificador:</label>
    <input type="text" name="subscription_id" class="form-control" placeholder="Identificador:" value="{{ $tenant->subscription_id ?? old('subscription_id') }}">
</div>
<div class="form-group">
    <label>* Assinatura Ativa?</label>
    <select name="subscription_active" class="form-control">
        <option value="1" @if(isset($tenant) && $tenant->subscription_active) selected @endif >SIM</option>
        <option value="0" @if(isset($tenant) && !$tenant->subscription_active) selected @endif>Não</option>
    </select>
</div>
<div class="form-group">
    <label>* Assinatura Cancelada?</label>
    <select name="subscription_suspended" class="form-control">
        <option value="1" @if(isset($tenant) && $tenant->subscription_suspended) selected @endif >SIM</option>
        <option value="0" @if(isset($tenant) && !$tenant->subscription_suspended) selected @endif>Não</option>
    </select>
</div>
<div class="form-group">
    <button type="submit" class="btn btn-dark">Enviar</button>
</div>
