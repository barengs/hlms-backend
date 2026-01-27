<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StructuredBatchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            // 'class_code' => $this->class_code, // Optional for structured?
            'type' => 'structured',
            'status' => $this->status,
            
            // Schedule & Capacity
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'enrollment_start_date' => $this->enrollment_start_date,
            'enrollment_end_date' => $this->enrollment_end_date,
            'max_students' => $this->max_students,
            'current_students' => $this->current_students,
            'is_open_for_enrollment' => $this->is_open_for_enrollment,

            // Courses (Ordered)
            'courses' => $this->whenLoaded('courses', function() {
                return $this->courses->sortBy('pivot.order')->values()->map(function($course) {
                    return [
                        'id' => $course->id,
                        'title' => $course->title,
                        'slug' => $course->slug,
                        'thumbnail' => $course->thumbnail,
                        'order' => $course->pivot->order ?? null,
                        'is_required' => $course->pivot->is_required ?? null,
                    ];
                });
            }),

            // Stats
            'assignments_count' => $this->assignments_count ?? $this->whenLoaded('assignments', fn() => $this->assignments->count()),
            
            'created_at' => $this->created_at,
        ];
    }
}
