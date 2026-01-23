<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningPathItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'learning_path_id',
        'course_id',
        'step_number',
        'step_title',
        'step_description',
        'is_required',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'step_number' => 'integer',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Get the learning path this item belongs to.
     */
    public function learningPath(): BelongsTo
    {
        return $this->belongsTo(LearningPath::class);
    }

    /**
     * Get the course for this item.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
