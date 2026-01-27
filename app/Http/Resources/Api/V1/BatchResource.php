<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BatchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Get first course for thumbnail and title
        $firstCourse = null;
        if ($this->relationLoaded('courses') && $this->courses->isNotEmpty()) {
            $firstCourse = $this->courses->first();
        }

        // Calculate sections count from all courses
        $sectionsCount = 0;
        $lessonsCount = 0;
        if ($this->relationLoaded('courses')) {
            foreach ($this->courses as $course) {
                if ($course->relationLoaded('sections')) {
                    $sectionsCount += $course->sections->count();
                    foreach ($course->sections as $section) {
                        if ($section->relationLoaded('lessons')) {
                            $lessonsCount += $section->lessons->count();
                        }
                    }
                }
            }
        }

        // Calculate average grade
        $averageGrade = null;
        if ($this->relationLoaded('grades') && $this->grades->count() > 0) {
            $averageGrade = round($this->grades->avg('overall_score'), 1);
        }

        // Get recent students (max 5) with avatars
        $recentStudents = [];
        if ($this->relationLoaded('enrollments')) {
            $recentStudents = $this->enrollments
                ->take(5)
                ->map(function ($enrollment) {
                    if ($enrollment->relationLoaded('student')) {
                        return [
                            'id' => $enrollment->student->id,
                            'name' => $enrollment->student->name,
                            'avatar' => $enrollment->student->avatar ?? null,
                        ];
                    }
                    return null;
                })
                ->filter()
                ->values()
                ->toArray();
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'thumbnail' => $this->thumbnail ?? ($firstCourse ? $firstCourse->thumbnail : null),
            'course_title' => $firstCourse ? $firstCourse->title : null,
            'class_code' => $this->class_code,
            'status' => $this->status,
            'type' => $this->type,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'max_students' => $this->max_students,
            'current_students' => $this->current_students,
            'sections_count' => $sectionsCount,
            'lessons_count' => $lessonsCount,
            'average_grade' => $averageGrade,
            'recent_students' => $recentStudents,
            'is_open_for_enrollment' => $this->is_open_for_enrollment,
            'created_at' => $this->created_at,
        ];
    }
}
