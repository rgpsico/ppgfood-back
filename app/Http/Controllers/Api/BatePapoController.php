<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notificacao;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Request;

class BatePapoController extends Controller
{



    public function enviarMensagem(Request $request)
    {

        $url = 'http://www.comunidadeppg.com.br:3000/enviarmensagem';

        $client = new Client();

        try {
            $response = $client->post($url, [
                'json' => $request->all() // Envia os dados no formato JSON
            ]);

            // Retorna a resposta como um objeto JSON
            return json_decode($response->getBody()->getContents());
        } catch (RequestException $e) {
            // Em caso de erro, retorna a mensagem
            return [
                'error' => true,
                'message' => $e->getMessage()
            ];
        }
    }


    public function enviarnotificacao(Request $request)
    {

        $url = 'http://www.comunidadeppg.com.br:3000/enviarpedidoparaentregadores';

        $client = new Client();

        try {
            $response = $client->post($url, [
                'json' => $request->all() // Envia os dados no formato JSON
            ]);

            // Retorna a resposta como um objeto JSON
            return json_decode($response->getBody()->getContents());
        } catch (RequestException $e) {
            // Em caso de erro, retorna a mensagem
            return [
                'error' => true,
                'message' => $e->getMessage()
            ];
        }
    }
}
