<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GiftCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'value',
        'code',
        'status',
        'order_id',
        'sold_at',
        'used_at',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'sold_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    /**
     * Gift code product relationship
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Gift code order relationship
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Scope for available codes
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    /**
     * Scope for sold codes
     */
    public function scopeSold($query)
    {
        return $query->where('status', 'sold');
    }

    /**
     * Scope for used codes
     */
    public function scopeUsed($query)
    {
        return $query->where('status', 'used');
    }

    /**
     * Mark code as sold
     */
    public function markAsSold($orderId)
    {
        $this->update([
            'status' => 'sold',
            'order_id' => $orderId,
            'sold_at' => now(),
        ]);
    }

    /**
     * Mark code as used
     */
    public function markAsUsed()
    {
        $this->update([
            'status' => 'used',
            'used_at' => now(),
        ]);
    }

    /**
     * Get masked code for list display
     */
    public function getMaskedCodeAttribute()
    {
        $code = $this->code;
        if (strlen($code) <= 4) {
            return $code;
        }
        return substr($code, 0, 2) . str_repeat('*', strlen($code) - 4) . substr($code, -2);
    }
}
