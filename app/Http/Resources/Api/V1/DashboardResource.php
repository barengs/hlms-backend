<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Since we are wrapping a simple array/collection of stats,
        // we can just return parent::toArray or customize if needed.
        // If keys are 'stats' and 'recent_orders' etc, they are passed as array.
        return parent::toArray($request);
    }
}
