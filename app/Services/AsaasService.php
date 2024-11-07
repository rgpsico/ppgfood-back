<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AsaasService
{
    protected $apiKey;
    protected $baseUrl;

    public function __construct()
    {
        $this->apiKey = env('ASAAS_ACCESS_TOKEN');
        $this->baseUrl = config('asaas.base_sandbox_url');
    }

    protected function request($method, $endpoint, $data = [])
    {
        $response = Http::withHeaders([
            'access_token' => $this->apiKey,
            'accept' => 'application/json',
            'content-type' => 'application/json',
        ])->$method("{$this->baseUrl}/$endpoint", $data);

        return $response->json();
    }

    public function createCustomer($data)
    {
        return $this->request('post', 'customers', $data);
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
