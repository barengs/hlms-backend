<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'lesson_id',
        'title',
        'description',
        'instructions',
        'type',
        'content',
        'due_date',
        'available_from',
        'max_points',
        'gradable',
        'allow_multiple_submissions',
        'is_published',
        'is_required',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'due_date' => 'datetime',
            'available_from' => 'datetime',
            'max_points' => 'integer',
            'gradable' => 'boolean',
            'allow_multiple_submissions' => 'boolean',
            'is_published' => 'boolean',
            'is_required' => 'boolean',
        ];
    }

    /**
     * Get the batch this assignment belongs to.
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    /**
     * Get the lesson this assignment is associated with.
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * Get all submissions for this assignment.
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    /**
     * Scope for published assignments.
     */
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    /**
     * Scope for assignments with due date in the future.
     */
    public function scopeNotDueYet($query)
    {
        return $query->where('due_date', '>', now());
    }

    /**
     * Scope for overdue assignments.
     */
    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
            ->where('is_published', true);
    }

    /**
     * Check if the assignment is available.
     */
    public function getIsAvailableAttribute(): bool
    {
        return $this->is_published && 
               (!$this->available_from || $this->available_from <= now());
    }

    /**
     * Check if the assignment is overdue.
     */
    public function getIsOverdueAttribute(): bool
    {
        return $this->due_date && $this->due_date < now();
    }
}
