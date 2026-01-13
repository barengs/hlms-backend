<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Discussion extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'lesson_id',
        'user_id',
        'parent_id',
        'title',
        'content',
        'type',
        'is_pinned',
        'is_approved',
        'is_locked',
        'replies_count',
        'views_count',
        'upvotes_count',
    ];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'is_approved' => 'boolean',
            'is_locked' => 'boolean',
            'replies_count' => 'integer',
            'views_count' => 'integer',
            'upvotes_count' => 'integer',
        ];
    }

    /**
     * Get the batch this discussion belongs to.
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    /**
     * Get the lesson this discussion is associated with.
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * Get the user who created this discussion.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent discussion (for replies).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Discussion::class, 'parent_id');
    }

    /**
     * Get all replies to this discussion.
     */
    public function replies(): HasMany
    {
        return $this->hasMany(Discussion::class, 'parent_id');
    }

    /**
     * Scope for approved discussions.
     */
    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    /**
     * Scope for questions.
     */
    public function scopeQuestions($query)
    {
        return $query->where('type', 'question');
    }

    /**
     * Scope for announcements.
     */
    public function scopeAnnouncements($query)
    {
        return $query->where('type', 'announcement');
    }

    /**
     * Scope for pinned discussions.
     */
    public function scopePinned($query)
    {
        return $query->where('is_pinned', true);
    }

    /**
     * Check if this is a reply.
     */
    public function getIsReplyAttribute(): bool
    {
        return $this->parent_id !== null;
    }

    /**
     * Check if this discussion is locked.
     */
    public function getIsLockedAttribute(): bool
    {
        return $this->is_locked;
    }

    /**
     * Check if this discussion is a question.
     */
    public function getIsQuestionAttribute(): bool
    {
        return $this->type === 'question';
    }

    /**
     * Check if this discussion is an announcement.
     */
    public function getIsAnnouncementAttribute(): bool
    {
        return $this->type === 'announcement';
    }
}
