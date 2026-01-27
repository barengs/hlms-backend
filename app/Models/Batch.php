<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Batch extends Model
{
    use HasFactory;


    protected $fillable = [
        'instructor_id',
        'name',
        'slug',
        'class_code',
        'description',
        'type',
        'start_date',
        'end_date',
        'enrollment_start_date',
        'enrollment_end_date',
        'max_students',
        'current_students',
        'status',
        'is_public',
        'auto_approve',
        'thumbnail',
    ];

    public function casts(): array
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
            'type' => 'string',
        ];
    }

    /**
     * Get the instructor who owns this batch (for classroom type).
     */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    /**
     * Get all instructors assigned to this batch (many-to-many).
     * For structured batches where admin assigns multiple instructors.
     */
    public function instructors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'batch_instructor', 'batch_id', 'instructor_id')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get the courses in this batch (many-to-many).
     */
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'batch_course')
            ->withPivot('order', 'is_required')
            ->withTimestamps()
            ->orderBy('batch_course.order');
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
     * Scope for public batches (visible on landing page).
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope for classroom type batches (Google Classroom style).
     */
    public function scopeClassroom($query)
    {
        return $query->where('type', 'classroom');
    }

    /**
     * Scope for structured type batches (traditional bootcamp/cohort).
     */
    public function scopeStructured($query)
    {
        return $query->where('type', 'structured');
    }

    /**
     * Scope for upcoming batches.
     */
    public function scopeUpcoming($query)
    {
        return $query->where('start_date', '>', now())
            ->orderBy('start_date');
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

    /**
     * Generate a unique class code.
     */
    public static function generateClassCode(): string
    {
        do {
            $code = strtoupper(bin2hex(random_bytes(3))); // 6 chars
        } while (self::where('class_code', $code)->exists());

        return $code;
    }
}
