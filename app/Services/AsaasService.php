<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AsaasService
{
    protected $access_token;
    protected $baseUrl;

    public function __construct()
    {
        $this->access_token = env('ASAAS_ACCESS_TOKEN');
        $this->baseUrl = config('asaas.base_sandbox_url');
    }

    protected function request($method, $endpoint, $data = [])
    {
        $response = Http::withHeaders([
            'access_token' => $this->access_token,
            'accept' => 'application/json',
            'content-type' => 'application/json',
        ])->$method("{$this->baseUrl}/$endpoint", $data);

        return $response->json();
    }

    public function createCustomer($request)
    {
        $clienteData = [
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'address' => $request->input('address'),
            'province' => $request->input('province'),
            'postalCode' => $request->input('postalCode'),
            'cpf' => $request->input('cpfcnpj'),
        ];

        // Realizando a requisição POST
        return  $response = Http::withHeaders([
            'accept' => 'application/json',
            'access_token' => $this->access_token,
            'content-type' => 'application/json'
        ])->post('https://sandbox.asaas.com/api/v3/customers', $clienteData);
    }

    public function getCommercialInfo()
    {
        return $this->request('get', 'myAccount/commercialInfo');
    }

    public function createPayment($data)
    {
        return $this->request('post', 'payments', $data);
    }

    public function listPayments($customerId)
    {
        return $this->request('get', 'payments', ['customer' => $customerId]);
    }

    public function getPaymentStatus($paymentId)
    {
        return $this->request('get', "payments/$paymentId");
    }
}
