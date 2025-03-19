<?php

namespace App\Http\Controllers;

use App\Services\AsaasService;
use App\Services\ClientService;
use App\Models\Client;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ConfiguracoesController extends Controller
{


    public function index()
    {

        return view('admin.pages.configuracoes.index');
    }
}
