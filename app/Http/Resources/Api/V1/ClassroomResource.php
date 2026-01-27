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
            
            // Instructor View: Detailed Student List
            'students' => $this->whenLoaded('enrollments', function() {
                return $this->enrollments->map(function($enrollment) {
                    $grade = $this->grades->where('user_id', $enrollment->user_id)->first();
                    return [
                        'id' => $enrollment->user_id,
                        'name' => $enrollment->student->name ?? 'Unknown',
                        'email' => $enrollment->student->email ?? 'N/A',
                        'avatar' => $enrollment->student->profile->avatar ?? null,
                        'joined_at' => $enrollment->enrolled_at,
                        'progress' => $enrollment->progress_percentage,
                        'assignments_completed' => 0, // Placeholder - requires heavy query or cache
                        'assignments_total' => 0,    // Placeholder
                        'grade_score' => $grade->overall_score ?? null,
                        'grade_letter' => $grade->letter_grade ?? null,
                    ];
                });
            }),
            // Instructor View: Assessment Stats
            'assessment_stats' => $this->whenLoaded('assignments', function() {
                $totalAssignments = $this->assignments->count();
                // Submissions requiring grading: submitted but not graded
                $ungradedCount = 0;
                
                // This is N+1 if not careful, but for a single batch detail it's acceptable or needs eager loading 'assignments.submissions'
                foreach ($this->assignments as $assignment) {
                     // We need to count submissions where status is 'submitted'
                     // Assuming 'submissions' relation is loaded or we load it now
                     $ungradedCount += $assignment->submissions()->where('status', 'submitted')->count();
                }

                return [
                    'assignments_count' => $totalAssignments,
                    'ungraded_submissions_count' => $ungradedCount,
                    'class_average_score' => $this->grades->avg('overall_score') ?? 0,
                    'achieving_students_count' => $this->grades->where('overall_score', '>=', 80)->count(),
                    'needs_attention_count' => $this->grades->where('overall_score', '<', 50)->count(),
                ];
            }),
        ];
    }
}
