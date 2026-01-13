<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Batch extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'name',
        'slug',
        'description',
        'start_date',
        'end_date',
        'enrollment_start_date',
        'enrollment_end_date',
        'max_students',
        'current_students',
        'status',
        'is_public',
        'auto_approve',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'enrollment_start_date' => 'datetime',
            'enrollment_end_date' => 'datetime',
            'max_students' => 'integer',
            'current_students' => 'integer',
            'is_public' => 'boolean',
            'auto_approve' => 'boolean',
        ];
    }

    /**
     * Get the course this batch belongs to.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Get all assignments for this batch.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /**
     * Get all enrollments for this batch.
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Get all grades for this batch.
     */
    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    /**
     * Get all discussions for this batch.
     */
    public function discussions(): HasMany
    {
        return $this->hasMany(Discussion::class);
    }

    /**
     * Scope for active/in-progress batches.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'in_progress');
    }

    /**
     * Scope for open enrollment batches.
     */
    public function scopeOpenForEnrollment($query)
    {
        return $query->where('status', 'open')
            ->where('enrollment_start_date', '<=', now())
            ->where('enrollment_end_date', '>', now());
    }

    /**
     * Check if the batch is currently open for enrollment.
     */
    public function getIsOpenForEnrollmentAttribute(): bool
    {
        return $this->status === 'open' &&
            $this->enrollment_start_date <= now() &&
            $this->enrollment_end_date > now() &&
            ($this->max_students === null || $this->current_students < $this->max_students);
    }

    /**
     * Check if the batch is full.
     */
    public function getIsFullAttribute(): bool
    {
        return $this->max_students !== null && $this->current_students >= $this->max_students;
    }

    /**
     * Check if the batch has started.
     */
    public function getHasStartedAttribute(): bool
    {
        return $this->start_date <= now();
    }

    /**
     * Check if the batch has ended.
     */
    public function getHasEndedAttribute(): bool
    {
        return $this->end_date < now();
    }
}
