<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LearningPath extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'thumbnail',
        'category_id',
        'level',
        'estimated_duration',
        'is_featured',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'estimated_duration' => 'integer',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the category of this learning path.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get all items (courses) in this learning path.
     */
    public function items(): HasMany
    {
        return $this->hasMany(LearningPathItem::class)->orderBy('step_number');
    }

    /**
     * Get all courses in this learning path (many-to-many).
     */
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'learning_path_items')
            ->withPivot('step_number', 'step_title', 'step_description', 'is_required', 'sort_order')
            ->withTimestamps()
            ->orderBy('learning_path_items.step_number');
    }

    /**
     * Scope for active learning paths.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for featured learning paths.
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }
}
