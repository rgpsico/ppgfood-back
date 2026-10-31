@include('admin.includes.alerts')

<div class="form-group">
    <label>Nome do atendente:</label>
    <input type="text" name="nome" class="form-control" placeholder="Ex: João" value="{{ old('nome', $atendente->nome ?? '') }}" required>
</div>

<div class="form-group">
    <input type="hidden" name="ativo" value="0">
    <label><input type="checkbox" name="ativo" value="1" {{ old('ativo', $atendente->ativo ?? true) ? 'checked' : '' }}> Ativo (aparece para o cliente escolher)</label>
</div>

<hr>
<h5>Login do painel (opcional)</h5>
<p class="text-muted">Com login, o atendente entra no painel da barraca e vê só os pedidos dele.
    @isset($atendente) @if($atendente->user) Deixe a senha em branco para manter a atual. @endif @endisset</p>

<div class="form-group">
    <label>E-mail:</label>
    <input type="email" name="email" class="form-control" autocomplete="off" value="{{ old('email', isset($atendente) && $atendente->user ? $atendente->user->email : '') }}">
</div>
<div class="form-group">
    <label>Senha:</label>
    <input type="password" name="password" class="form-control" autocomplete="new-password" minlength="6">
</div>

<div class="form-group">
    <button type="submit" class="btn btn-dark">Salvar</button>
    <a href="{{ route('atendentes.index') }}" class="btn btn-default">Voltar</a>
</div>
