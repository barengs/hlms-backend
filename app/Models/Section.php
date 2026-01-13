<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'title',
        'description',
        'sort_order',
    ];

    /**
     * Get the course this section belongs to.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Get all lessons in this section.
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('sort_order');
    }

    /**
     * Get total duration of all lessons in this section.
     */
    public function getTotalDurationAttribute(): int
    {
        return $this->lessons->sum('duration');
    }

    /**
     * Get count of lessons in this section.
     */
    public function getLessonsCountAttribute(): int
    {
        return $this->lessons->count();
    }
}
