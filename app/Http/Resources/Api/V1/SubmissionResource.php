<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubmissionResource extends JsonResource
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
            'assignment_id' => $this->assignment_id,
            'student' => new UserResource($this->whenLoaded('student')),
            'content' => $this->content,
            'files' => $this->files, // JSON cast
            'status' => $this->status,
            'submitted_at' => $this->submitted_at,
            'grade' => $this->grade, // If numeric grade column exists separately, otherwise points_awarded
            'points_awarded' => $this->points_awarded,
            'feedback' => $this->instructor_feedback,
            'graded_at' => $this->graded_at,
        ];
    }
}
