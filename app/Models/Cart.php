<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'subtotal',
        'discount',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    /**
     * Get the user who owns the cart.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all items in the cart.
     */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Get total quantity of items in cart.
     */
    public function getTotalQuantityAttribute(): int
    {
        return $this->items->count();
    }

    /**
     * Get total price of items in cart.
     */
    public function getTotalPriceAttribute(): float
    {
        return $this->items->sum('price');
    }

    /**
     * Update cart totals.
     */
    public function updateTotals(): void
    {
        $subtotal = $this->items->sum('price');
        $this->update([
            'subtotal' => $subtotal,
            'total' => $subtotal - $this->discount,
        ]);
    }
}
