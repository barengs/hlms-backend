<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Submission extends Model
{
    use HasFactory;

    protected $fillable = [
        'assignment_id',
        'user_id',
        'content',
        'answers',
        'files',
        'notes',
        'status',
        'submitted_at',
        'points_awarded',
        'instructor_feedback',
        'graded_at',
        'graded_by',
    ];

    public function casts(): array
    {
        return [
            'answers' => 'array',
            'files' => 'array',
            'submitted_at' => 'datetime',
            'graded_at' => 'datetime',
            'points_awarded' => 'integer',
        ];
    }

    /**
     * Get the assignment this submission belongs to.
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    /**
     * Get the user who submitted this.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the instructor who graded this submission.
     */
    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    /**
     * Scope for submitted assignments.
     */
    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }

    /**
     * Scope for graded assignments.
     */
    public function scopeGraded($query)
    {
        return $query->where('status', 'graded');
    }

    /**
     * Scope for late submissions.
     */
    public function scopeLate($query)
    {
        return $query->where('status', 'late');
    }

    /**
     * Check if submission is late.
     */
    public function getIsLateAttribute(): bool
    {
        return $this->submitted_at && $this->assignment->due_date && $this->submitted_at > $this->assignment->due_date;
    }

    /**
     * Check if submission is graded.
     */
    public function getIsGradedAttribute(): bool
    {
        return $this->status === 'graded';
    }

    /**
     * Calculate percentage score.
     */
    public function getPercentageScoreAttribute(): float
    {
        if (!$this->points_awarded || !$this->assignment->max_points) {
            return 0;
        }
        
        return ($this->points_awarded / $this->assignment->max_points) * 100;
    }
}
