<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseResource extends JsonResource
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
            'slug' => $this->slug,
            'description' => $this->description,
            'thumbnail' => $this->thumbnail, // Ensure this accessor/attribute exists
            'price' => $this->price,
            'level' => $this->level,
            'instructor' => new UserResource($this->whenLoaded('instructor')),
            'category' => $this->category ? $this->category->name : null,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
