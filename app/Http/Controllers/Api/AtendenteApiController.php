<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\TenantFormRequest;
use App\Services\AtendimentoService;
use App\Services\TenantService;

class AtendenteApiController extends Controller
{
    public function index(TenantFormRequest $request, TenantService $tenantService, AtendimentoService $atendimento)
    {
        $tenant = $tenantService->getTenantByUuid($request->token_company);

        return response()->json([
            'data' => $atendimento->atendentesAtivos($tenant->id)
                ->map(fn ($a) => ['identify' => $a->uuid, 'name' => $a->nome])
                ->values(),
        ]);
    }
}
