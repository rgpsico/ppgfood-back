<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BatePapoController;
use App\Http\Controllers\Api\EntregaController;
use App\Http\Controllers\Api\NotificacaoController;
use App\Http\Controllers\Api\PedidoController;
use App\Http\Controllers\Api\UsuarioController;

use App\Http\Controllers\AsaasController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', 'Api\Auth\RegisterController@store');
Route::post('/auth/token', 'Api\Auth\AuthClientController@auth');

Route::group([
    'middleware' => ['auth:sanctum']
], function () {
    Route::get('/auth/me', 'Api\Auth\AuthClientController@me');
    Route::post('/auth/logout', 'Api\Auth\AuthClientController@logout');

    Route::post('/auth/v1/orders/{identifyOrder}/evaluations', 'Api\EvaluationApiController@store');

    Route::get('/auth/v1/my-orders', 'Api\OrderApiController@myOrders');
    Route::post('/auth/v1/orders', 'Api\OrderApiController@store');
});

Route::group([
    'prefix' => 'v1',
    'namespace' => 'Api'
], function () {
    Route::get('/tenants/{uuid}', 'TenantApiController@show');
    Route::get('/tenants', 'TenantApiController@index');

    Route::get('/categories/{identify}', 'CategoryApiController@show');
    Route::get('/categories', 'CategoryApiController@categoriesByTenant');

    Route::get('/tables/{identify}', 'TableApiController@show');
    Route::get('/tables', 'TableApiController@tablesByTenant');

    Route::get('/products/{identify}', 'ProductApiController@show');
    // Route::get('/products/{identify}/teste', 'ProductApiController@productsByTenantId');
    Route::get('/products', 'ProductApiController@productsByTenant');

    Route::post('/orders', 'OrderApiController@store');
    Route::get('/orders/{identify}', 'OrderApiController@show');
});

/**
 * Test API
 */
Route::get('/teste', function () {
    return response()->json(['message' => 'ok gil']);
});


Route::get('/teste', function () {
    return response()->json(['message' => 'ok gil']);
});

Route::put('/asaas/update-asaas-key', [AsaasController::class, 'updateAsaasKeyByEmail']);
Route::post('/asaas/customer', [AsaasController::class, 'criarCliente']);

Route::post('/asaas/pix', [AsaasController::class, 'criarPixQrcodeEstatico']);

Route::get('/asaas/recuperar-dados-comerciais', [AsaasController::class, 'recuperarDadosComerciais']);

Route::post('/webhook/asaas', 'AsaasController@asaasWebhook');
Route::post('/asaas/payment', [AsaasController::class, 'criarPagamento']);
Route::post('/asaas/paymentcc', [AsaasController::class, 'criarPagamentoComCartao']);

Route::get('/asaas/paymentlist', [AsaasController::class, 'listarPagamentos']);

Route::get('/app', function () {
    exec("C:\Program Files\Google\Chrome\Application\chrome.exe");
});


Route::get('indicadores', 'Api\EntregaController@dashboard')->middleware('auth:api');;



Route::get('usuarios', 'Api\UsuarioController@index'); // Listar todos
Route::post('usuarios', 'Api\UsuarioController@store'); // Criar novo
Route::get('usuarios/{id}', 'Api\UsuarioController@show'); // Mostrar um
Route::put('usuarios/{id}', 'Api\UsuarioController@update'); // Atualizar
Route::delete('usuarios/{id}', 'Api\UsuarioController@destroy'); // Deletar

Route::get('entregas', 'Api\EntregaController@index');
Route::post('entregas', 'Api\EntregaController@store');
Route::get('entregas/{id}', 'Api\EntregaController@show');
Route::put('entregas/{id}', 'Api\EntregaController@update');
Route::delete('entregas/{id}', 'Api\EntregaController@destroy');

Route::get('pedidos', 'Api\PedidoController@index');
Route::post('pedidos', 'Api\PedidoController@store');
Route::get('pedidos/{id}', 'Api\PedidoController@show');
Route::put('pedidos/{id}', 'Api\PedidoController@update');
Route::delete('pedidos/{id}', 'Api\PedidoController@destroy');

Route::get('notificacoes', 'Api\NotificacaoController@index');
Route::post('notificacoes', 'Api\NotificacaoController@store');
Route::get('notificacoes/{id}', 'Api\NotificacaoController@show');
Route::put('notificacoes/{id}', 'Api\NotificacaoController@update');
Route::delete('notificacoes/{id}', 'Api\NotificacaoController@destroy');


Route::apiResource('usuarios', UsuarioController::class);
Route::post('register', 'Api\AuthController@register');
Route::post('login', 'Api\AuthController@login');
Route::post('logout', 'Api\AuthController@logout')->middleware('auth:sanctum');
