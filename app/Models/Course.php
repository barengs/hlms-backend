<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Course extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'instructor_id',
        'category_id',
        'title',
        'slug',
        'subtitle',
        'description',
        'thumbnail',
        'preview_video',
        'type',
        'level',
        'language',
        'price',
        'discount_price',
        'requirements',
        'outcomes',
        'target_audience',
        'status',
        'is_featured',
        'published_at',
        'total_duration',
        'total_lessons',
        'total_enrollments',
        'average_rating',
        'total_reviews',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'discount_price' => 'decimal:2',
            'requirements' => 'array',
            'outcomes' => 'array',
            'target_audience' => 'array',
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
            'average_rating' => 'decimal:2',
        ];
    }

    /**
     * Get the instructor (user) who created this course.
     */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    /**
     * Get the category of this course.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get all sections of this course.
     */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class)->orderBy('sort_order');
    }

    /**
     * Get all lessons through sections.
     */
    public function lessons(): HasManyThrough
    {
        return $this->hasManyThrough(Lesson::class, Section::class);
    }

    /**
     * Get all order items for this course.
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get all enrollments for this course.
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Get all cart items for this course.
     */
    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Get all batches for this course.
     */
    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    /**
     * Get the total sales count for this course.
     */
    public function getTotalSalesAttribute(): int
    {
        return $this->enrollments()->count();
    }

    /**
     * Get the total revenue for this course.
     */
    public function getTotalRevenueAttribute(): float
    {
        return $this->orderItems()
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.status', 'paid')
            ->sum('order_items.price');
    }

    /**
     * Scope for published courses.
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at');
    }

    /**
     * Scope for featured courses.
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope for self-paced courses.
     */
    public function scopeSelfPaced($query)
    {
        return $query->where('type', 'self_paced');
    }

    /**
     * Scope for structured courses.
     */
    public function scopeStructured($query)
    {
        return $query->where('type', 'structured');
    }

    /**
     * Get the effective price (considering discount).
     */
    public function getEffectivePriceAttribute(): float
    {
        return $this->discount_price ?? $this->price;
    }

    /**
     * Check if course is free.
     */
    public function getIsFreeAttribute(): bool
    {
        return $this->effective_price == 0;
    }

    /**
     * Check if course is on sale.
     */
    public function getIsOnSaleAttribute(): bool
    {
        return $this->discount_price !== null && $this->discount_price < $this->price;
    }
}
