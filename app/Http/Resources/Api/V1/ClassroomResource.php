<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassroomResource extends JsonResource
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
            'class_code' => $this->class_code,
            'type' => 'classroom',
            'instructor' => $this->whenLoaded('instructor', function() {
                return [
                    'id' => $this->instructor->id,
                    'name' => $this->instructor->name,
                    'avatar' => $this->instructor->profile->avatar ?? null, 
                ];
            }),
            'courses' => $this->whenLoaded('courses', function() {
                return $this->courses->map(function($course) {
                    return [
                        'id' => $course->id,
                        'title' => $course->title,
                        'slug' => $course->slug,
                        'thumbnail' => $course->thumbnail,
                        'sections_count' => $course->sections_count ?? $course->sections()->count(),
                        // Add pivot data if available (e.g. order) though less critical for classroom
                        'pivot' => $course->pivot ?? null,
                    ];
                });
            }),
            'students_count' => $this->current_students,
            'created_at' => $this->created_at,
            'is_open_for_enrollment' => $this->is_open_for_enrollment,
            
            // Student specific fields
            'is_enrolled' => $this->whenLoaded('enrollments', function() use ($request) {
                 return $this->enrollments->contains('user_id', $request->user()->id ?? 0);
            }),
        ];
    }
}
