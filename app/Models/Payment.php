<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'payment_gateway',
        'payment_method',
        'transaction_id',
        'payment_status',
        'amount',
        'fee_amount',
        'gateway_response',
        'metadata',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fee_amount' => 'decimal:2',
            'gateway_response' => 'array',
            'metadata' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the order this payment belongs to.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Check if payment is successful.
     */
    public function getIsSuccessfulAttribute(): bool
    {
        return in_array($this->payment_status, ['settlement', 'capture']);
    }

    /**
     * Check if payment is pending.
     */
    public function getIsPendingAttribute(): bool
    {
        return $this->payment_status === 'pending';
    }

    /**
     * Check if payment failed.
     */
    public function getIsFailedAttribute(): bool
    {
        return in_array($this->payment_status, ['cancel', 'expire', 'failure']);
    }

    /**
     * Scope for successful payments.
     */
    public function scopeSuccessful($query)
    {
        return $query->whereIn('payment_status', ['settlement', 'capture']);
    }

    /**
     * Scope for pending payments.
     */
    public function scopePending($query)
    {
        return $query->where('payment_status', 'pending');
    }

    /**
     * Scope for failed payments.
     */
    public function scopeFailed($query)
    {
        return $query->whereIn('payment_status', ['cancel', 'expire', 'failure']);
    }
}
