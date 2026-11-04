<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;

/**
 * Telas do dono da plataforma: so os e-mails de config/acl.php (admins). Gerente de barraca nunca passa.
 */
class EnsureSuperAdmin
{
    public function handle($request, Closure $next)
    {
        $user = $request->user();

        if (!$user instanceof User || !$user->isAdmin()) {
            return response()->json(['message' => 'Acesso restrito ao administrador da plataforma.'], 403);
        }

        return $next($request);
    }
}
