<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Entrega;
use Carbon\Carbon;
use Illuminate\Http\Request;

class EntregaController extends Controller
{
    public function index()
    {
        return view('admin.pages.entregadores.index');
    }
}
