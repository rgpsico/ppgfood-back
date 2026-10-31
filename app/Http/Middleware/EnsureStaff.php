<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;

/**
 * Rotas do painel (/staff/*) so para usuarios da barraca. O token de um cliente (Client) tambem
 * passa no auth:sanctum, mas nao pode abrir o painel nem ver pedidos dos outros.
 */
class EnsureStaff
{
    public function handle($request, Closure $next)
    {
        if (!$request->user() instanceof User) {
            return response()->json(['message' => 'Acesso restrito à equipe.'], 403);
        }

        return $next($request);
    }
}
