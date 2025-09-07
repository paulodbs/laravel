<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'image',
        'price_options',
        'is_active',
        'sort_order',
        'sales_count',
        'rating',
        'reviews_count',
    ];

    protected $casts = [
        'price_options' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'sales_count' => 'integer',
        'reviews_count' => 'integer',
        'rating' => 'decimal:2',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($product) {
            if (empty($product->slug)) {
                $product->slug = Str::slug($product->name);
            }
        });
    }

    /**
     * Product category relationship
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Product gift codes relationship
     */
    public function giftCodes()
    {
        return $this->hasMany(GiftCode::class);
    }

    /**
     * Available gift codes only
     */
    public function availableGiftCodes()
    {
        return $this->hasMany(GiftCode::class)->where('status', 'available');
    }

    /**
     * Product order items relationship
     */
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Scope for active products
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for sorting by popularity
     */
    public function scopePopular($query)
    {
        return $query->orderBy('sales_count', 'desc');
    }

    /**
     * Scope for sorting
     */
    public function scopeSorted($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Get available gift codes for a specific value
     */
    public function getAvailableCodesForValue($value, $quantity = 1)
    {
        return $this->availableGiftCodes()
            ->where('value', $value)
            ->limit($quantity)
            ->get();
    }

    /**
     * Get price for a specific value
     */
    public function getPriceForValue($value)
    {
        foreach ($this->price_options as $option) {
            if ($option['value'] == $value) {
                return $option['price'];
            }
        }
        return null;
    }
}
