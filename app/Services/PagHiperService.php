<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PagHiperService
{
    private $apiKey;
    private $token;
    private $environment;
    private $baseUrl;

    public function __construct()
    {
        $settings = Setting::getPagHiperSettings();
        
        $this->apiKey = $settings['api_key'];
        $this->token = $settings['token'];
        $this->environment = $settings['environment'] ?? 'homologacao';
        
        $this->baseUrl = $this->environment === 'producao' 
            ? 'https://api.paghiper.com'
            : 'https://api.paghiper.com';
    }

    /**
     * Create a PIX transaction
     */
    public function createPixTransaction($orderData)
    {
        $payload = [
            'apiKey' => $this->apiKey,
            'order_id' => $orderData['order_number'],
            'payer_email' => $orderData['customer_email'],
            'payer_name' => $orderData['customer_name'],
            'payer_cpf_cnpj' => '00000000000', // This should come from user data in real implementation
            'days_due_date' => 1, // PIX expires in 1 day
            'type_bank_slip' => 'pix',
            'items' => $this->formatItemsForPagHiper($orderData['items']),
        ];

        return $this->makeRequest('/transaction/create/', $payload);
    }

    /**
     * Create a Boleto transaction
     */
    public function createBoletoTransaction($orderData)
    {
        $payload = [
            'apiKey' => $this->apiKey,
            'order_id' => $orderData['order_number'],
            'payer_email' => $orderData['customer_email'],
            'payer_name' => $orderData['customer_name'],
            'payer_cpf_cnpj' => '00000000000', // This should come from user data in real implementation
            'days_due_date' => 3, // Boleto expires in 3 days
            'type_bank_slip' => 'boletoA4',
            'items' => $this->formatItemsForPagHiper($orderData['items']),
        ];

        return $this->makeRequest('/transaction/create/', $payload);
    }

    /**
     * Get transaction status
     */
    public function getTransactionStatus($transactionId)
    {
        $payload = [
            'apiKey' => $this->apiKey,
            'transaction_id' => $transactionId,
            'token' => $this->token,
        ];

        return $this->makeRequest('/transaction/status/', $payload);
    }

    /**
     * Test API credentials
     */
    public function testCredentials()
    {
        // Create a minimal test transaction to verify credentials
        $testPayload = [
            'apiKey' => $this->apiKey,
            'order_id' => 'TEST-' . time(),
            'payer_email' => 'test@example.com',
            'payer_name' => 'Test User',
            'payer_cpf_cnpj' => '00000000000',
            'days_due_date' => 1,
            'type_bank_slip' => 'pix',
            'items' => [
                [
                    'item_id' => 'test',
                    'description' => 'Test Item',
                    'quantity' => 1,
                    'price_cents' => 100, // R$ 1.00
                ]
            ],
        ];

        $response = $this->makeRequest('/transaction/create/', $testPayload);
        
        // For test purposes, we just check if we get a response without auth errors
        return !isset($response['error']) || !str_contains(strtolower($response['error']), 'auth');
    }

    /**
     * Validate webhook signature
     */
    public function validateWebhookSignature($payload, $signature)
    {
        $expectedSignature = hash_hmac('sha256', json_encode($payload), $this->token);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Format order items for PagHiper API
     */
    private function formatItemsForPagHiper($items)
    {
        $formattedItems = [];
        
        foreach ($items as $item) {
            $formattedItems[] = [
                'item_id' => $item['product_id'],
                'description' => $item['product_name'] ?? 'Gift Card',
                'quantity' => $item['quantity'],
                'price_cents' => intval($item['price'] * 100), // Convert to cents
            ];
        }
        
        return $formattedItems;
    }

    /**
     * Make HTTP request to PagHiper API
     */
    private function makeRequest($endpoint, $payload)
    {
        try {
            $response = Http::timeout(30)
                ->post($this->baseUrl . $endpoint, $payload);

            $data = $response->json();

            if (!$response->successful()) {
                Log::error('PagHiper API Error', [
                    'status' => $response->status(),
                    'response' => $data,
                    'payload' => $payload
                ]);

                return [
                    'error' => $data['error'] ?? 'API request failed',
                    'status' => $response->status()
                ];
            }

            return $data;

        } catch (\Exception $e) {
            Log::error('PagHiper API Exception', [
                'message' => $e->getMessage(),
                'payload' => $payload
            ]);

            return [
                'error' => 'API request failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Check if PagHiper is configured
     */
    public static function isConfigured()
    {
        $settings = Setting::getPagHiperSettings();
        return !empty($settings['api_key']) && !empty($settings['token']);
    }
}