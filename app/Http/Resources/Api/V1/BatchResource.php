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
        return [
            'id' => $this->id,
            'name' => $this->name,
            'course' => new CourseResource($this->whenLoaded('course')),
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'max_students' => $this->max_students,
            'current_students' => $this->current_students,
            'status' => $this->status, // Accessor or computed attribute
            'is_open_for_enrollment' => $this->is_open_for_enrollment,
            // 'instructors' => UserResource::collection($this->whenLoaded('instructors')), // If applicable
            'created_at' => $this->created_at,
        ];
    }
}
