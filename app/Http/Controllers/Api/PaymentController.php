<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\PagHiperService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PaymentController extends Controller
{
    private $pagHiperService;

    public function __construct()
    {
        $this->pagHiperService = new PagHiperService();
    }

    /**
     * Create payment transaction
     */
    public function create(Request $request)
    {
        $request->validate([
            'order_id' => ['required', 'exists:orders,id'],
            'payment_method' => ['required', 'in:pix,boleto'],
        ]);

        $order = Order::with('orderItems.product')->findOrFail($request->order_id);

        // Check if user can access this order
        if (!$request->user()->isAdmin() && $order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Check if order is in correct status
        if ($order->status !== 'pending') {
            return response()->json([
                'message' => 'Order is not in pending status'
            ], 400);
        }

        // Check if PagHiper is configured
        if (!PagHiperService::isConfigured()) {
            return response()->json([
                'message' => 'Payment system is not configured'
            ], 500);
        }

        try {
            // Prepare order data for PagHiper
            $orderData = [
                'order_number' => $order->order_number,
                'customer_email' => $order->customer_email,
                'customer_name' => $order->customer_name,
                'items' => $order->orderItems->map(function ($item) {
                    return [
                        'product_id' => $item->product_id,
                        'product_name' => $item->product->name,
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                    ];
                })->toArray(),
            ];

            // Create transaction with PagHiper
            if ($request->payment_method === 'pix') {
                $response = $this->pagHiperService->createPixTransaction($orderData);
            } else {
                $response = $this->pagHiperService->createBoletoTransaction($orderData);
            }

            if (isset($response['error'])) {
                return response()->json([
                    'message' => 'Failed to create payment transaction',
                    'error' => $response['error']
                ], 400);
            }

            // Save payment transaction
            $paymentTransaction = PaymentTransaction::create([
                'order_id' => $order->id,
                'paghiper_transaction_id' => $response['transaction_id'],
                'payment_method' => $request->payment_method,
                'status' => 'pending',
                'amount' => $order->total_amount,
                'qr_code_image' => $response['pix_code']['qrcode_image_url'] ?? null,
                'qr_code_text' => $response['pix_code']['qrcode_base64'] ?? null,
                'boleto_url' => $response['bank_slip']['url_slip_pdf'] ?? null,
                'paghiper_response' => $response,
                'expires_at' => $response['due_date'] ? \Carbon\Carbon::parse($response['due_date']) : null,
            ]);

            return response()->json([
                'message' => 'Payment transaction created successfully',
                'transaction' => [
                    'id' => $paymentTransaction->id,
                    'paghiper_transaction_id' => $paymentTransaction->paghiper_transaction_id,
                    'payment_method' => $paymentTransaction->payment_method,
                    'status' => $paymentTransaction->status,
                    'amount' => $paymentTransaction->amount,
                    'expires_at' => $paymentTransaction->expires_at,
                    'payment_instructions' => $paymentTransaction->getPaymentInstructions(),
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Payment creation error', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to create payment transaction'
            ], 500);
        }
    }

    /**
     * Get payment status
     */
    public function status(Request $request, $orderId)
    {
        $order = Order::with('paymentTransactions')->findOrFail($orderId);

        // Check if user can access this order
        if (!$request->user()->isAdmin() && $order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $latestTransaction = $order->latestPaymentTransaction;

        if (!$latestTransaction) {
            return response()->json([
                'message' => 'No payment transaction found'
            ], 404);
        }

        // Optionally, check with PagHiper for latest status
        try {
            $pagHiperResponse = $this->pagHiperService->getTransactionStatus(
                $latestTransaction->paghiper_transaction_id
            );

            if (!isset($pagHiperResponse['error']) && isset($pagHiperResponse['status'])) {
                // Update local status if different
                $pagHiperStatus = $this->mapPagHiperStatus($pagHiperResponse['status']);
                if ($pagHiperStatus !== $latestTransaction->status) {
                    $latestTransaction->update(['status' => $pagHiperStatus]);
                    
                    if ($pagHiperStatus === 'paid') {
                        $latestTransaction->markAsPaid();
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to check PagHiper status', [
                'transaction_id' => $latestTransaction->paghiper_transaction_id,
                'error' => $e->getMessage()
            ]);
        }

        return response()->json([
            'order' => [
                'id' => $order->id,
                'status' => $order->status,
                'payment_transaction' => [
                    'id' => $latestTransaction->id,
                    'status' => $latestTransaction->status,
                    'payment_method' => $latestTransaction->payment_method,
                    'amount' => $latestTransaction->amount,
                    'expires_at' => $latestTransaction->expires_at,
                    'payment_instructions' => $latestTransaction->getPaymentInstructions(),
                ]
            ]
        ]);
    }

    /**
     * Handle PagHiper webhook
     */
    public function webhook(Request $request)
    {
        Log::info('PagHiper webhook received', ['payload' => $request->all()]);

        try {
            // Validate webhook signature if provided
            $signature = $request->header('X-Paghiper-Signature');
            if ($signature && !$this->pagHiperService->validateWebhookSignature($request->all(), $signature)) {
                Log::warning('Invalid webhook signature');
                return response()->json(['message' => 'Invalid signature'], 403);
            }

            $transactionId = $request->get('transaction_id');
            if (!$transactionId) {
                return response()->json(['message' => 'Transaction ID not provided'], 400);
            }

            $transaction = PaymentTransaction::where('paghiper_transaction_id', $transactionId)->first();
            if (!$transaction) {
                Log::warning('Transaction not found', ['transaction_id' => $transactionId]);
                return response()->json(['message' => 'Transaction not found'], 404);
            }

            $status = $request->get('status');
            $newStatus = $this->mapPagHiperStatus($status);

            if ($newStatus && $newStatus !== $transaction->status) {
                $transaction->update(['status' => $newStatus]);

                if ($newStatus === 'paid') {
                    $transaction->markAsPaid();
                    
                    // Assign gift codes to order
                    $this->assignGiftCodesToOrder($transaction->order);
                    
                    // Send confirmation email (implement this)
                    $this->sendPaymentConfirmationEmail($transaction->order);
                }
            }

            return response()->json(['message' => 'Webhook processed successfully']);

        } catch (\Exception $e) {
            Log::error('Webhook processing error', [
                'error' => $e->getMessage(),
                'payload' => $request->all()
            ]);

            return response()->json(['message' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Map PagHiper status to our internal status
     */
    private function mapPagHiperStatus($pagHiperStatus)
    {
        return match($pagHiperStatus) {
            'pending' => 'pending',
            'paid' => 'paid',
            'cancelled' => 'cancelled',
            'refunded' => 'refunded',
            default => null,
        };
    }

    /**
     * Assign gift codes to order when payment is confirmed
     */
    private function assignGiftCodesToOrder(Order $order)
    {
        foreach ($order->orderItems as $orderItem) {
            if (!$orderItem->hasAllCodesAssigned()) {
                $availableCodes = $orderItem->product->getAvailableCodesForValue(
                    $orderItem->gift_card_value,
                    $orderItem->quantity
                );

                if ($availableCodes->count() >= $orderItem->quantity) {
                    $orderItem->assignGiftCodes($availableCodes);
                }
            }
        }
    }

    /**
     * Send payment confirmation email
     */
    private function sendPaymentConfirmationEmail(Order $order)
    {
        // This would typically use Laravel's Mail system
        // For now, we'll just log it
        Log::info('Payment confirmation email should be sent', [
            'order_id' => $order->id,
            'customer_email' => $order->customer_email
        ]);
    }
}
