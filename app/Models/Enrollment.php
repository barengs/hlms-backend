<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Enrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'course_id',
        'order_item_id',
        'enrolled_at',
        'expires_at',
        'is_completed',
        'completed_at',
        'progress_percentage',
        'completed_lessons',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_completed' => 'boolean',
            'completed_at' => 'datetime',
            'progress_percentage' => 'integer',
            'completed_lessons' => 'array',
        ];
    }

    /**
     * Get the user who enrolled.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the course enrolled in.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Get the order item that initiated this enrollment.
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    /**
     * Get the batch this enrollment is associated with (for structured courses).
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    /**
     * Scope for active enrollments.
     */
    public function scopeActive($query)
    {
        return $query->where('is_completed', false)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Scope for completed enrollments.
     */
    public function scopeCompleted($query)
    {
        return $query->where('is_completed', true);
    }

    /**
     * Scope for expired enrollments.
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now())
            ->where('is_completed', false);
    }

    /**
     * Check if enrollment is active.
     */
    public function getIsActiveAttribute(): bool
    {
        return !$this->is_completed && (
            $this->expires_at === null || $this->expires_at > now()
        );
    }

    /**
     * Check if enrollment is expired.
     */
    public function getIsExpiredAttribute(): bool
    {
        return !$this->is_completed && $this->expires_at !== null && $this->expires_at < now();
    }
}
