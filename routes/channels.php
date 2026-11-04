<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Pedidos da barraca inteira: so o painel do gerente/dono da propria barraca. Atendente
// (usuario com cadastro em atendentes) fica de fora: ele so recebe o canal privado dele.
Broadcast::channel('order-created.{tenantId}', function ($user, $tenantId) {
    return $user instanceof \App\Models\User
        && $user->tenant_id !== null
        && (int) $user->tenant_id === (int) $tenantId
        && !\App\Models\Atendente::withoutGlobalScopes()->where('user_id', $user->id)->exists();
});


Broadcast::channel('teste', function ($user, $tenantId) {
    return $user->tenant_id == $tenantId;
});

// Pedidos do proprio atendente: so o usuario dono do canal, e so quem esta cadastrado como atendente
Broadcast::channel('atendente.{userId}', function ($user, $userId) {
    return $user instanceof \App\Models\User
        && (int) $user->id === (int) $userId
        && \App\Models\Atendente::where('user_id', $user->id)->exists();
});
