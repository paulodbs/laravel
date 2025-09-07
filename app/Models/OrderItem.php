<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'gift_card_value',
        'price',
        'quantity',
        'total_price',
        'gift_codes',
    ];

    protected $casts = [
        'gift_card_value' => 'decimal:2',
        'price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'quantity' => 'integer',
        'gift_codes' => 'array',
    ];

    /**
     * Order item order relationship
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Order item product relationship
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Assign gift codes to this order item
     */
    public function assignGiftCodes($codes)
    {
        $codeArray = collect($codes)->map(function ($code) {
            return [
                'id' => $code->id,
                'code' => $code->code,
                'value' => $code->value,
            ];
        })->toArray();

        $this->update(['gift_codes' => $codeArray]);

        // Mark codes as sold
        collect($codes)->each(function ($code) {
            $code->markAsSold($this->order_id);
        });
    }

    /**
     * Check if all codes are assigned
     */
    public function hasAllCodesAssigned()
    {
        return $this->gift_codes && count($this->gift_codes) >= $this->quantity;
    }

    /**
     * Get masked gift codes for display
     */
    public function getMaskedGiftCodesAttribute()
    {
        if (!$this->gift_codes) {
            return [];
        }

        return collect($this->gift_codes)->map(function ($codeData) {
            $code = $codeData['code'];
            if (strlen($code) <= 4) {
                $codeData['masked_code'] = $code;
            } else {
                $codeData['masked_code'] = substr($code, 0, 2) . str_repeat('*', strlen($code) - 4) . substr($code, -2);
            }
            return $codeData;
        })->toArray();
    }
}
