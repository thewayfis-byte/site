<?php

class YuKassa {
    private $shop_id;
    private $secret_key;
    private $api_url = 'https://api.yookassa.ru/v3/';
    
    public function __construct($shop_id, $secret_key) {
        $this->shop_id = $shop_id;
        $this->secret_key = $secret_key;
    }
    
    public function createPayment($amount, $currency, $description, $return_url, $metadata = []) {
        $data = [
            'amount' => [
                'value' => number_format($amount, 2, '.', ''),
                'currency' => $currency
            ],
            'confirmation' => [
                'type' => 'redirect',
                'return_url' => $return_url
            ],
            'capture' => true,
            'description' => $description,
            'metadata' => $metadata
        ];
        
        return $this->makeRequest('payments', 'POST', $data);
    }
    
    public function getPayment($payment_id) {
        return $this->makeRequest('payments/' . $payment_id, 'GET');
    }
    
    private function makeRequest($endpoint, $method = 'GET', $data = null) {
        $url = $this->api_url . $endpoint;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $this->shop_id . ':' . $this->secret_key);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Idempotence-Key: ' . uniqid()
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code >= 200 && $http_code < 300) {
            return json_decode($response, true);
        }
        
        return false;
    }
}

?>