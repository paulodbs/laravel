<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'paghiper_transaction_id',
        'payment_method',
        'status',
        'amount',
        'qr_code_image',
        'qr_code_text',
        'boleto_url',
        'paghiper_response',
        'paid_at',
        'expires_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paghiper_response' => 'array',
        'paid_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Payment transaction order relationship
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Scope for pending transactions
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for paid transactions
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * Mark transaction as paid
     */
    public function markAsPaid()
    {
        $this->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        // Mark related order as paid
        $this->order->markAsPaid();
    }

    /**
     * Check if transaction is expired
     */
    public function isExpired()
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if transaction is for PIX payment
     */
    public function isPix()
    {
        return $this->payment_method === 'pix';
    }

    /**
     * Check if transaction is for Boleto payment
     */
    public function isBoleto()
    {
        return $this->payment_method === 'boleto';
    }

    /**
     * Get payment instructions for frontend
     */
    public function getPaymentInstructions()
    {
        if ($this->isPix()) {
            return [
                'type' => 'pix',
                'qr_code_image' => $this->qr_code_image,
                'qr_code_text' => $this->qr_code_text,
                'expires_at' => $this->expires_at,
            ];
        }

        if ($this->isBoleto()) {
            return [
                'type' => 'boleto',
                'boleto_url' => $this->boleto_url,
                'expires_at' => $this->expires_at,
            ];
        }

        return null;
    }
}
