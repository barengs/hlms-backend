<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssignmentResource extends JsonResource
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
            'title' => $this->title,
            'description' => $this->description,
            'batch_id' => $this->batch_id,
            'batch' => new BatchResource($this->whenLoaded('batch')),
            'due_date' => $this->due_date,
            'max_points' => $this->max_points,
            'is_published' => $this->is_published,
            'my_submission' => new SubmissionResource($this->whenLoaded('mySubmission')), // Usage depends on controller loading this
            'created_at' => $this->created_at,
        ];
    }
}
