<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceRecordResource extends JsonResource
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
            'user_id' => $this->user_id,
            'user' => new UserResource($this->whenLoaded('user')),
            'date' => $this->date->format('Y-m-d'),
            'clock_in' => $this->clock_in?->format('H:i') ?? '',
            'clock_out' => $this->clock_out?->format('H:i') ?? '',
            'total_time' => $this->formatSecondsToHM($this->totalTime),
            'total_break_time' => $this->formatSecondsToHM($this->totalBreakTime),
            'comment' => $this->comment,
            'breaks' => BreakRecordResource::collection($this->whenLoaded('breakRecords')),
            'applications' => AttendanceRequestResource::collection($this->whenLoaded('attendanceRequests')),
        ];
    }
}
