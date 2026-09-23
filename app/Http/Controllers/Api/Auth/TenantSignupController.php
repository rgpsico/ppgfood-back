<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\Table;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenantSignupController extends Controller
{
    const MAX_GUARDA_SOIS = 20;

    /**
     * Cadastro rapido de um novo barraqueiro: cria a barraca (tenant), o
     * usuario admin dela e os guarda-sois de uma vez, ja devolvendo um
     * token de login pronto pra cair direto no painel de pedidos.
     */
    public function store(Request $request)
    {
        $request->validate([
            'tenant_name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email', 'unique:tenants,email'],
            'password' => ['required', 'string', 'min:6'],
            'umbrella_count' => ['required', 'integer', 'min:1', 'max:' . self::MAX_GUARDA_SOIS],
        ]);

        if (Tenant::where('name', $request->tenant_name)->exists()
            || Tenant::where('url', Str::kebab($request->tenant_name))->exists()
        ) {
            return response()->json([
                'message' => 'Já existe uma barraca com esse nome. Tente outro.',
            ], 422);
        }

        $result = DB::transaction(function () use ($request) {
            $tenant = Tenant::create([
                'name' => $request->tenant_name,
                'email' => $request->email,
                'active' => 'Y',
            ]);

            $user = new User();
            $user->name = $request->tenant_name;
            $user->email = $request->email;
            $user->password = Hash::make($request->password);
            $user->tenant_id = $tenant->id;
            $user->save();

            for ($i = 1; $i <= (int) $request->umbrella_count; $i++) {
                $table = new Table();
                $table->tenant_id = $tenant->id;
                $table->identify = "Guarda-sol {$i}";
                $table->save();
            }

            $token = $user->createToken('signup')->plainTextToken;

            return [$tenant, $user, $token];
        });

        [$tenant, $user, $token] = $result;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'tenant_id' => $tenant->id,
                'tenant_uuid' => $tenant->uuid,
                'tenant_name' => $tenant->name,
            ],
        ], 201);
    }
}
