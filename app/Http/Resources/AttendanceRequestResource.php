<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceRequestResource extends JsonResource
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
            'request_status' => $this->request_status,
            'clock_in' => $this->clock_in?->format('H:i') ?? '',
            'clock_out' => $this->clock_out?->format('H:i') ?? '',
            'comment' => $this->comment,
            'request_date' => $this->request_date->format('Y-m-d'),
            'breaks' => BreakRequestResource::collection($this->whenLoaded('breakRequests')),
        ];
    }
}
