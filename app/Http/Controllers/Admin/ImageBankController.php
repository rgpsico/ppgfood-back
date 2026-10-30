<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ImageBank;
use Illuminate\Http\Request;
use Throwable;

class ImageBankController extends Controller
{
    public function __construct(private ImageBank $bank)
    {
        $this->middleware(['can:products']);
    }

    public function index(Request $request)
    {
        if (! $this->bank->enabled()) {
            return response()->json(['message' => 'Banco de imagens não configurado.'], 503);
        }

        try {
            return response()->json(array_merge(
                ['collections' => collect($this->bank->collections())->map(fn ($c) => ['id' => $c['id'], 'name' => $c['name']])->values()],
                $this->bank->search($request->query('q'), $request->query('collection'), (int) $request->query('page', 1))
            ));
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Banco de imagens indisponível no momento.'], 502);
        }
    }
}
