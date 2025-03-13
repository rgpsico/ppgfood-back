<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BatePapoController;
use App\Http\Controllers\Api\EntregaController;
use App\Http\Controllers\Api\NotificacaoController;
use App\Http\Controllers\Api\PedidoController;
use App\Http\Controllers\Api\UsuarioController;

use App\Http\Controllers\AsaasController;
use Illuminate\Http\Client\Request;
use Illuminate\Routing\Route;

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



Route::get('/entrega', function () {
    return response()->json(['message' => 'ok entrega']);
});

Route::apiResource('pedidos', PedidoController::class);


Route::apiResource('usuarios', UsuarioController::class);

Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::apiResource('notificacoes', NotificacaoController::class);



Route::post('enviarnotificacao', [NotificacaoController::class, 'enviarnotificacao']);


Route::post('batepapo/enviarmensagem', [BatePapoController::class, 'enviarMensagem']);




Route::apiResource('entregas', EntregaController::class)->middleware('auth:sanctum');;;


Route::get('indicadores/dashboard', [EntregaController::class, 'dashboard'])->middleware('auth:sanctum');;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
